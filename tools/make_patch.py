#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
make_patch.py — ساخت بسته به‌روزرسانی Patch برای قالب بریون.

دقیقاً همان قراردادی را تولید می‌کند که app/Updater.php::create() می‌سازد و
app/Updater/PatchApplier.php::validate_manifest() اعتبارسنجی می‌کند:

    beryon-patch-<version>.zip
    ├── patch.json     (format:1, theme:"beryon", files[], post_apply[])
    ├── README.txt
    └── files/<مسیر نسبی قالب>

هر ردیف files:
    {"path", "from": "files/<path>", "op": "copy", "sha256", "size", "mode": "0644"}

فایل‌های بسته از میان تغییرات پس از نسخه ۱.۰.۰ انتخاب می‌شوند و فقط آن‌هایی که
در updater.patch_slug_allowlist و updater.patch_ext_allowlist قالب جای می‌گیرند
(در غیر این‌صورت خود قالب هنگام اعتبارسنجی بسته را رد می‌کند).

کاربرد:  python3 tools/make_patch.py <version> [--since "YYYY-MM-DD HH:MM"] [--base <git-ref>] [--from X]

دو روش انتخاب فایل‌ها:
  --since  فایل‌هایی که زمان تغییرشان (mtime) از این لحظه به بعد است. فقط در
           همان پوشه کاری که ویرایش‌ها در آن انجام شده معنا دارد؛ در clone تازه
           زمان همه فایل‌ها زمان checkout است و بسته شامل کل قالب می‌شود.
  --base   فایل‌هایی که نسبت به یک ref گیت (کامیت/تگ نسخه پیشین) تغییر کرده،
           اضافه یا حذف شده‌اند + فایل‌های تازه ردگیری‌نشده. به mtime وابسته نیست.
           اگر مدخل RELEASES کلید «base» داشته باشد و --since داده نشود، همین روش
           به‌کار می‌رود. در این حالت requires.theme_min برابر from_version است
           (بسته تفاضلی فقط روی همان نسخه پایه قابل اعمال است).

بسته امضا نمی‌شود؛ برای سایتی که updater.signature_key دارد، signature.txt را
طبق docs/PATCH_SPEC.md (بخش ۷، شکل ۱) به zip اضافه کنید.
"""
import hashlib
import io
import json
import os
import subprocess
import sys
import time
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
THEME = os.path.join(ROOT, 'beryon-wp')

# همان فهرست‌های پیش‌فرض config/defaults.json → updater.*
SLUG_ALLOWLIST = [
    'app/', 'resources/', 'config/', 'public/', 'woocommerce/',
    'functions.php', 'index.php', 'style.css', 'theme.json', 'composer.json',
    'package.json', 'vite.config.js', 'tailwind.config.js', 'README.md', 'CHANGELOG.md',
]
EXT_ALLOWLIST = [
    'php', 'blade.php', 'css', 'js', 'json', 'md', 'txt', 'webp', 'png', 'jpg',
    'jpeg', 'svg', 'woff', 'woff2', 'htaccess', 'xml', 'po', 'mo', 'pot',
]


def sha256_of(path):
    digest = hashlib.sha256()
    with open(path, 'rb') as handle:
        for chunk in iter(lambda: handle.read(65536), b''):
            digest.update(chunk)
    return digest.hexdigest()


def allowed_slug(path):
    for prefix in SLUG_ALLOWLIST:
        if prefix.endswith('/'):
            if path.startswith(prefix):
                return True
        elif path == prefix or path.startswith(prefix + '/'):
            return True
    return False


def allowed_ext(path):
    base = os.path.basename(path).lower()
    return any(base == '.' + ext or base.endswith('.' + ext) for ext in EXT_ALLOWLIST)


def collect(since_stamp):
    changed = []
    for root, dirs, names in os.walk(THEME):
        dirs[:] = [d for d in dirs if d not in ('.git', 'node_modules', 'vendor', 'storage', 'public' + os.sep + 'patches')]
        for name in names:
            absolute = os.path.join(root, name)
            relative = os.path.relpath(absolute, THEME).replace(os.sep, '/')
            if os.path.getmtime(absolute) < since_stamp:
                continue
            if not allowed_slug(relative) or not allowed_ext(relative):
                print('   رد شد (بیرون از محدوده مجاز Patch): %s' % relative)
                continue
            changed.append(relative)
    return sorted(changed)


def collect_git(base):
    """فایل‌های تغییرکرده و حذف‌شده قالب نسبت به یک ref گیت.

    خروجی: (فهرست مسیرهای موجود برای کپی، فهرست مسیرهای حذف‌شده) — هر دو نسبت
    به ریشه قالب و فقط در محدوده مجاز Patch.
    """
    prefix = 'beryon-wp/'

    def git(*args):
        try:
            out = subprocess.check_output(('git', '-C', ROOT) + args, stderr=subprocess.STDOUT)
        except (OSError, subprocess.CalledProcessError) as error:
            detail = getattr(error, 'output', b'') or b''
            raise SystemExit('git ناموفق بود (%s): %s' % (' '.join(args[:2]), detail.decode('utf-8', 'replace').strip() or error))
        return [item for item in out.decode('utf-8').split('\0') if item]

    # --no-renames: جابه‌جایی فایل به‌صورت «حذف + افزودن» دیده می‌شود تا مسیر قدیمی جا نماند.
    changed = set(git('diff', '--name-only', '--no-renames', '--diff-filter=ACM', '-z', base, '--', 'beryon-wp'))
    changed |= set(git('ls-files', '--others', '--exclude-standard', '-z', '--', 'beryon-wp'))
    deleted = set(git('diff', '--name-only', '--no-renames', '--diff-filter=D', '-z', base, '--', 'beryon-wp'))

    def scoped(names, must_exist):
        result = []
        for name in sorted(names):
            if not name.startswith(prefix):
                continue
            relative = name[len(prefix):]
            if must_exist and not os.path.isfile(os.path.join(THEME, relative)):
                continue
            if relative.startswith('public/patches/') or relative.startswith('storage/'):
                continue
            if not allowed_slug(relative) or not allowed_ext(relative):
                print('   رد شد (بیرون از محدوده مجاز Patch): %s' % relative)
                continue
            result.append(relative)
        return result

    return scoped(changed, True), scoped(deleted - changed, False)


def theme_version():
    with io.open(os.path.join(THEME, 'style.css'), encoding='utf-8') as handle:
        for line in handle:
            if line.startswith('Version:'):
                return line.split(':', 1)[1].strip()
    return '0.0.0'


def build(version, since, label, notes, from_version='1.0.0', base=None):
    deleted = []

    if base:
        paths, deleted = collect_git(base)
    else:
        stamp = time.mktime(time.strptime(since, '%Y-%m-%d %H:%M'))
        paths = collect(stamp)

    if not paths and not deleted:
        raise SystemExit('هیچ فایل تغییرکرده‌ای پیدا نشد.')

    files = []
    for relative in paths:
        absolute = os.path.join(THEME, relative)
        files.append({
            'path': relative,
            'from': 'files/' + relative,
            'op': 'copy',
            'sha256': sha256_of(absolute),
            'size': os.path.getsize(absolute),
            'mode': '0644',
        })

    # فایل‌هایی که نسبت به نسخه پایه حذف شده‌اند (فقط در حالت --base قابل تشخیص است)
    for relative in deleted:
        files.append({'path': relative, 'op': 'delete'})

    manifest = {
        'format': 1,
        'theme': 'beryon',
        'id': 'beryon-patch-%s-%s' % (version, time.strftime('%Y%m%d%H%M%S', time.gmtime())),
        'version': version,
        'from_version': from_version,
        'channel': 'stable',
        'label': label,
        'notes': notes,
        'author': 'Beryon Web Team (codex · themos · wooger · securx)',
        'released_at': time.strftime('%Y-%m-%dT%H:%M:%S+00:00', time.gmtime()),
        'requires': {
            'php': '7.4',
            'wordpress': '6.4',
            # بسته تفاضلی (--base) فقط فایل‌های تغییرکرده نسبت به همان نسخه را دارد
            'theme_min': from_version if base else '1.0.0',
        },
        'files': files,
        'post_apply': ['flush_rewrites', 'clean_caches', 'refingerprint'],
    }

    payload = json.dumps(manifest, ensure_ascii=False, indent=2)

    readme = '\n'.join([
        'Beryon Theme Patch Package',
        '============================',
        'id       : ' + manifest['id'],
        'version  : ' + manifest['version'],
        'label    : ' + manifest['label'],
        'channel  : ' + manifest['channel'],
        'released : ' + manifest['released_at'],
        'requires : PHP %s / WordPress %s / theme >= %s' % (
            manifest['requires']['php'], manifest['requires']['wordpress'], manifest['requires']['theme_min']),
        'files    : %d' % len(files),
        '',
        'نصب: پیشخوان وردپرس ← بریون ← به‌روزرسانی (Patch) ← بارگذاری بسته',
        'یا: wp beryon patch apply %s' % os.path.basename(OUT),
        '',
        'یادداشت‌ها:',
        notes,
        '',
    ])

    with zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED) as archive:
        archive.writestr('patch.json', payload)
        archive.writestr('README.txt', readme)
        for entry in files:
            if entry['op'] == 'copy':
                archive.write(os.path.join(THEME, entry['path']), 'files/' + entry['path'])

    return manifest, payload


RELEASES = {
    '1.0.4': {
        'from': '1.0.3',
        # کامیت نسخه ۱.۰.۳ روی main؛ فایل‌های بسته = تفاوت پوشه کاری با همین کامیت
        'base': 'f88f98364c212e853899629ee4e7d7c302037a3a',
        'label': '۱.۰.۴ — رفع ایرادهای بازبینی: مسیریابی نماها، داده نمونه، Patch، پنل تنظیمات',
        'notes': [
            '۱) MiniBlade/Template: نماها پیش از نخستین رندر پیدا نمی‌شدند و همه نشانی‌ها فهرست وبلاگ (index) را نشان می‌دادند. نماهای تازه page و singular برای برگه‌های بدون نمای اختصاصی.',
            '۲) Seeder: خطای مرگبار هنگام ساخت منوها، برگه‌های تکراری در هر اجرا، تاریخ ۱۹۹۹ نوشته‌های نمونه، درون‌ریزی رسانه (GLOB_BRACE و جابه‌جا شدن فایل تصویر قالب)، نام لاتین برچسب محصولات.',
            '۳) Updater/PatchApplier: خطای مرگبار پس از کپی فایل‌ها (ثبت نشدن نسخه)، رد شدن همه بسته‌های امضاشده، خوانده نشدن signature.txt، دکمه‌های «بازگردانی» و «بازسازی اثر انگشت»، حداقل PHP بسته‌های ساخته‌شده (۸.۱ → ۷.۴).',
            '۴) SettingsPage: ذخیره یک زبانه مقدار زبانه‌های دیگر را خالی می‌کرد؛ بازنشانی، تکرارشونده‌های زبانه «صفحات»، چک‌باکس‌ها، پاک‌کردن تصویر انتخابی و زبانه «پیشرفته».',
            '۵) functions.php: خطای مرگبار پیام خوش‌آمد در نخستین بارگذاری پیشخوان (PHP 8).',
            '۶) Seo: نشانی سایت‌مپ به صفحه اصلی ریدایرکت می‌شد؛ الگوی قاعده بازنویسی اصلاح شد. توضیح متا برای نوشته رمزدار.',
            '۷) Forms: فرم نمایندگی با «انواع فروش» چندگزینه‌ای خطای ۴۰۰ می‌داد.',
            '۸) Assets/helpers/نماها: نشانی تصویر کارت‌های مجله، تصویر شاخص، لوگوی انتخابی، صفحه‌بندی، کارت دسته‌ها در ۴۰۴، برچسب فارسی محصولات، نوشته رمزدار، کش «آخرین نوشته‌ها».',
            '۹) Jalali/Submissions: ساعت در تاریخ‌های پنل و اخطار wpdb::prepare در صندوق پیام‌ها.',
            '۱۰) نسخه قالب: 1.0.4 (style.css).',
            'نصب روی ۱.۰.۳: درخواست بارگذاری با صفحه «خطای مهم» تمام می‌شود (خطای خودِ ۱.۰.۳ پس از جایگزینی فایل‌ها)؛ صفحه را دوباره باز کنید و «به‌روزرسانی ← بازسازی اثر انگشت» را بزنید. اگر کلید امضا تنظیم است، پیش از بارگذاری خالی‌اش کنید.',
            'اگر پیش‌تر داده نمونه درج شده: «ابزارها ← اجرای دوباره داده‌های نمونه» منوها را می‌سازد و تاریخ نوشته‌ها و نام برچسب‌ها را ترمیم می‌کند.',
        ],
    },
    '1.0.3': {
        'from': '1.0.0',
        'label': '۱.۰.۳ — رفع بی‌استایلی سایت: پایه URL قطعی دارایی‌ها + نسخه پویا',
        'notes': [
            '۱) Assets.php: پایه URL دارایی‌ها مستقیم از trailingslashit(get_template_directory_uri()) ساخته می‌شود؛ روی سایت زنده URLها اسلش جاافتاده داشتند (themes/beryon-wppublic/…) و ۴۰۴ خاموش = سایت بی‌استایل.',
            '۲) functions.php: BERYON_VERSION دیگر سخت‌کد نیست؛ از سربرگ style.css خوانده می‌شود.',
            '۳) Updater.php: sync_version_option در admin_init گزینه نسخه را خودکار با فایل‌ها همگام می‌کند.',
            '۴) نسخه قالب: 1.0.3 (style.css).',
            'توصیه: پس از این به‌روزرسانی یک‌بار حافظه نهان را پاک کنید (ابزارها → پاکسازی).',
        ],
    },
    '1.0.2': {
        'from': '1.0.1',
        'label': '۱.۰.۲ — رفع خطای Parse صفحه اضطراری و Warningهای پنل تنظیمات',
        'notes': [
            '۱) Template.php: رشته شکسته در صفحه اضطراری («<meta charset="utf-8">» بدون escape) که خطای Parse مرگبار می‌ساخت اصلاح شد (گزارش کاربر: unexpected identifier "utf" در Template.php:148).',
            '۲) SettingsPage.php: فیلدهای type=heading کلید name ندارند؛ دسترسی بدون guard باعث Warning «Undefined array key "name"» و ذخیره کلید خالی در options می‌شد — حالا skip می‌شوند.',
            '۳) ابزار جدید tools/php_syntax_check.py: lexer ایستای PHP که شکستن رشته‌ها (گیومه escape‌نشده)، توازن پرانتزها، رشته/کامنت بسته‌نشده و پارامترهای nullable ضمنی (deprecated در PHP 8.4) را می‌گیرد.',
            '۴) Assets.php: بدون تغییر عملکرد؛ فقط هم‌راستاسازی با بررسی جدید.',
            '۵) نسخه قالب: 1.0.2 (style.css).',
        ],
    },
}

OUT = os.path.join(ROOT, 'beryon-patch-1.0.1.zip')


def main():
    argv = sys.argv[1:]
    args = []
    i = 0
    opts = {}
    while i < len(argv):
        a = argv[i]
        if a == '--since' and i + 1 < len(argv):
            opts['since'] = argv[i + 1]
            i += 2
            continue
        if a.startswith('--since='):
            opts['since'] = a.split('=', 1)[1]
            i += 1
            continue
        if a == '--from' and i + 1 < len(argv):
            opts['from'] = argv[i + 1]
            i += 2
            continue
        if a.startswith('--from='):
            opts['from'] = a.split('=', 1)[1]
            i += 1
            continue
        if a == '--base' and i + 1 < len(argv):
            opts['base'] = argv[i + 1]
            i += 2
            continue
        if a.startswith('--base='):
            opts['base'] = a.split('=', 1)[1]
            i += 1
            continue
        if not a.startswith('--'):
            args.append(a)
        i += 1
    version = args[0] if args else '1.0.1'
    since = opts.get('since', '2026-11-03 08:08')
    from_version = opts.get('from', None)

    global OUT
    OUT = os.path.join(ROOT, 'beryon-patch-%s.zip' % version)

    meta = RELEASES.get(version)
    label = meta['label'] if meta else 'به‌روزرسانی قالب'
    notes = '\n'.join([
        '۱) MiniBlade: اصلاح unset($__php) در evaluate() که خروجی همه نماها را خالی می‌کرد (علت صفحه سفید).',
        '۲) MiniBlade: @endphp حالا به ?> کامپایل می‌شود (پیش‌تر <?php تولید می‌کرد = خطای نحوی).',
        '۳) MiniBlade: @endforelse به endif; اصلاح شد و @forelse فرم استاندارد «as» را می‌فهمد.',
        '۴) MiniBlade: حافظه نهان فایلی به‌جای eval، ثبت خطا در error_log و حذف بازگشت رشته خالی.',
        '۵) Assets: حذف wp_style_add_data(rtl, replace) که app-rtl.css ناموجود را جایگزین app.css می‌کرد.',
        '۶) Assets: بررسی وجود فایل در manifest + fallback استایل/اسکریپت درون‌خطی (سایت هرگز بی‌استایل نمی‌ماند).',
        '۷) پنل: افزودن قواعد CSS کارت‌های آمار و نوار فیلتر صندوق پیام‌ها.',
        '۸) SettingsSchema: رفع بازگشت declared() که تب «پیشرفته» همه کلیدها را تکرار می‌کرد.',
        '۹) Template: صفحه اضطراری معتبر HTML به‌جای صفحه سفید + functions.php: نگهبان خطای مرگبار.',
        '۱۰) سازگاری PHP 7.4: polyfill برای str_contains/str_starts_with/str_ends_with و حذف match().',
        '۱۱) نماها: اصلاح فرار HTML (صفحه‌بندی مجله، R&D در چارت سازمانی) و پیشوند \\ برای نام کلاس‌ها.',
        '۱۲) Security: دستور CSP «upgrade-insecure-requests» فقط روی HTTPS.',
    ])

    if meta:
        notes = '\n'.join(meta['notes'])
        if from_version is None:
            from_version = meta.get('from')

    # --since صریح یعنی انتخاب بر پایه mtime؛ در غیر این صورت --base یا «base» مدخل نسخه
    base = opts.get('base')
    if base is None and 'since' not in opts and meta:
        base = meta.get('base')

    manifest, payload = build(version, since, label, notes, from_version or '1.0.0', base)

    print('روش انتخاب فایل‌ها: %s' % ('git diff نسبت به ' + base if base else 'mtime از ' + since))

    print('بسته Patch ساخته شد: %s' % OUT)
    print('  نسخه      : %s ← %s' % (manifest['from_version'], manifest['version']))
    print('  فایل‌ها    : %d' % len(manifest['files']))
    print('  حجم       : %d بایت' % os.path.getsize(OUT))
    print('  sha256 بسته: %s' % sha256_of(OUT))
    print('  نسخه قالب  : %s' % theme_version())
    print('')
    print('  فهرست فایل‌ها:')
    for entry in manifest['files']:
        if entry['op'] == 'copy':
            print('    %-58s %8d B' % (entry['path'], entry['size']))
        else:
            print('    %-58s %8s' % (entry['path'], entry['op']))

    # خودآزمایی: مانیفست باید با قواعد PatchApplier سازگار باشد
    problems = []
    if manifest['format'] != 1:
        problems.append('format')
    if manifest['theme'] != 'beryon':
        problems.append('theme')
    for entry in manifest['files']:
        if not allowed_slug(entry['path']) or not allowed_ext(entry['path']):
            problems.append('scope: ' + entry['path'])
        if entry['op'] != 'copy':
            continue
        if not entry['from'].startswith('files/'):
            problems.append('from: ' + entry['path'])
        if len(entry['sha256']) != 64:
            problems.append('sha256: ' + entry['path'])
        if entry['size'] > 15728640:
            problems.append('size: ' + entry['path'])
    print('')
    print('  خودآزمایی مانیفست: %s' % ('✔ معتبر' if not problems else '✗ ' + ', '.join(problems)))
    return 1 if problems else 0


if __name__ == '__main__':
    sys.exit(main())
