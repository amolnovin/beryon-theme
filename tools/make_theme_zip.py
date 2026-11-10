#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
make_theme_zip.py — ساخت بسته نصب کامل قالب بریون (beryon-wp.zip).

بسته همان چیزی است که از «نمایش ← پوسته‌ها ← افزودن ← بارگذاری پوسته» نصب
می‌شود یا با FTP جایگزین پوشه قالب می‌شود:

    beryon-wp.zip
    └── beryon-wp/            ← یک پوشه سطح اول (نام پوشه قالب روی سایت)
        ├── style.css
        ├── functions.php
        ├── app/ … resources/ … config/ … public/assets/ (بیلدشده)
        └── …

چه فایل‌هایی داخل بسته می‌آیند:
  * همه فایل‌های پوشه beryon-wp/ که گیت می‌شناسد یا نادیده نگرفته
    (git ls-files -co --exclude-standard)؛ پس node_modules، vendor، پشتیبان‌ها و
    Patchهای اعمال‌شده و کش کامپایل Blade هرگز وارد بسته نمی‌شوند.
  * public/assets/manifest.json — خروجی بیلد است و در مخزن نیست، ولی قالب در
    زمان اجرا آن را می‌خواند؛ پیش از بسته‌بندی tools/build_assets.py اجرا می‌شود
    تا public/assets با resources هم‌خوان باشد.
  بدون گیت (مثلاً روی نسخه استخراج‌شده)، پوشه پیمایش می‌شود و همان الگوهای
  .gitignore کنار گذاشته می‌شوند.

نام پوشه سطح اول مهم است: وردپرس «جایگزینی با نسخه بارگذاری‌شده» را فقط وقتی
پیشنهاد می‌دهد که این نام با نام پوشه قالبِ نصب‌شده یکی باشد؛ وگرنه بسته به‌صورت
یک قالب دوم نصب می‌شود و جایگاه منوها و لوگو (theme_mods) به آن منتقل نمی‌شود.
پیش‌فرض «beryon-wp» است (نام پوشه در مخزن و در بسته‌های پیشین)؛ اگر قالب روی
سایت در پوشه دیگری است، با --folder همان نام را بدهید.

کاربرد:
    python3 tools/make_theme_zip.py [--folder NAME] [--out PATH] [--no-build]

    --folder NAME  نام پوشه سطح اول داخل zip (پیش‌فرض: beryon-wp)
    --out PATH     مسیر فایل خروجی (پیش‌فرض: <ریشه مخزن>/beryon-wp.zip)
    --no-build     tools/build_assets.py اجرا نشود (public/assets همان‌طور که هست)

زمان هر فایل داخل بسته، زمان تغییر همان فایل روی دیسک است و بیلد دارایی‌ها
فایل‌های public/assets را از نو می‌نویسد؛ بنابراین sha256 بسته در هر بار ساخت
فرق می‌کند (محتوای فایل‌ها یکسان است). زمان ثابت عمداً به‌کار نرفته: روی
میزبان‌هایی که zip را با PclZip باز می‌کنند زمان فایل‌ها از بسته می‌آید و
Assets.php همان را برای نسخه‌گذاری کش مرورگر (filemtime) استفاده می‌کند.
"""
import fnmatch
import hashlib
import io
import os
import re
import subprocess
import sys
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
THEME_DIR = 'beryon-wp'
THEME = os.path.join(ROOT, THEME_DIR)

# خروجی‌های بیلد که در مخزن نیستند ولی قالب در زمان اجرا لازمشان دارد.
BUILT_FILES = ['public/assets/manifest.json']

# فایل‌هایی که بدون آن‌ها بسته قابل نصب/اجرا نیست.
REQUIRED_FILES = [
    'style.css', 'functions.php', 'index.php', 'screenshot.png',
    'public/assets/css/app.css', 'public/assets/js/app.js', 'public/assets/manifest.json',
]

# حالت بدون گیت: همان الگوهای .gitignore ریشه و قالب (مسیر نسبت به ریشه قالب).
WALK_SKIP_DIRS = ['node_modules', 'vendor', 'public/hot', 'public/assets/js/chunks', 'public/patches/backups', '.git']
WALK_SKIP_FILES = [
    '*.log', '.DS_Store', 'Thumbs.db', '.env', '.env.*', 'public/patches/*.zip',
    'storage/framework/views/*.php',
]
WALK_KEEP_FILES = ['.env.example']


def fail(message):
    print('✗ ' + message)
    return 1


def theme_header(name):
    """مقدار یک سربرگ style.css (مثل Version)."""
    with io.open(os.path.join(THEME, 'style.css'), encoding='utf-8') as handle:
        head = handle.read(8192)
    match = re.search(r'^[ \t/*#@]*' + re.escape(name) + r':\s*(.+)$', head, re.M | re.I)
    return match.group(1).strip() if match else ''


def fallback_version():
    """نسخه پشتیبانِ سخت‌کد در functions.php (وقتی style.css خوانده نشود)."""
    with io.open(os.path.join(THEME, 'functions.php'), encoding='utf-8') as handle:
        source = handle.read()
    match = re.search(r"define\('BERYON_VERSION',[^;]*:\s*'([0-9][0-9A-Za-z.\-]*)'\s*\)", source)
    return match.group(1) if match else ''


def files_from_git():
    """فهرست فایل‌های قالب از گیت؛ None یعنی گیت در دسترس نیست."""
    try:
        out = subprocess.check_output(
            ('git', '-C', ROOT, 'ls-files', '-z', '--cached', '--others', '--exclude-standard', '--', THEME_DIR),
            stderr=subprocess.DEVNULL,
        )
    except (OSError, subprocess.CalledProcessError):
        return None

    prefix = THEME_DIR + '/'
    names = set()

    for item in out.decode('utf-8').split('\0'):
        if item.startswith(prefix):
            names.add(item[len(prefix):])

    return names or None


def walk_skips(relative):
    """آیا این فایل در حالت پیمایش باید کنار گذاشته شود؟ (معادل .gitignore)"""
    if relative in WALK_KEEP_FILES:
        return False

    name = relative.rsplit('/', 1)[-1]

    for pattern in WALK_SKIP_FILES:
        # الگوی دارای «/» با مسیر کامل و الگوی ساده با نام فایل سنجیده می‌شود.
        if fnmatch.fnmatchcase(relative if '/' in pattern else name, pattern):
            return True

    return False


def files_from_walk():
    """پیمایش پوشه با همان استثناهای .gitignore (برای محیط بدون گیت)."""
    names = set()

    for base, dirs, files in os.walk(THEME):
        relative_base = os.path.relpath(base, THEME).replace(os.sep, '/')
        relative_base = '' if relative_base == '.' else relative_base + '/'

        dirs[:] = sorted(d for d in dirs if (relative_base + d) not in WALK_SKIP_DIRS)

        for name in files:
            if not walk_skips(relative_base + name):
                names.add(relative_base + name)

    return names


def sha256_of(path):
    digest = hashlib.sha256()
    with open(path, 'rb') as handle:
        for chunk in iter(lambda: handle.read(1 << 16), b''):
            digest.update(chunk)
    return digest.hexdigest()


def parse_args(argv):
    opts = {'folder': THEME_DIR, 'out': os.path.join(ROOT, 'beryon-wp.zip'), 'build': True}
    i = 0

    while i < len(argv):
        arg = argv[i]

        if arg in ('-h', '--help'):
            print(__doc__.strip())
            raise SystemExit(0)

        if arg == '--no-build':
            opts['build'] = False
            i += 1
            continue

        matched = False

        for key in ('folder', 'out'):
            flag = '--' + key

            if arg == flag and i + 1 < len(argv):
                opts[key] = argv[i + 1]
                i += 2
                matched = True
                break

            if arg.startswith(flag + '='):
                opts[key] = arg.split('=', 1)[1]
                i += 1
                matched = True
                break

        if not matched:
            raise SystemExit('گزینه ناشناخته: %s\n\n%s' % (arg, __doc__.strip().split('کاربرد:', 1)[-1].strip()))

    return opts


def main():
    opts = parse_args(sys.argv[1:])
    folder = opts['folder'].strip().strip('/')

    # نام پوشه همان «شناسه قالب» در وردپرس می‌شود؛ فقط نویسه‌های امن.
    if not re.match(r'^[A-Za-z0-9][A-Za-z0-9._-]*$', folder):
        return fail('نام پوشه نامعتبر است: %r (فقط حروف لاتین، رقم، نقطه، خط تیره و زیرخط)' % opts['folder'])

    out = os.path.abspath(opts['out'])

    if not out.lower().endswith('.zip'):
        return fail('مسیر خروجی باید به .zip ختم شود: %s' % out)

    try:
        inside_theme = os.path.commonpath([out, THEME]) == THEME
    except ValueError:  # درایوهای متفاوت در ویندوز
        inside_theme = False

    if inside_theme:
        return fail('خروجی نباید داخل پوشه قالب ساخته شود: %s' % out)

    if opts['build']:
        print('۱) بیلد دارایی‌ها (tools/build_assets.py)…')
        result = subprocess.call([sys.executable, os.path.join(ROOT, 'tools', 'build_assets.py')], stdout=subprocess.DEVNULL)

        if result != 0:
            return fail('بیلد دارایی‌ها ناموفق بود (کد %d)؛ «python3 tools/build_assets.py» را جدا اجرا و خطا را ببینید.' % result)
    else:
        print('۱) بیلد دارایی‌ها رد شد (--no-build).')

    names = files_from_git()
    mode = 'git ls-files (ردگیری‌شده + تازه، بدون فایل‌های نادیده‌گرفته)'

    if names is None:
        names = files_from_walk()
        mode = 'پیمایش پوشه با استثناهای .gitignore (گیت در دسترس نبود)'

    names.update(BUILT_FILES)

    # فایل ردگیری‌شده‌ای که در پوشه کاری حذف شده وارد بسته نمی‌شود.
    files = sorted(name for name in names if os.path.isfile(os.path.join(THEME, name)))

    missing = [name for name in REQUIRED_FILES if name not in files]

    if missing:
        return fail('فایل‌های ضروری پیدا نشد: %s' % ', '.join(missing))

    version = theme_header('Version')
    fallback = fallback_version()

    if version == '':
        return fail('سربرگ Version در style.css پیدا نشد.')

    if fallback != version:
        return fail('نسخه style.css (%s) با نسخه پشتیبان functions.php (%s) یکی نیست.' % (version, fallback or '—'))

    print('۲) روش انتخاب فایل‌ها: %s' % mode)

    temporary = out + '.tmp'

    with zipfile.ZipFile(temporary, 'w', zipfile.ZIP_DEFLATED) as archive:
        for name in files:
            archive.write(os.path.join(THEME, name), folder + '/' + name)

    # خودآزمایی: بسته سالم باشد، فقط یک پوشه سطح اول داشته باشد و هیچ فایلی جا نمانده باشد.
    with zipfile.ZipFile(temporary) as archive:
        broken = archive.testzip()
        entries = archive.namelist()
        packed_version = ''
        match = re.search(r'^Version:\s*(.+)$', archive.read(folder + '/style.css').decode('utf-8'), re.M)

        if match:
            packed_version = match.group(1).strip()

    problems = []

    if broken:
        problems.append('ورودی خراب: ' + broken)

    if {entry.split('/', 1)[0] for entry in entries} != {folder}:
        problems.append('بیش از یک پوشه سطح اول')

    if len(entries) != len(files):
        problems.append('تعداد ورودی‌ها (%d) با فهرست (%d) یکی نیست' % (len(entries), len(files)))

    if packed_version != version:
        problems.append('نسخه داخل بسته: %s' % (packed_version or '—'))

    if problems:
        os.remove(temporary)

        return fail('خودآزمایی بسته ناموفق: ' + '؛ '.join(problems))

    os.replace(temporary, out)

    views = sum(1 for name in files if name.startswith('resources/views/') and name.endswith('.blade.php'))

    print('بسته قالب ساخته شد: %s' % out)
    print('  نسخه       : %s (Requires PHP %s / WordPress %s)' % (
        version, theme_header('Requires PHP') or '—', theme_header('Requires at least') or '—'))
    print('  پوشه سطح اول: %s/' % folder)
    print('  فایل‌ها     : %d (نماهای Blade: %d)' % (len(files), views))
    print('  حجم        : %d بایت' % os.path.getsize(out))
    print('  sha256 بسته : %s' % sha256_of(out))
    print('  خودآزمایی   : ✔ سالم')

    return 0


if __name__ == '__main__':
    sys.exit(main())
