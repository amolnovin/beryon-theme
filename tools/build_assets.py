#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
بیلد دارایی‌های قالب بریون (جایگزین آفلاین `npm run build`)
==============================================================
ورودی‌ها از resources/{css,js} و خروجی‌ها در public/assets/ با «نام ثابت»
(بدون hash) نوشته می‌شوند؛ نسخه‌گذاری کش در app/Assets.php با filemtime()
انجام می‌شود، بنابراین نام ثابت بودن مشکلی ایجاد نمی‌کند و قالب حتی بدون
اجرای npm هم با فایل‌های از پیش بیلدشده کار می‌کند.

کارها:
  CSS : درون‌سازی @import‌های محلی + حذف توضیحات + فشرده‌سازی امن
        (بدون شکستن url()ها، رشته‌ها، data URIها و قواعد @media)
  JS  : حذف «import './x.js'» (main.js پیش از app.js الحاق می‌شود) و
        حذف توضیحات — بدون هیچ تغییر در منطق
  +   : ساخت public/assets/manifest.json سازگار با app/Assets.php::manifest()
  +   : کپی فونت‌ها و تصاویر به public/assets

اجرا:  python3 tools/build_assets.py
نکته: اگر در محیط میزبان `npm install && npm run build` اجرا شود، Vite
خروجی معادل (با همان نام‌ها) تولید می‌کند و این اسکریپت لازم نیست.
"""
import json
import os
import re
import shutil
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
THEME = os.path.join(ROOT, 'beryon-wp')
RES = os.path.join(THEME, 'resources')
OUT = os.path.join(THEME, 'public/assets')

# ----------------------------------------------------------------- ابزار CSS


def strip_css_comments(css):
    """حذف توضیحات بدون دست‌زدن به رشته‌ها."""
    out = []
    i = 0
    n = len(css)
    while i < n:
        ch = css[i]
        if ch in ('"', "'"):
            quote = ch
            j = i + 1
            while j < n:
                if css[j] == '\\':
                    j += 2
                    continue
                if css[j] == quote:
                    j += 1
                    break
                j += 1
            out.append(css[i:j])
            i = j
        elif css.startswith('/*', i):
            j = css.find('*/', i + 2)
            i = n if j < 0 else j + 2
        else:
            out.append(ch)
            i += 1
    return ''.join(out)


def inline_css_imports(css, base_dir, seen=None):
    """جایگزینی @import './file.css' با محتوای فایل (بازگشتی)."""
    seen = seen or set()

    def repl(match):
        rel = match.group(1) or match.group(2)
        path = os.path.normpath(os.path.join(base_dir, rel))
        if path in seen or not os.path.isfile(path):
            print('  ! import یافت نشد: %s' % rel)
            return ''
        seen.add(path)
        inner = open(path, encoding='utf-8').read()
        inner = inline_css_imports(inner, os.path.dirname(path), seen)
        return '\n/* --- %s --- */\n' % os.path.basename(path) + inner

    pattern = r"@import\s+(?:url\()?\s*['\"]([^'\"]+)['\"]\s*\)?[^;]*;"
    return re.sub(pattern, repl, css)


MATH_FUNCTION = re.compile(r'(?<![\w-])(?:calc|clamp|min|max)\(')


def protect_math_functions(css, keep):
    """
    عبارت‌های calc() / clamp() / min() / max() را پیش از فشرده‌سازی کنار می‌گذارد.

    در این توابع فاصله دو طرف «+» و «-» اجباری است و صفرِ واحددار (0px) را
    نمی‌توان بی‌واحد کرد. فشرده‌ساز پیشین «calc(var(--header-h) + 46px)» را به
    «calc(var(--header-h)+46px)» تبدیل می‌کرد که نامعتبر است؛ مرورگر آن اعلان را
    کنار می‌گذاشت و padding بالای هیروی صفحه اصلی و top نوار کناری چسبان صفر/auto
    می‌شد. فقط فاصله‌های زائدِ کنار پرانتز و ویرگول برداشته می‌شود.
    """
    out = []
    pos = 0

    while True:
        match = MATH_FUNCTION.search(css, pos)
        if not match:
            out.append(css[pos:])
            break

        out.append(css[pos:match.start()])
        depth = 0
        end = match.end() - 1

        while end < len(css):
            if css[end] == '(':
                depth += 1
            elif css[end] == ')':
                depth -= 1
                if depth == 0:
                    break
            end += 1

        if depth != 0:  # پرانتز بسته نشده: ادامه متن دست‌نخورده می‌ماند
            out.append(css[match.start():])
            break

        expr = css[match.start():end + 1]
        expr = re.sub(r'\(\s+', '(', expr)
        expr = re.sub(r'\s+\)', ')', expr)
        expr = re.sub(r'\s*,\s*', ',', expr)
        out.append(keep(expr))
        pos = end + 1

    return ''.join(out)


def minify_css(css):
    """
    فشرده‌سازی محافظه‌کار CSS.

    رشته‌ها، محتوای url() و عبارت‌های ریاضی (calc و …) پیش از هر تغییری
    «بیرون کشیده» و با نشانه جایگزین می‌شوند تا data URIها، مسیر فونت‌ها،
    مقادیر داخل کوتیشن و فاصله‌های اجباری calc() دست‌نخورده بمانند؛ سپس
    فشرده‌سازی انجام و در پایان بازگردانده می‌شود.
    """
    vault = {}

    def keep(value):
        token = '\x00%d\x00' % len(vault)
        vault[token] = value
        return token

    def stash(match):
        return keep(match.group(0))

    # ۱) محافظت از url(...) و رشته‌ها
    css = re.sub(r'url\(\s*[^)]*?\s*\)', stash, css)
    css = re.sub(r"\"[^\"\n]*\"|'(?:\\.|[^'\n])*'", stash, css)

    # ۲) حذف توضیحات و فشرده‌سازی
    css = strip_css_comments(css)
    css = re.sub(r'\s+', ' ', css)
    css = protect_math_functions(css, keep)
    css = re.sub(r'\s*([{}:;,>~+])\s*', r'\1', css)
    css = re.sub(r'(?<![\w.])(0)(?:\.0+)?(px|em|rem|%|cm|mm|in|pt|pc|vh|vw|vmin|vmax|deg|rad|turn)\b', r'\1', css)
    css = re.sub(r'(?<![\w.])\.0+(?=[^\d])', '0', css)
    # «0 0 0 0» فقط وقتی کلِ مقدار یک ویژگی است کوتاه می‌شود (margin:0 0 0 0 ← margin:0).
    # الگوی پیشین درون پرانتز را هم کوتاه می‌کرد و clip:rect(0 0 0 0) را به مقدار
    # نامعتبر clip:rect(0) تبدیل می‌کرد (مرورگر آن اعلان را دور می‌ریزد).
    css = re.sub(r'(?<=:)0 0 0 0(?=[;}!])', '0', css)
    css = css.replace(';}', '}')
    css = css.strip()

    # ۳) بازگردانی مقادیر محافظت‌شده (پس از همه تغییرات تا data URI و
    #    url()های فونت دست‌نخورده بمانند)
    #    به ترتیب وارونه: عبارت ریاضی ممکن است خودش نشانه url()/رشته داشته باشد.
    for token, value in reversed(list(vault.items())):
        css = css.replace(token, value)

    return css


# ------------------------------------------------------------------ ابزار JS


def strip_js_comments(js):
    """حذف توضیحات // و /* */ بدون دست‌زدن به رشته‌ها و regex."""
    out = []
    i = 0
    n = len(js)
    prev_significant = ''
    while i < n:
        ch = js[i]
        if ch in ('"', "'", '`'):
            quote = ch
            j = i + 1
            while j < n:
                if js[j] == '\\':
                    j += 2
                    continue
                if js[j] == quote:
                    j += 1
                    break
                j += 1
            out.append(js[i:j])
            prev_significant = quote
            i = j
        elif ch == '/' and i + 1 < n and js[i + 1] == '/' and not _regex_context(prev_significant):
            j = js.find('\n', i)
            i = n if j < 0 else j
        elif ch == '/' and i + 1 < n and js[i + 1] == '*':
            j = js.find('*/', i + 2)
            i = n if j < 0 else j + 2
        else:
            out.append(ch)
            if not ch.isspace():
                prev_significant = ch
            i += 1
    return ''.join(out)


def _regex_context(prev):
    """آیا «/» می‌تواند آغاز regex باشد؟ (برای پرهیز از حذف اشتباه)"""
    return prev in ('', '(', ',', '=', ':', '[', '!', '&', '|', '?', '{', '}', ';', '+', '-', '*', '%', '<', '>', '~', '^')


def bundle_js(entries):
    """الحاق ورودی‌ها و حذف «import './file.js'» (به‌جای آن فایل الحاق می‌شود)."""
    chunks = []
    for entry in entries:
        path = os.path.join(RES, entry)
        code = open(path, encoding='utf-8').read()

        def repl(match):
            rel = match.group(1)
            sub = os.path.normpath(os.path.join(os.path.dirname(path), rel))
            if not os.path.isfile(sub):
                print('  ! import JS یافت نشد: %s' % rel)
                return ''
            inner = open(sub, encoding='utf-8').read()
            return '\n/* --- %s --- */\n%s\n' % (os.path.basename(sub), strip_js_comments(inner))

        code = re.sub(r"^\s*import\s+['\"]([^'\"]+)['\"]\s*;?\s*$", repl, code, flags=re.M)
        code = re.sub(r"^\s*import\s+[^'\"]*?from\s+['\"]([^'\"]+)['\"]\s*;?\s*$", repl, code, flags=re.M)
        chunks.append('/* --- %s --- */\n%s' % (os.path.basename(path), strip_js_comments(code)))

    return '\n'.join(chunks).strip() + '\n'


# ---------------------------------------------------------------------- اجرا


def write(path, content):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as handle:
        handle.write(content)
    return len(content.encode('utf-8'))


def copy_tree(src, dst, patterns):
    copied = 0
    if not os.path.isdir(src):
        return 0
    for name in sorted(os.listdir(src)):
        full = os.path.join(src, name)
        if os.path.isdir(full):
            copied += copy_tree(full, os.path.join(dst, name), patterns)
            continue
        if any(re.search(p, name) for p in patterns):
            os.makedirs(dst, exist_ok=True)
            shutil.copy2(full, os.path.join(dst, name))
            copied += 1
    return copied


def main():
    verbose = '-v' in sys.argv
    manifest = {}

    # ---- CSS سایت
    css_source = open(os.path.join(RES, 'css/app.css'), encoding='utf-8').read()
    css_inlined = inline_css_imports(css_source, os.path.join(RES, 'css'))
    css_out = minify_css(css_inlined)
    size = write(os.path.join(OUT, 'css/app.css'), css_out)
    manifest['resources/css/app.css'] = {'file': 'css/app.css', 'src': 'resources/css/app.css', 'isEntry': True}
    print('CSS app.css      : %6d B  (خام %d B، کاهش %.0f%%)'
          % (size, len(css_inlined.encode('utf-8')), 100 * (1 - size / max(1, len(css_inlined.encode('utf-8'))))))

    # ---- CSS فونت (بدون فشرده‌سازی تهاجمی؛ @font-face حساس است)
    fonts = open(os.path.join(RES, 'css/fonts.css'), encoding='utf-8').read()
    fonts_out = re.sub(r'\s+', ' ', strip_css_comments(fonts)).replace('{ ', '{').replace(' }', '}').strip()
    size = write(os.path.join(OUT, 'css/fonts.css'), fonts_out)
    manifest['resources/css/fonts.css'] = {'file': 'css/fonts.css', 'src': 'resources/css/fonts.css', 'isEntry': True}
    print('CSS fonts.css    : %6d B' % size)

    # ---- CSS پنل مدیریت
    admin_css = open(os.path.join(RES, 'css/admin.css'), encoding='utf-8').read()
    admin_out = minify_css(admin_css)
    size = write(os.path.join(OUT, 'css/admin.css'), admin_out)
    manifest['resources/css/admin.css'] = {'file': 'css/admin.css', 'src': 'resources/css/admin.css', 'isEntry': True}
    print('CSS admin.css    : %6d B' % size)

    # ---- CSS ویرایشگر بلوکی (سبک‌های پایه نویسنده)
    editor_css = open(os.path.join(RES, 'css/editor.css'), encoding='utf-8').read() if os.path.isfile(os.path.join(RES, 'css/editor.css')) else ''
    if editor_css:
        size = write(os.path.join(OUT, 'css/editor.css'), minify_css(editor_css))
        manifest['resources/css/editor.css'] = {'file': 'css/editor.css', 'src': 'resources/css/editor.css', 'isEntry': True}
        print('CSS editor.css   : %6d B' % size)

    # ---- JS سایت (main.js داخل app.js درون‌سازی می‌شود)
    js_out = bundle_js(['js/app.js'])
    size = write(os.path.join(OUT, 'js/app.js'), js_out)
    manifest['resources/js/app.js'] = {'file': 'js/app.js', 'src': 'resources/js/app.js', 'isEntry': True}
    print('JS  app.js       : %6d B' % size)

    # ---- JS پنل
    admin_js = bundle_js(['js/admin.js'])
    size = write(os.path.join(OUT, 'js/admin.js'), admin_js)
    manifest['resources/js/admin.js'] = {'file': 'js/admin.js', 'src': 'resources/js/admin.js', 'isEntry': True}
    print('JS  admin.js     : %6d B' % size)

    # ---- فونت‌ها و تصاویر
    fonts_count = copy_tree(os.path.join(RES, 'fonts'), os.path.join(OUT, 'fonts'), [r'\.woff2?$'])
    images_count = copy_tree(os.path.join(RES, 'images'), os.path.join(OUT, 'images'), [r'\.(webp|png|jpe?g|svg|gif|avif)$'])
    print('فونت‌ها: %d فایل | تصاویر: %d فایل' % (fonts_count, images_count))

    # ---- manifest
    write(os.path.join(OUT, 'manifest.json'), json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')
    print('manifest.json    : %d ورودی' % len(manifest))

    # ---- اعتبارسنجی ساده خروجی‌ها
    problems = []
    for rel in ('css/app.css', 'css/admin.css', 'css/fonts.css', 'js/app.js', 'js/admin.js'):
        path = os.path.join(OUT, rel)
        if not os.path.isfile(path) or os.path.getsize(path) < 200:
            problems.append(rel + ' خالی یا ناموجود است')
        text = open(path, encoding='utf-8').read()
        if rel.endswith('.css'):
            if text.count('{') != text.count('}'):
                problems.append('%s: نابرابری { و }' % rel)
            if '@import' in text:
                problems.append('%s: @import درون‌سازی نشده' % rel)
        else:
            if re.search(r"^\s*import\s+['\"]", text, re.M):
                problems.append('%s: import باقی‌مانده' % rel)

    if problems:
        print('\nخطاها:')
        for item in problems:
            print(' -', item)
        return 1

    if verbose:
        print('\nخروجی‌ها در: %s' % OUT)
    print('\nبیلد دارایی‌ها موفق ✔')
    return 0


if __name__ == '__main__':
    sys.exit(main())
