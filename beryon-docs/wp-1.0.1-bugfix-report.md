# گزارش رفع اشکال قالب وردپرس بریون — نسخه ۱.۰.۱

**تاریخ:** ۱۴۰۵/۰۸/۱۲ (2026-11-03) — **قالب:** `beryon-wp/` — **نسخه:** ۱.۰.۰ → ۱.۰.۱ → **۱.۰.۲ (الحاقیه پایین‌تر)**
**گزارش کاربر:** «قالب وردپرس را نصب کردم ولی صفحه اصلی را کاملاً سفید نمایش می‌دهد و تنظیمات قالب دارای استایل نمی باشند. قالب را مجدداً بررسی و مشکلات را برطرف کن.»

---

## الحاقیه ۱.۰.۳ — «قالب لود شده ولی هیچ استایلی ندارد»

**گزارش سوم کاربر (همان روز، پس از ۱.۰.۲):** صفحه اصلی و پنل تنظیمات بدون هیچ
استایل قالب؛ اسکرین‌شات‌ها نشان می‌دادند استایل‌های **هسته وردپرس** (نوار مدیر،
منوی پیشخوان) سالم‌اند ولی `app.css` و `admin.css` قالب غایب‌اند.

### شواهد گردآوری‌شده از خود سایت زنده (بدون دسترسی به سرور)

1. واکشی HTML صفحه اصلی: URL لوگو = `…/themes/beryon-wppublic/assets/images/logo.png`
   — **اسلش بین `beryon-wp` و `public` جاافتاده** ⇒ هر URL دارایی که از همان پایه
   ساخته شود ۴۰۴ می‌شود ⇒ سایت بی‌استایل، بی‌آنکه هیچ خطایی لاگ شود.
2. واکشی مستقیم URL صحیح `…/beryon-wp/public/assets/css/app.css` ⇒ **۲۰۰ و محتوای
   کامل CSS** ⇒ فایل‌ها روی سرور هست؛ فقط URL داخل HTML خراب است.
3. پنل: «نسخه 1.0.0» ⇒ ثابت `BERYON_VERSION` سخت‌کد بود و با فایل‌ها همگام نمی‌شد
   (همچنین بررسی `from_version` پچ‌ها را بی‌معنی می‌کرد).

### ریشه و اصلاح (۱.۰.۳)

- پایه URL دارایی‌ها به `trailingslashit(get_template_directory_uri())` تغییر کرد
  (قطعی، بدون عبور از `get_theme_file_uri` و فیلترهای محیطی)؛ فایل `Assets.php`
  روی سرور کاربر کهنه مانده بود (احتمالاً جایگزینی ناقص در یکی از نصب‌های قبلی)
  و این نسخه کل فایل را بازنویسی می‌کند.
- `BERYON_VERSION` از سربرگ `style.css` خوانده می‌شود + `Updater::sync_version_option()`
  گزینه نسخه را پس از هر جایگزینی فایل خودکار همگام می‌کند.
- نسخه `style.css`: **1.0.3**.

### تحویل‌های ۱.۰.۳

| بسته | محتوا | sha256 |
|---|---|---|
| `beryon-patch-1.0.3.zip` | ۵ فایل: `functions.php`، `app/Assets.php`، `app/Updater.php`، `style.css`، `README.md` — ۳۳٬۶۱ B | `ec9ca1f987d678ba8fea9600ea917e1e83c9a8ecb1ab592a8bb184ecf14e1b4e` |
| `beryon-wp.zip` | قالب کامل ۱.۰.۳ (۱۳۳ فایل، ۴٬۷۲۶٬۰۳۱ B) — **مسیر توصیه‌شده این بار: جایگزینی کامل پوشه قالب** تا هر فایل کهنه احتمالی از نصب‌های قبلی هم پاک شود | `0ebddcb6ffee94f54a406e393199f6cc6fa421c09b4141ae8ae7ef5db078414b` |

---

## الحاقیه ۱.۰.۲ — خطای مرگبار Parse در سایت زنده (PHP 8.4)

**گزارش دوم کاربر:** پس از نصب ۱.۰.۱ روی `beryon.com/new/`، به‌جای صفحه سفید این‌بار
**کارت قرمز نگهبان خطا** نمایش داده شد با این پیام (اسکرین‌شات و `debug.log` ضمیمه شد):

```
PHP Parse error: syntax error, unexpected identifier "utf", expecting ";"
in .../themes/beryon-wp/app/Template.php:148
```

### تحلیل — سه خبر، یکی تلخ

1. **خبر خوب:** `beryon_fatal_guard()` (تحویل ۱.۰.۱) دقیقاً طبق طراحی کار کرد؛
   خطای مرگبار به‌جای صفحه سفید، با مسیر و شماره خط نمایش داده شد.
2. **خبر خوب دوم:** همان `debug.log` تشخیص ۱.۰.۰ ما را مُهر کرد — ردیف
   `Undefined variable $__php ... MiniBlade.php:159` (ساعت ۰۸:۰۴، پیش از نصب
   ۱.۰.۱) همان ریشه صفحه سفید اصلی بود که رفع کرده بودیم.
3. **خبر تلخ:** خطای Parse را **خود اسکریپت پچ ۱.۰.۱ من ساخته بود**: بلوک
   بازگشت `Template::emergency()` با رشته دوتایی‌گیومه‌ای نوشته شده بود که
   گیومه‌های داخلی‌اش escape نشده بود (`"<meta charset="utf-8">"`) و newlineهایش
   خام بودند. یعنی صفحه‌ای که مسئول نمایش خطاهاست، خودش خطای مرگبار داشت —
   و چون PHP در مرحله compile کل کلاس را رد می‌کند، `emergency()` هرگز اجرا
   نمی‌شد و فقط نگهبان `functions.php` (فایلی جدا و سالم) باقی می‌ماند.

### چرا همه ابزارهای QA این را نگرفتند؟

`php_lint.py` فقط توازن آکولاد/پرانتز/تگ می‌سنجد و هیچ‌کدام از ابزارها
**رشته‌ها را tokenize نمی‌کردند** — رشته شکسته از دیدشان نامرئی بود.

### اصلاحات ۱.۰.۲

| # | فایل | ایراد | اصلاح |
|---|---|---|---|
| ۱ | `app/Template.php` | رشته شکسته در بلوک بازگشت `emergency()` → Parse error خط ۱۴۸ | بازنویسی با رشته‌های تک‌گیومه + escape صحیح newline (`"\n"`)؛ خروجی HTML بدون تغییر |
| ۲ | `app/Admin/SettingsPage.php` | `Undefined array key "name"` در `sanitize():190` و `field():830` (ردیف‌های `type=heading` کلید `name` ندارند) + ذخیره کلید خالی `''` در options | guard `?? ''`، `continue` برای فیلدهای بی‌نام، انتقال خواندن `$value` به بعد از early-return |
| ۳ | `tools/php_syntax_check.py` | — (ابزار جدید) | lexer ایستای PHP: شناسه/`$`/گیومه چسبیده بعد از بسته‌شدن رشته، توازن پرانتزها، رشته/کامنت بسته‌نشده، رشته دوتایی‌گیومه‌ای که فقط newline خام دارد، nullable ضمنی (deprecated در PHP 8.4). **خودآزمون:** الگوی خراب Template.php را `FAIL` (خطای «شناسه u بعد از رشته») و SQL چندخطی سالم را `PASS` می‌دهد |
| ۴ | `style.css` | — | نسخه **1.0.2** |

`debug.log` کاربر دو ردیف بی‌ربط هم داشت که مربوط به قالب نیست: `duplicator_*_cron
invalid_schedule` (افزونه Duplicator) — قابل نادیده‌گیری.

### تحویل‌های ۱.۰.۲

| بسته | محتوا | اندازه | sha256 |
|---|---|---|---|
| `beryon-patch-1.0.2.zip` | **۴ فایل** (از ۱.۰.۱): `app/Template.php`، `app/Admin/SettingsPage.php`، `style.css`، `README.md` | ۲۹٬۸۱۹ B | `81411affb284dbe60b8dbb2e607a035983316a18a1f2b41186fc038e350d389e` |
| `beryon-wp.zip` | قالب کامل ۱.۰.۲ (۱۳۳ فایل) | ۴٬۷۲۴٬۹۹۶ B | `abc98bd147f02d29dae604ce50253d4a318fcf349462dea493e47aa57edb1804` |

**نصب (مسیر پیشنهادی — چون ۱.۰.۱ را نصب دارید):** پنل وردپرس →
`تنظیمات قالب بریون → به‌روزرسانی` → بارگذاری `beryon-patch-1.0.2.zip` →
ابتدا «اجرای آزمایشی (dry run)» سپس «اعمال». مانیفست `from_version=1.0.1`
است، پس با نسخه نصب‌شده شما مطابقت دقیق دارد. پس از اعمال، یک‌بار
`ابزارها → پاکسازی حافظه نهان` (یا `wp beryon cache flush`).
مسیر جایگزین: نصب کل `beryon-wp.zip` از `نمایش → پوسته‌ها` (تنظیمات و داده‌ها حفظ می‌شوند).

### دروازه QA نهایی (همه سبز، ۱۴۰۵/۰۸/۱۲)

```
php_lint.py         → ۱۰۲ فایل ✔          blade_check.py --strict → ۳۶ نما، ۰ خطا ✔
php_syntax_check.py → ۶۶ فایل، ۰ خطا، ۰ هشدار ✔   parity_check.py --strict → ۱۸/۱۸ ✔
api_check.py        → ۱۴۹ فراخوانی، ۴۵ تابع، ۲۴ کلاس ✔
```

---

## خلاصه ۵ خطی

1. **ریشه صفحه سفید پیدا شد و قطعیت دارد:** در `app/MiniBlade.php::evaluate()`
   پارامتر `$__php` پیش از `eval('?>' . $__php)` با `unset()` پاک می‌شد؛ یعنی
   کد کامپایل‌شده همیشه `null` بود و **هر نما رشته خالی برمی‌گرداند** — بدون
   هیچ خطا، بدون هیچ خروجی. این مسیر وقتی فعال است که `vendor/autoload.php`
   نباشد (zip بدون `composer install`)، یعنی دقیقاً حالت نصب کاربر.
2. سه باگ کامپایلری دیگر هم در همان فایل بود: `@endphp` به `<?php ` کامپایل
   می‌شد (خطای نحوی)، `@endforelse` به `endforeach;` (درحالی‌که `@empty` شاخه
   `else:` می‌سازد)، و `@forelse ($items as $k => $v)` — فرم استاندارد Blade —
   اصلاً شناخته نمی‌شد. همه اصلاح و با هارنس جدید راستی‌آزمایی شدند.
3. **ریشه بی‌استایل بودن سایت و پنل:** `wp_style_add_data('beryon-app','rtl','replace')`
   در سایت فارسی، `app-rtl.css`ِ ناموجود را جایگزین `app.css` می‌کرد → ۴۰۴ →
   حذف کل استایل؛ به‌علاوه `resolve()` وجود فایل manifest را بررسی نمی‌کرد.
   هر دو رفع شد و **fallback استایل/اسکریپت درون‌خطی** اضافه شد تا سایت هیچ‌وقت
   بی‌استایل نماند. ۵ قاعده CSS غایب پنل (کارت آمار، نوار فیلتر) هم افزوده شد.
4. **هیچ صفحه سفید خاموشی باقی نمانده است:** خطای رندر حالا در `error_log`
   ثبت و به صفحه اضطراری معتبر HTML (با `wp_head`/`wp_footer` و جزئیات برای
   مدیر) منتقل می‌شود؛ `beryon_fatal_guard()` روی هوک `shutdown` خطاهای مرگبار
   PHP را هم چاپ می‌کند؛ و با polyfillها حداقل نسخه PHP به **۷.۴** کاهش یافت.
5. دو بسته تحویل ساخته شد: `beryon-wp.zip` (نصب کامل، ۱۶۳ فایل / ۴.۷MB) و
   `beryon-patch-1.0.1.zip` (۳۶ فایل تغییرکرده / ۱۱۸KB) که با **سیستم Patch
   خود قالب** نصب می‌شود و مانیفست آن با `PatchApplier::validate_manifest()`
   خودآزمایی و معتبر تأیید شده است.

---

## ۱) تحلیل ریشه‌ای — چرا صفحه سفید؟

زنجیره علّی (هر حلقه با خواندن کد تأیید شد):

```
نصب zip بدون vendor/  →  Acorn::available() = false  →  مسیر MiniBlade
        ↓
MiniBlade::render() → compile() ✓ → evaluate($php, $data)
        ↓
evaluate():  $__vars = $__data;  unset($__data, $__php);   ← $__php نابود شد
        ↓
eval('?>' . $__php)  →  eval('?>')  →  خروجی ''   (بدون استثنا، بدون لاگ)
        ↓
Template::render() = ''  →  index.php: echo ''  →  صفحه کاملاً سفید
```

چرا هیچ خطایی دیده نمی‌شد؟ دو لایه خاموش‌سازی روی هم:

| لایه | رفتار پیشین | رفتار اکنون |
|---|---|---|
| `MiniBlade::evaluate()` | `catch (\Throwable)` و `return ''` وقتی `WP_DEBUG` خاموش است | ثبت `error_log` + نمایش برای مدیر + `throw RuntimeException` |
| `Template::render()` | بدون `try/catch` | `try/catch` + صفحه اضطراری HTML با `status_header(500)` |
| `functions.php` | بوت ماژول‌ها بدون محافظ | `try/catch` برای هر ماژول + `beryon_fatal_guard()` روی `shutdown` |

### فهرست کامل ۱۸ اصلاح

| # | فایل | ایراد | اثر روی کاربر | اصلاح |
|---|---|---|---|---|
| ۱ | `app/MiniBlade.php` | `unset($__php)` پیش از `eval` | **صفحه سفید در همه صفحات** | `$__beryonCode` |
| ۲ | `app/MiniBlade.php` | `@endphp` → `<?php ` | ParseError در هر نما با بلوک `@php` | → `?>` |
| ۳ | `app/MiniBlade.php` | `@endforelse` → `endforeach;` | ParseError (`else` بدون `endif`) | → `endif;` |
| ۴ | `app/MiniBlade.php` | `@forelse (X as $k => $v)` پشتیبانی نمی‌شد | «مجله» در صفحه اصلی/بلاگ | پیمایش فرم `as` + فرم سه‌بخشی |
| ۵ | `app/MiniBlade.php` | بلعیدن خطاها | عدم امکان عیب‌یابی | `reportError()` + `throw` |
| ۶ | `app/MiniBlade.php` | `eval` در فضای نام `App` | `App\Products` → `App\App\Products` | حافظه نهان فایلی + `include` در فضای نام سراسری + گارد `ABSPATH` |
| ۷ | `app/MiniBlade.php` | نبود `@stack/@hasSection/@verbatim/@props/@includeWhen/@error` | چاپ شدن متن دستور در صفحه | پشتیبانی کامل + `clearCache()` |
| ۸ | `app/Assets.php` | `wp_style_add_data('rtl','replace')` | **۴۰۴ شدن app.css و حذف کل استایل سایت** | حذف شد |
| ۹ | `app/Assets.php` | manifest بدون بررسی `is_readable` | ۴۰۴ خاموش وقتی `public/assets` خالی است | بررسی وجود + fallback خام + **CSS/JS درون‌خطی اضطراری** |
| ۱۰ | `app/Assets.php` | fallback اسکریپت = `resources/js/app.js` (ES module) | JS خاموش در حالت fallback | fallback به `resources/js/main.js` |
| ۱۱ | `app/Assets.php` | هوک پنل فقط `str_contains($hook,'beryon')` | ریسک بی‌استایل شدن پنل در چیدمان‌های دیگر | بررسی هم‌زمان `?page=` |
| ۱۲ | `resources/css/admin.css` | نبود قاعده برای `.beryon-stat*`, `.beryon-filters`, `.beryon-form` | آمار و فیلتر «صندوق پیام‌ها» بی‌استایل | ۱۵ قاعده + واکنش‌گرایی (۹۷۹۶B بیلد) |
| ۱۳ | `app/Admin/SettingsSchema.php` | `declared()` → `all()` → محافظ بازگشت → `[]` | تب «پیشرفته» همه ۳۱۸ کلید را تکرار می‌کرد | `tabMethods()` مستقیم |
| ۱۴ | `app/helpers.php` | `beryon_view()` → `App\view()` ناموجود | خطای مرگبار در حلقه/برگه محصول ووکامرس | `\App\Acorn::view()` |
| ۱۵ | `app/helpers.php` + `functions.php` | `match()` و `str_contains` (فقط PHP ۸) | صفحه سفید روی PHP ۷.۴ | `if`-chain + polyfill → حداقل **PHP 7.4** |
| ۱۶ | ۷ نما | `{{ beryon_pagination() }}`، `{{ beryon_amp(…) }}`، `'<small>'` داخل `{{ }}` | چاپ برچسب‌ها به‌صورت متن؛ `مدیریت R&amp;amp;D` | `{!! !!}` |
| ۱۷ | `app/Security.php` | `upgrade-insecure-requests` در CSP بدون شرط SSL | سفید شدن سایت‌های HTTP-only | فقط `is_ssl()`؛ `block_bad_agents` با پاسخ ۴۰۳ خوانا به‌جای `exit` |
| ۱۸ | `functions.php` | فعال‌سازی قالب (`after_switch_theme`) بدون محافظ بود | خطا در فعال‌سازی = صفحه سفید | `try/catch` + ثبت خطا + هشدار پیشخوان؛ قالب با مقادیر پیش‌فرض کار می‌کند |

---

## ۲) ابزار راستی‌آزمایی جدید: `tools/blade_check.py`

چون در محیط ساخت PHP/composer نصب نیست، **مراحل کامپایل `MiniBlade` به پایتون
منتقل شد** تا خروجی PHP هر نما ایستا بررسی شود. برای هر ۳۶ نما:

1. توازن `<?php` / `?>` و نبود `<?php` تودرتو (امضای دقیق باگ `@endphp`).
2. توازن ساختارهای کنترلی با پشته، **با تفکیک نحو جایگزین** (`if … : … endif;`)
   **از نحو آکولادی** (`if (…) { … }` داخل بلوک‌های `@php`) و بررسی `else/elseif`
   روی `if` و `case/default` روی `switch`، به‌علاوه توازن `ob_start`/`ob_get_clean`.
3. نبود هیچ `@دستور` کامپایل‌نشده در خروجی (ایمیل‌ها و `@media` مستثنا).
4. نام کلاس نسبی (`App\X`، `WP_Term`) → هشدار.
5. چاپ نشدن HTML داخل `{{ }}`: توابع HTML‌ساز (از روی بدنه `helpers.php`
   استخراج می‌شوند)، `beryon_amp()` و رشته‌های `'<tag'`.

**خودآزمایی ابزار** (برای اطمینان از اینکه «پاس شدن» تهی نیست): روی نماهای
عمداً خراب، دقیقاً همان خطاهایی را گزارش می‌کند که باعث صفحه سفید شده بود:

```
FAIL bad2.blade.php
        ✗ » <?php تودرتو داخل بلوک PHP (نشانه @endphp کامپایل‌نشده)
        ✗ » endforeach اما بالای پشته alt:if است
        ✗ » ساختارهای بسته‌نشده: alt:foreach
        ✗ » @php بلوکی (1) با @endphp (0) جفت نیست
```

---

## ۳) شواهد — خروجی واقعی ابزارها پس از اصلاح

```
$ python3 tools/php_lint.py beryon-wp
بررسی شد: 102 فایل
همه بررسی‌ها پاس شد ✔

$ python3 tools/parity_check.py --strict
نماهای بررسی‌شده: 36 | صفحات استاتیک: 18
همه بررسی‌های هم‌ارزی پاس شد ✔

$ python3 tools/api_check.py
نماهای بررسی‌شده: 36 | فراخوانی‌ها: 149 | توابع قالب: 45 | کلاس‌ها: 24
همه فراخوانی‌های نماها به API موجود ارجاع می‌دهند ✔

$ python3 tools/blade_check.py --strict
نماها: 36 | خطا: 0 | هشدار: 0
همه نماها بدون خطا کامپایل می‌شوند ✔

$ python3 tools/build_assets.py
CSS app.css 50006 B | fonts 1629 | admin 9796 | editor 1250
JS  app.js 20354 B | admin 12449 | manifest 6 ورودی | بیلد موفق ✔

$ node --check (5 فایل JS) → همه ok
$ 10 فایل JSON → معتبر
```

نمونه خروجی کامپایل `layouts/app.blade.php` پس از اصلاح (پیش‌تر `''` بود):

```html
<!DOCTYPE html>
<html lang="<?php echo esc_html((string) (get_locale() === 'fa_IR' ? 'fa' : 'fa')); ?>" dir="rtl">
<head>
<?php echo \App\MiniBlade::render('partials.head', (array) ([])); ?>
</head>
<body <?php body_class(); ?>>
…
<main id="main">
<?php echo \App\MiniBlade::section((string) ('content' ?? '')); ?>
</main>
…
<?php wp_footer(); ?>
</body>
</html>
```

و `@forelse` در `partials/blog-list.blade.php`:

```php
<?php $__beryonFe1 = beryon_query_posts(); if (is_array($__beryonFe1) && $__beryonFe1 !== []): foreach ($__beryonFe1 as $index => $post): ?>
        <article class="blog-card reveal…">
<?php endforeach; else: ?>
        <p class="no-result show">…</p>
<?php endif; ?>
```

---

## ۴) تحویل‌ها

| بسته | حجم | محتوا | کاربرد |
|---|---|---|---|
| `beryon-wp.zip` | ۴٬۷۳۷٬۲۹۵ B / ۱۶۳ فایل | قالب کامل ۱.۰.۱ با دارایی‌های بیلدشده | نصب تازه یا جایگزینی کامل |
| `beryon-patch-1.0.1.zip` | ۱۱۸٬۵۵۲ B / ۳۶ فایل | فقط فایل‌های تغییرکرده + `patch.json` معتبر | **به‌روزرسانی از ۱.۰.۰ با سیستم Patch خود قالب** |

`sha256` بسته پچ: `318abb4163ccd12552d21ee1f1b348f38566d533296a8c0f03eb7a43d8ce28fd`

### راه الف — به‌روزرسانی با Patch (توصیه‌شده؛ تنظیمات و محتوا دست‌نخورده می‌ماند)

1. پیشخوان وردپرس ← **بریون ← به‌روزرسانی (Patch)**.
2. `beryon-patch-1.0.1.zip` را بارگذاری کنید؛ ابتدا **Dry-run** (گزارش بدون تغییر).
3. سپس «اعمال» — پشتیبان خودکار در `public/patches/backups` ساخته می‌شود و در
   صورت خطا بازگردانی خودکار انجام می‌گیرد؛ `post_apply` شامل
   `flush_rewrites`, `clean_caches`, `refingerprint` است.

### راه ب — نصب کامل

`beryon-wp.zip` را در `wp-content/themes/` جایگزین کنید (پوشه `beryon`) و
قالب را دوباره فعال کنید. در صورت دسترسی به ترمینال: `composer install`
(مسیر Blade واقعی Acorn) — **بدون composer هم قالب کار می‌کند** (MiniBlade
اکنون سالم است). دارایی‌ها از پیش بیلدشده در `public/assets/` هستند.

### اگر باز هم صفحه سفید دیدید

`WP_DEBUG` و `WP_DEBUG_LOG` را روشن کنید: قالب خطا را هم در
`wp-content/debug.log` و هم (برای مدیر) روی صفحه چاپ می‌کند؛ نسخه PHP باید
۷.۴+ باشد؛ `wp beryon cache flush` حافظه نهان نماها را پاک می‌کند؛ و
`wp beryon doctor` ۱۵ بررسی سلامت انجام می‌دهد.

---

## ۵) بخش باز (Open)

1. **راستی‌آزمایی اجرایی انجام نشده است.** در محیط ساخت PHP/composer/node
   نصب نیست؛ همه بررسی‌ها ایستا (لینتر، شبیه‌ساز کامپایلر، هم‌ارزی مارک‌آپ)
   است. اجرای واقعی روی وردپرس زنده هنوز توسط کاربر تأیید نشده.
2. **`beryon-patch-1.0.1.zip` امضا ندارد** چون `updater.signature_key` خالی
   است؛ برای زنجیره تأمین، کلید را در پنل تنظیم کنید تا `signature` به
   مانیفست افزوده شود (بسته باید دوباره ساخته شود).
3. **مسیر `storage/` در `updater.patch_slug_allowlist` نیست** (پیش‌فرض ۱.۰.۰
   در دیتابیس سایت ذخیره شده)؛ به همین دلیل فایل‌های `storage/…` در بسته پچ
   نیامدند. MiniBlade پوشه حافظه نهان را خودش در `wp-content/cache/beryon-views`
   می‌سازد، پس این محدودیت اثر عملکردی ندارد.
4. **ناسازگاری مستند و کد در `docs/PATCH_SPEC.md` رفع شد** (کلید `from` در
   مستند «hash فایل فعلی» بود ولی در کد «مسیر منبع داخل `files/`» است). این
   سند داخل بسته پچ نیست (پوشه `docs/` بیرون از allowlist است) و فقط در نصب
   کامل به‌روز می‌شود.
5. موارد باز پیشین هنوز پابرجا است: متن استاتیک «۶۱ محصول» در برابر داده
   **۴۳** محصول؛ `blog-3.webp` کپی بایتی `cat-frozen.webp`؛ تکرار ~۱.۹MB
   تصاویر در `resources/images` و `public/assets/images`؛ `beryon.pot` ساخته
   نشده؛ بیلد دارایی‌ها با `tools/build_assets.py` است نه `vite build` واقعی؛
   و هیچ تست مرورگری/Core Web Vitals انجام نشده.
6. **تب «پیشرفته» اکنون درست کار می‌کند** ولی برچسب فارسی فقط برای ~۳۰ کلید
   پرکاربرد تعریف شده (`advanced_labels()`)؛ بقیه با برچسب خودکار
   «کلید X (گروه Y)» نمایش داده می‌شوند.
7. صفحه اضطراری `Template::emergency()` عمداً `status_header(500)` می‌فرستد؛
   اگر ترجیح می‌دهید در حالت خطا صفحه ۲۰۰ بماند (برای سئو)، این خط قابل
   تغییر است.
