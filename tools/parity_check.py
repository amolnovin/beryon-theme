#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
هارنس بررسی هم‌ارزی (Parity QA) قالب وردپرس بریون
====================================================
سه لایه بررسی:

 ۱) پوشش نماها: هر صفحه استاتیک _src/pages/*.html باید نمای Blade
    متناظر داشته باشد (نگاشت صریح در VIEW_MAP).
 ۲) پاکیزگی: در نماها نباید «href="*.html"»، «src="assets/» یا متن
    placeholder باقی مانده باشد (URLها باید از توابع قالب بیایند).
 ۳) ساختار: مجموعه کلاس‌های CSS و هوک‌های JS هر صفحه استاتیک باید در
    نمای Blade هم وجود داشته باشند (مارک‌آپ یکسان، فقط داده پویا).
    برای بخش‌هایی که عمداً حلقه شده‌اند (کارت‌ها) فقط کلاس والد بررسی
    می‌شود.

اجرا: python3 tools/parity_check.py            (گزارش کامل)
      python3 tools/parity_check.py --strict   (خطا → کد خروج ۱)
"""
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PAGES = os.path.join(ROOT, '_src/pages')
VIEWS = os.path.join(ROOT, 'beryon-wp/resources/views')

VIEW_MAP = {
    'index.html': 'front-page.blade.php',
    'products.html': 'archive-product.blade.php',
    'cat-sausage.html': 'taxonomy-product_cat.blade.php',
    'cat-kalbas.html': 'taxonomy-product_cat.blade.php',
    'cat-jambon.html': 'taxonomy-product_cat.blade.php',
    'cat-frozen.html': 'taxonomy-product_cat.blade.php',
    'cat-plant.html': 'taxonomy-product_cat.blade.php',
    'product-single.html': 'single-product.blade.php',
    'about.html': 'page-about.blade.php',
    'ceo.html': 'page-ceo.blade.php',
    'org-chart.html': 'page-org-chart.blade.php',
    'certificates.html': 'page-certificates.blade.php',
    'contact.html': 'page-contact-us.blade.php',
    'feedback.html': 'page-feedback.blade.php',
    'agency.html': 'page-agency.blade.php',
    'catalog.html': 'page-catalog.blade.php',
    'blog.html': 'home.blade.php',
    'blog-single.html': 'single.blade.php',
}

EXTRA_VIEWS = [
    'index.blade.php',          # نسخه پشتیبان صفحه وبلاگ
    'archive.blade.php',        # بایگانی دسته/برچسب/تاریخ
    'search.blade.php',         # نتایج جست‌وجو
    '404.blade.php',            # خطای ۴۰۴
    'page.blade.php',           # برگه عمومی (برگه‌های بدون نمای اختصاصی)
    'singular.blade.php',       # نوع محتوای سفارشی بدون نمای اختصاصی
    'partials/entry.blade.php', # بدنه مشترک page/singular
    'partials/blog-list.blade.php',
    'woocommerce/cart.blade.php',
    'woocommerce/checkout.blade.php',
    'woocommerce/myaccount/my-account.blade.php',
    'woocommerce/single-product/spec-table.blade.php',
]

# هوک‌های JS که باید در نماها زنده بمانند (app/…/main.js به آن‌ها وابسته است)
JS_HOOKS = {
    'index.html': ['reveal', 'data-count'],
    'products.html': ['data-filter-wrap', 'data-search', 'data-count-label', 'no-result'],
    'cat-sausage.html': ['data-filter-wrap', 'no-result'],
    'product-single.html': ['ps-main', 'data-img', 'data-lightbox', 'data-tabs', 'tab-panel'],
    'feedback.html': ['acc-item', 'data-form'],
    'contact.html': ['data-form', 'form-success'],
    'agency.html': ['data-form', 'radio-pills', 'check-line'],
    'blog.html': ['data-form', 'blog-card'],
}


def classes_of(html):
    """مجموعه کلاس‌های CSS یک سند."""
    found = set()
    for value in re.findall(r'class="([^"]*)"', html):
        for token in value.split():
            if re.match(r'^[A-Za-z][A-Za-z0-9_\-]*$', token):
                found.add(token)
    return found


def resolve_includes(view_path, text, depth=0):
    """متن نما + متن جزئها/کامپوننت‌هایی که @include می‌کند (تا ۳ سطح)."""
    if depth > 3:
        return text

    for dotted in set(re.findall(r"@include\(\s*'([a-zA-Z0-9_.\-/]+)'", text)):
        candidate = os.path.join(VIEWS, dotted.replace('.', '/') + '.blade.php')
        if os.path.isfile(candidate):
            text += '\n' + open(candidate, encoding='utf-8').read()

    return text


def strip_blade(text):
    """
    خنثی‌سازی دستورهای Blade برای مقایسه ساختار.

    نکته: عبارت‌های {{ … }} که «داخل» مقدار class هستند (مثل
    class="chip{{ $index === 0 ? ' active' : '' }}") باید به یک کلاس
    ساختگی تبدیل شوند، وگرنه کلاس پایه (chip) از مقایسه حذف می‌شود.
    """
    text = re.sub(r'\{\{--.*?--\}\}', ' ', text, flags=re.S)
    # عبارت Blade داخل class="…" → کلاس ساختگی
    text = re.sub(r'class="([^"]*)"\s*',
                  lambda m: 'class="' + re.sub(r'\{\{.*?\}\}', ' bexpr', m.group(1), flags=re.S) + '" ',
                  text, flags=re.S)
    text = re.sub(r'\{!!.*?!!\}', ' ', text)
    text = re.sub(r'\{\{.*?\}\}', ' ', text, flags=re.S)
    text = re.sub(r'@\w+(\([^)]*\))?', ' ', text)
    return text


def main():
    strict = '--strict' in sys.argv
    errors = []
    warnings = []
    print('=' * 74)
    print('بررسی هم‌ارزی قالب وردپرس بریون با ۱۸ صفحه HTML نهایی')
    print('=' * 74)

    # --- وجود فایل صفحه‌ها
    missing_pages = [name for name in VIEW_MAP if not os.path.isfile(os.path.join(PAGES, name))]
    if missing_pages:
        errors.append('صفحه استاتیک مفقود: ' + ', '.join(missing_pages))

    # --- وجود نماها
    for name, view in sorted(VIEW_MAP.items()):
        path = os.path.join(VIEWS, view)
        if not os.path.isfile(path):
            errors.append('نمای Blade مفقود برای %s → %s' % (name, view))
    for view in EXTRA_VIEWS:
        if not os.path.isfile(os.path.join(VIEWS, view)):
            errors.append('نمای Blade مفقود: ' + view)

    # --- پاکیزگی همه نماها
    all_views = []
    for base, _dirs, files in os.walk(VIEWS):
        for file in files:
            if file.endswith('.blade.php'):
                all_views.append(os.path.join(base, file))

    for path in sorted(all_views):
        text = open(path, encoding='utf-8').read()
        rel = os.path.relpath(path, VIEWS)

        for pattern, message in [
            (r'href="[^"]*\.html"', 'لینک استاتیک .html باقی مانده'),
            (r'src="assets/', 'مسیر استاتیک assets/ باقی مانده'),
            (r'\{\{[A-Z_]+\}\}', 'توکِن قالب HTML جایگزین نشده'),
            (r'lorem|TODO|FIXME|XXX', 'متن placeholder'),
        ]:
            hits = re.findall(pattern, text)
            if hits:
                errors.append('%s: %s → %s' % (rel, message, ', '.join(sorted(set(hits))[:3])))

    # --- ساختار و هوک‌های JS صفحه‌به‌صفحه
    for name, view in sorted(VIEW_MAP.items()):
        src_path = os.path.join(PAGES, name)
        dst_path = os.path.join(VIEWS, view)
        if not (os.path.isfile(src_path) and os.path.isfile(dst_path)):
            continue

        src = open(src_path, encoding='utf-8').read()
        dst_raw = open(dst_path, encoding='utf-8').read()
        dst_raw = resolve_includes(dst_path, dst_raw)
        dst = strip_blade(dst_raw)

        src_classes = classes_of(src)
        dst_classes = classes_of(dst)
        missing = sorted(src_classes - dst_classes)

        # کلاس‌هایی که در قالب HTML از partialهای مشترک (header/footer) می‌آیند
        # یا توسط JS در زمان اجرا افزوده می‌شوند، از مقایسه مستثنی هستند.
        allowed_missing = {
            # در لایه layouts/app.blade.php به‌صورت <main id="main"> آمده است
            'main',
            # کلاس‌های تأخیر انیمیشن به‌صورت شرطی در Blade تولید می‌شوند
            'd1', 'd2', 'd3',
            # اجزای مشترک (header/footer) یا کلاس‌هایی که JS در زمان اجرا می‌افزاید
            'header', 'topbar', 'nav', 'burger', 'mobile-menu', 'overlay', 'panel',
            'footer', 'to-top', 'lightbox', 'is-stuck', 'show', 'open', 'active',
            'invalid', 'is-loaded', 'is-copied', 'form-note',
        }
        real_missing = [token for token in missing if token not in allowed_missing]

        # <main id="main"> باید در لایه وجود داشته باشد
        layout = open(os.path.join(VIEWS, 'layouts/app.blade.php'), encoding='utf-8').read()
        if 'main' in missing and 'id="main"' not in layout:
            real_missing.append('main (id="main" در layouts/app.blade.php نیست)')

        status = 'OK ' if not real_missing else 'ERR'
        print('%s %-20s → %-32s کلاس %d/%d%s' % (
            status, name, view, len(src_classes & dst_classes), len(src_classes),
            '' if not real_missing else ' | مفقود: ' + ', '.join(real_missing[:6])))

        if real_missing:
            errors.append('%s: کلاس‌های مفقود %s' % (name, ', '.join(real_missing)))

        for hook in JS_HOOKS.get(name, []):
            if hook not in dst:
                # ممکن است در partial/component باشد
                found_elsewhere = any(
                    hook in open(os.path.join(VIEWS, other), encoding='utf-8').read()
                    for other in ('components/product-card.blade.php', 'components/cat-card.blade.php',
                                  'components/products-grid.blade.php', 'components/cats-grid.blade.php',
                                  'partials/blog-list.blade.php', 'partials/lightbox.blade.php')
                    if os.path.isfile(os.path.join(VIEWS, other))
                )
                if not found_elsewhere:
                    errors.append('%s: هوک JS مفقود → %s' % (name, hook))

    # --- لایه‌ها و جزئی‌ها
    for partial in ('layouts/app.blade.php', 'partials/head.blade.php', 'partials/header.blade.php',
                    'partials/footer.blade.php', 'partials/to-top.blade.php', 'partials/lightbox.blade.php'):
        if not os.path.isfile(os.path.join(VIEWS, partial)):
            errors.append('لایه/جزء مفقود: ' + partial)

    # --- دارایی‌های بیلدشده
    assets = os.path.join(ROOT, 'beryon-wp/public/assets')
    for rel in ('css/app.css', 'css/fonts.css', 'css/admin.css', 'css/editor.css',
                'js/app.js', 'js/admin.js', 'manifest.json'):
        path = os.path.join(assets, rel)
        if not os.path.isfile(path) or os.path.getsize(path) < 200:
            errors.append('دارایی بیلدشده مفقود/خالی: ' + rel)

    # --- تصاویر باید webp باشند (به‌جز ceo-photo.png که باید بایت‌به‌بایت بماند)
    images = os.path.join(assets, 'images')
    if os.path.isdir(images):
        for file in sorted(os.listdir(images)):
            if file.endswith(('.jpg', '.jpeg')):
                errors.append('تصویر غیر webp در دارایی‌ها: ' + file)

    print('-' * 74)
    print('نماهای بررسی‌شده: %d | صفحات استاتیک: %d' % (len(all_views), len(VIEW_MAP)))
    if warnings:
        print('\nهشدارها:')
        for item in warnings:
            print(' !', item)
    if errors:
        print('\nخطاها (%d):' % len(errors))
        for item in errors:
            print(' -', item)
        return 1 if strict else 0

    print('\nهمه بررسی‌های هم‌ارزی پاس شد ✔')
    return 0


if __name__ == '__main__':
    sys.exit(main())
