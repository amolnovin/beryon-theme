#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
به‌روزرسانی مستندات و شماره نسخه برای انتشار ۱.۰.۱.

  1) style.css        : Version 1.0.0 → 1.0.1
  2) README.md        : افزودن blade_check.py به جدول QA، خروجی واقعی آخرین اجرا،
                        بخش جدید «تغییرات ۱.۰.۱ و عیب‌یابی صفحه سفید»
  3) docs/PATCH_SPEC.md: اصلاح معنای کلید `from` — در پیاده‌سازی واقعی،
                        `from` مسیر منبع داخل بسته (files/…) است، نه hash فایل
                        فعلی. (مستند پیش‌تر با کد ناسازگار بود.)
"""
import io
import os
import sys

ROOT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'beryon-wp')


def read(path):
    with io.open(path, encoding='utf-8') as handle:
        return handle.read()


def write(path, text):
    tmp = path + '.tmp'
    with io.open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
        handle.write(text)
    os.replace(tmp, path)


def sub(source, old, new, label):
    if source.count(old) == 0:
        if new in source:
            print('   (skip [%s])' % label)
            return source
        raise SystemExit('ANCHOR FAIL [%s]' % label)
    if source.count(old) != 1:
        raise SystemExit('ANCHOR FAIL [%s] count=%d' % (label, source.count(old)))
    return source.replace(old, new)


QA_TABLE_OLD = "| بررسی ارجاع نماها به API قالب | `python3 tools/api_check.py` |\n"
QA_TABLE_NEW = ("| بررسی ارجاع نماها به API قالب | `python3 tools/api_check.py` |\n"
                "| **کامپایل ایستای همه نماهای Blade** | `python3 tools/blade_check.py --strict` |\n"
                "| ساخت بسته Patch | `python3 tools/make_patch.py 1.0.1` |\n")

QA_OUT_OLD = """```
php_lint.py      → بررسی شد: ۱۰۰ فایل | همه بررسی‌ها پاس شد ✔
parity_check.py  → ۱۸/۱۸ صفحه OK | نماهای بررسی‌شده: ۳۶ | همه پاس ✔
api_check.py     → ۱۴۹ فراخوانی نما | همه به API موجود ارجاع می‌دهند ✔
build_assets.py  → app.css ۵۰KB | app.js ۲۰KB | admin.css ۷.۸KB | بیلد موفق ✔
```"""

QA_OUT_NEW = """```
php_lint.py      → بررسی شد: ۱۰۲ فایل | همه بررسی‌ها پاس شد ✔
parity_check.py  → ۱۸/۱۸ صفحه OK | نماهای بررسی‌شده: ۳۶ | همه پاس ✔
api_check.py     → ۳۶ نما | ۱۴۹ فراخوانی | ۴۵ تابع | ۲۴ کلاس ✔
blade_check.py   → ۳۶ نما | خطا: ۰ | هشدار: ۰ (--strict) ✔
build_assets.py  → app.css ۵۰KB | app.js ۲۰KB | admin.css ۹.۸KB | بیلد موفق ✔
```

`blade_check.py` مراحل کامپایل `app/MiniBlade.php` را بازتولید می‌کند و برای هر
نما بررسی می‌کند: توازن `<?php`/`?>`، توازن ساختارهای کنترلی (با تفکیک نحو
جایگزین از آکولادی)، نبود دستور کامپایل‌نشده، نام کلاس نسبی، و چاپ نشدن
HTML/`beryon_amp()` داخل `{{ }}` (که به متن فرار‌شده تبدیل می‌شود)."""

CHANGELOG = """## ۱۲) تغییرات ۱.۰.۱ — رفع صفحه سفید و بی‌استایل بودن پنل

پس از گزارش «صفحه اصلی کاملاً سفید» و «بی‌استایل بودن تنظیمات قالب»، ریشه‌ها
پیدا و اصلاح شد. همه موارد با ابزارهای خودکار (بخش ۹) راستی‌آزمایی شده‌اند.

| # | فایل | ایراد | اصلاح |
|---|---|---|---|
| ۱ | `app/MiniBlade.php` | در `evaluate()` متغیر `$__php` پیش از `eval('?>' . $__php)` با `unset()` پاک می‌شد → **خروجی همه نماها رشته خالی** = صفحه سفید | کد کامپایل‌شده در `$__beryonCode` نگه داشته می‌شود |
| ۲ | `app/MiniBlade.php` | `@endphp` به `<?php ` کامپایل می‌شد → `<?php … <?php` = خطای نحوی | به `?>` اصلاح شد |
| ۳ | `app/MiniBlade.php` | `@endforelse` → `endforeach;` درحالی‌که `@empty` شاخه `else:` می‌سازد → خطای نحوی | به `endif;` اصلاح شد |
| ۴ | `app/MiniBlade.php` | `@forelse ($items as $k => $v)` (فرم استاندارد Blade) به `foreach` ساده تبدیل و `@empty` بی‌`if` رها می‌شد | هر دو فرم `as` و سه‌بخشی پشتیبانی می‌شود؛ متغیر موقت یکتا برای حلقه‌های تودرتو |
| ۵ | `app/MiniBlade.php` | خطاها بلعیده می‌شدند (`return ''` وقتی `WP_DEBUG` خاموش است) | ثبت در `error_log` + نمایش برای مدیر + انتقال به صفحه اضطراری |
| ۶ | `app/MiniBlade.php` | `eval()` در فضای نام `App` اجرا می‌شد → `App\\Products` به `App\\App\\Products` تبدیل می‌شد | کامپایل در فایل حافظه نهان (`wp-content/cache/beryon-views`) و `include` در فضای نام سراسری + گارد `ABSPATH` |
| ۷ | `app/Assets.php` | `wp_style_add_data(…, 'rtl', 'replace')` در سایت فارسی `app-rtl.css` ناموجود را جایگزین `app.css` می‌کرد → **۴۰۴ و حذف کل استایل سایت** | حذف شد (طراحی قالب ذاتاً RTL است) |
| ۸ | `app/Assets.php` | شاخه manifest وجود فایل را بررسی نمی‌کرد؛ اگر `public/assets` خالی بود، CSS/JS پنل و سایت ۴۰۴ می‌شد | بررسی `is_readable` + fallback فایل خام + **استایل/اسکریپت درون‌خطی اضطراری** |
| ۹ | `resources/css/admin.css` | کارت‌های آمار و نوار فیلتر «صندوق پیام‌ها» قاعده CSS نداشتند | `.beryon-stat-row/.beryon-stat(--warn/--ok/--brand)`, `.beryon-filters`, `.beryon-form` + واکنش‌گرایی |
| ۱۰ | `app/Admin/SettingsSchema.php` | `declared()` از `all()` می‌خواند و به دلیل محافظ بازگشت همیشه خالی برمی‌گشت → تب «پیشرفته» **همه کلیدها را تکرار می‌کرد** | خواندن مستقیم از سازنده‌های تب (`tabMethods()`) |
| ۱۱ | `app/Template.php` + `functions.php` | هیچ لایه‌ای خطای مرگبار را گزارش نمی‌کرد (صفحه سفید بدون سرنخ) | صفحه اضطراری معتبر HTML (با `wp_head`/`wp_footer`) + `beryon_fatal_guard()` روی هوک `shutdown` |
| ۱۲ | `functions.php` | `str_contains`/`str_starts_with` و `match()` فقط در PHP ۸ هستند → صفحه سفید روی PHP ۷.۴ | polyfill + تبدیل `match` به `if` → حداقل نسخه **PHP 7.4** |
| ۱۳ | `app/helpers.php` | `beryon_view()` تابع ناموجود `App\\view()` را صدا می‌زد → خطای مرگبار در حلقه ووکامرس | به `\\App\\Acorn::view()` اصلاح شد |
| ۱۴ | نماها | `{{ beryon_pagination() }}` و `{{ beryon_amp(…) }}` → چاپ برچسب‌ها به‌صورت متن و `R&amp;amp;D` در چارت سازمانی | به `{!! !!}` اصلاح شد (۷ مورد) |
| ۱۵ | نماها | نام کلاس نسبی (`App\\Products`, `WP_Term`) در مسیر `eval` شکسته می‌شد | پیشوند `\\` در ۱۴ نما |
| ۱۶ | `app/Security.php` | `upgrade-insecure-requests` در CSP روی سایت HTTP-only همه دارایی‌ها را از کار می‌انداخت | فقط وقتی `is_ssl()`؛ همچنین `block_bad_agents` به‌جای `exit` خام پاسخ ۴۰۳ خوانا می‌دهد |
| ۱۷ | `app/Acorn.php` | `flush()` حافظه نهان MiniBlade را پاک نمی‌کرد | فراخوانی `MiniBlade::clearCache()` |

### عیب‌یابی سریع اگر باز هم صفحه سفید دیدید

1. `WP_DEBUG` و `WP_DEBUG_LOG` را در `wp-config.php` روشن کنید؛ قالب خطاها را
   هم در `wp-content/debug.log` و هم (برای مدیر) روی صفحه چاپ می‌کند.
2. نسخه PHP هاست باید **۷.۴ یا بالاتر** باشد (توصیه: ۸.۱+).
3. حافظه نهان نماها: `wp beryon cache flush` یا حذف `wp-content/cache/beryon-views`.
4. اگر `public/assets` خالی است، قالب به‌صورت خودکار CSS خام را درون‌خطی چاپ
   می‌کند؛ برای حالت استاندارد `python3 tools/build_assets.py` یا `npm run build`.
5. سلامت کلی: `wp beryon doctor` (۱۵ بررسی) و `برریون ← به‌روزرسانی ← بررسی یکپارچگی`.

### به‌روزرسانی از ۱.۰.۰ با Patch

بسته `beryon-patch-1.0.1.zip` (ساخته‌شده با `tools/make_patch.py`) فقط شامل
فایل‌های تغییرکرده است؛ از **پیشخوان ← بریون ← به‌روزرسانی (Patch) ← بارگذاری
بسته** نصب می‌شود (پشتیبان‌گیری خودکار + بازگردانی در صورت خطا + Dry-run).

---

"""


def patch_readme():
    path = os.path.join(ROOT, 'README.md')
    source = read(path)
    source = sub(source, QA_TABLE_OLD, QA_TABLE_NEW, 'qa-table')
    source = sub(source, QA_OUT_OLD, QA_OUT_NEW, 'qa-output')
    source = sub(source, '## ۱۲) تیم', CHANGELOG + '## ۱۳) تیم', 'team-renumber')
    write(path, source)
    print('README.md به‌روز شد ✔')


def patch_spec():
    path = os.path.join(ROOT, 'docs', 'PATCH_SPEC.md')
    source = read(path)

    source = sub(
        source,
        '      "from": "sha256:c3ab8ff13720e8ad9047dd39466b3c8974e592c2fa383d4a3960714caef0c4f2",\n'
        '      "op": "copy",\n'
        '      "sha256": "9f2c…",\n',
        '      "from": "files/app/Seo.php",\n'
        '      "op": "copy",\n'
        '      "sha256": "9f2c…",\n',
        'example',
    )

    source = sub(
        source,
        '| `from` | خیر | hash فعلی فایل مقصد؛ اگر تنظیم شود و مطابق نباشد، یعنی فایل **دست‌کاری محلی** شده → در گزارش «تعارض» ثبت و طبق سیاست پشتیبان‌گیری محافظت می‌شود |',
        '| `from` | **بله** (برای `op:copy`) | مسیر منبع **داخل بسته**، همیشه با پیشوند `files/` (مانند `files/app/Seo.php`). `PatchApplier::validate_manifest()` نبود این پیشوند را رد می‌کند. تشخیص دست‌کاری محلی فایل مقصد از راه `sha256` پشتیبان‌گیری و گزارش `conflicts` انجام می‌شود، نه این کلید |',
        'from-row',
    )

    source = sub(
        source,
        '`Updater::create()` خودش `sha256`، `size`، `from` (hash فایل فعلی) و',
        '`Updater::create()` خودش `sha256`، `size`، `from` (مسیر `files/…` داخل بسته) و',
        'create-note',
    )

    write(path, source)
    print('docs/PATCH_SPEC.md: معنای `from` با پیاده‌سازی هم‌راستا شد ✔')


def patch_style():
    path = os.path.join(ROOT, 'style.css')
    source = read(path)
    source = sub(source, 'Version:     1.0.0', 'Version:     1.0.1', 'version')
    write(path, source)
    print('style.css: Version 1.0.1 ✔')


def main():
    patch_style()
    patch_readme()
    patch_spec()
    return 0


if __name__ == '__main__':
    sys.exit(main())
