# قالب وردپرس بریون (Beryon) — Sage 11

قالب اختصاصی، کاملاً راست‌چین و واکنش‌گرا برای **شرکت بریان گوشت آمل** (برند
**بریون**)؛ تبدیل ۱:۱ قالب HTML نهایی `beryon/` به قالب وردپرس استاندارد، با
سازگاری ووکامرس در **حالت کاتالوگ/استعلام** (بدون فروش آنلاین)، پنل تنظیمات،
سیستم به‌روزرسانی Patch، سئوی پیشرفته و بهینه‌سازی سنگین بارگذاری.

> **اصل حاکم:** هیچ صفحه‌ای از قالب HTML تغییر نکرده است. مارک‌آپ، کلاس‌ها،
> ترتیب بخش‌ها و هوک‌های JS دقیقاً همان‌اند؛ فقط «داده» از کد جدا شده و از
> پنل تنظیمات یا دیتابیس وردپرس می‌آید.

---

## ۱) نصب

### پیش‌نیازها
* وردپرس ۶.۴+ / PHP ۸.۱+
* Composer ۲ و Node ۱۸+ (برای `vendor/` و بیلد دارایی‌ها)
* ووکامرس **اختیاری** است؛ قالب بدون آن هم کامل کار می‌کند (نوع پست
  `beryon_product` و تاکسونومی `product_cat` داخلی ثبت می‌شود).

### گام‌ها

```bash
# ۱) قالب را در wp-content/themes/beryon قرار دهید
cd wp-content/themes/beryon

# ۲) وابستگی‌های PHP (roots/acorn → Blade و …)
composer install --no-dev --optimize-autoloader

# ۳) دارایی‌ها (اختیاری: بسته فایل‌های «از پیش بیلدشده» در public/assets دارد)
npm install
npm run build

# ۴) در پیشخوان وردپرس قالب را فعال کنید
```

اگر در محیط میزبان دسترسی به `npm` ندارید، **نیازی به بیلد نیست**: فایل‌های
`public/assets/{css,js,fonts,images}` از پیش بیلد و کامیت شده‌اند و
`app/Assets.php` با `manifest.json` یا مستقیماً از همان مسیرها بارگذاری می‌کند.
اگر Composer هم در دسترس نبود، `app/MiniBlade.php` (کامپایلر Blade داخلی
قالب) جای `roots/acorn` را می‌گیرد تا سایت بدون `vendor/` هم رندر شود.

### درج داده نمونه (اختیاری ولی توصیه‌شده)

```bash
wp beryon seed                 # ۵ دسته، ۴۳ محصول، ۹ برگه، ۶ نوشته، ۳ منو
wp beryon seed --media         # همراه با درون‌ریزی تصاویر پوشه قالب
wp beryon doctor               # ۱۵ بررسی سلامت (نسخه، پوشه‌ها، منو، پرmalink، …)
wp beryon cache flush
```

`Seeder` از `config/catalog.json` و `config/blog.json` می‌خواند؛ یعنی محتوای
پیش‌فرض سایت در فایل (قابل بازبینی در Git) است، نه در دیتابیس.

---

## ۲) ساختار پوشه‌ها

```
beryon/
├── app/                    لایه PHP (PSR-4 با فضای نام App)
│   ├── Acorn.php           پل roots/acorn (در صورت نبود، غیرفعال می‌شود)
│   ├── Template.php        سلسله‌مراتب قالب → نامزدهای Blade + داده مشترک نما
│   ├── MiniBlade.php       کامپایلر Blade داخلی (fallback بدون vendor)
│   ├── Settings.php        خواندن/ذخیره تنظیمات + فایل‌های config/*.json
│   ├── Assets.php          صف دارایی‌ها، manifest، srcset، کلاس body، نکات head
│   ├── Seo.php             عنوان/توضیح/canonical/OG/Twitter/JSON-LD/سایت‌맵
│   ├── Optimize.php        حذف ایموجی/oembed/CSS اضافی، defer، lazy، کش کوئری
│   ├── Security.php        هدرها، CSP، سخت‌سازی لاگین، اثر انگشت فایل‌ها
│   ├── Forms.php           REST فرم‌ها (تماس/نظرات/نمایندگی/خبرنامه) + جدول
│   ├── Updater.php         فید، بررسی، دانلود، AJAX، تاریخچه، WP-Cron
│   ├── Updater/PatchApplier.php  اعتبارسنجی، staging، پشتیبان، اعمال اتمیک
│   ├── Nav.php / NavWalker.php   منوها (desktop/mobile/footer) با مارک‌آپ قالب
│   ├── Products.php        نوع پست/تاکسونومی، متا، کوئری، نرمال‌سازی، دسته‌ها
│   ├── WooCommerce.php     سازگاری ووکامرس + حالت کاتالوگ (بدون قیمت/سبد)
│   ├── Jalali.php          پورت دقیق jalaali-js ۱.۱.۵ (تاریخ شمسی)
│   ├── Seeder.php / Cli.php      درون‌ریزی داده + دستورات wp-cli
│   ├── Admin/              پنل تنظیمات، اسکیما، مدیریت پیام‌های دریافتی
│   ├── Providers/          ThemeServiceProvider و ViewServiceProvider
│   └── helpers.php         توابع سراسری beryon_* (بیش از ۶۰ تابع)
├── config/                 داده پیش‌فرض (JSON، قابل ویرایش بدون کد)
│   ├── defaults.json       همه تنظیمات پنل با مقدار پیش‌فرض (۱۷ گروه)
│   ├── catalog.json        ۵ دسته + ۴۳ محصول + متن‌های هر دسته
│   ├── blog.json           ۶ نوشته (بدنه، چکیده، تاریخ شمسی، تصویر)
│   ├── icons.json          ۷۳ آیکون SVG درون‌خطی (بدون فونت آیکون)
│   ├── provinces.json      ۳۱ استان برای فرم نمایندگی
│   └── app.php             پیکربندی Acorn
├── resources/
│   ├── views/              ۳۶ نمای Blade (صفحه‌ها، اجزا، لایه، ووکامرس)
│   ├── css/                beryon.css (طراحی اصلی) + wp.css + fonts + admin + editor
│   ├── js/                 main.js (رفتارهای قالب HTML) + app.js + admin.js
│   ├── images/             ۱۲ تصویر webp/png
│   └── fonts/              وزیرمتن (۵ وزن، woff2 خودمیمان)
├── public/
│   ├── assets/             خروجی بیلد (css/js/fonts/images + manifest.json)
│   └── patches/            backups/ + patch-log.jsonl
├── docs/PATCH_SPEC.md      قرارداد کامل بسته به‌روزرسانی
├── index.php               نقطه ورود (فقط echo App\Template::render())
├── functions.php           بارگذاری bootstrap + ثابت‌ها
├── style.css               سربرگ قالب
├── vite.config.js          ورودی‌ها و نام‌گذاری ثابت خروجی
└── composer.json / package.json / theme.json / tailwind.config.js
```

---

## ۳) نگاشت صفحه‌های HTML به نماهای Blade

| صفحه استاتیک | نمای Blade | منبع داده |
|---|---|---|
| `index.html` | `front-page.blade.php` | `home.*` در تنظیمات + محصولات/دسته‌ها/نوشته‌ها از DB |
| `products.html` | `archive-product.blade.php` | `Products::query()` + `products.*` |
| `cat-*.html` (۵ صفحه) | `taxonomy-product_cat.blade.php` | term meta + fallback به `catalog.json` |
| `product-single.html` | `single-product.blade.php` | متای محصول + تب‌ها + `spec-table` |
| `about.html` | `page-about.blade.php` | `about.*` |
| `ceo.html` | `page-ceo.blade.php` | `ceo.*` (تصویر `ceo-photo.png` بدون تغییر) |
| `org-chart.html` | `page-org-chart.blade.php` | `org.*` (درخت + شرح وظایف) |
| `certificates.html` | `page-certificates.blade.php` | `certificates.*` |
| `contact.html` | `page-contact-us.blade.php` | `contact.*` + `general.*` + REST فرم |
| `feedback.html` | `page-feedback.blade.php` | `feedback.*` + آکاردئون پرسش‌ها |
| `agency.html` | `page-agency.blade.php` | `agency.*` + `provinces.json` |
| `catalog.html` | `page-catalog.blade.php` | `catalog.*` + شمارش واقعی دسته‌ها |
| `blog.html` | `home.blade.php` (+ `index.blade.php`) | نوشته‌های DB با تاریخ شمسی |
| `blog-single.html` | `single.blade.php` | `the_content` + سایدبار |
| — | `page.blade.php` | برگه‌های بدون نمای اختصاصی (مثلاً «قوانین»): سربرگ صفحه + محتوای ویرایشگر |
| — | `singular.blade.php` | هر محتوای تکی دیگر بدون نمای اختصاصی (بدنه مشترک: `partials/entry`) |
| — | `archive.blade.php` | بایگانی دسته/برچسب/تاریخ |
| — | `search.blade.php` | نتایج جست‌وجو (نوشته + محصول) |
| — | `404.blade.php` | پیشنهاد دسته/محصول/نوشته |
| — | `woocommerce/{cart,checkout,myaccount}` | فقط در صورت فعال بودن فروشگاه |

---

## ۴) پنل تنظیمات

مسیر: **پیشخوان ← بریون** (۵ صفحه: تنظیمات، پیام‌های دریافتی،
به‌روزرسانی، ابزارها، مستندات).

گروه‌های تنظیمات: `general` (برند/لوگو/تماس/شبکه‌های اجتماعی/فوتر)،
`home` (۴۰+ کلید هیرو، آمار، مزیت‌ها، درباره، محصولات ویژه، گواهینامه‌ها،
وبلاگ، فراخوان)، `products`، `catalog`، `about`، `ceo`، `org`،
`certificates`، `contact`، `feedback`، `agency`، `blog`، `seo`، `forms`،
`performance`، `security`، `updater`.

* هر متن ثابت سایت یک کلید دارد؛ **ردیف‌ساز (Repeater)** برای فهرست‌ها
  (مزیت‌ها، آمار، گاه‌شمار، گواهینامه‌ها، پرسش‌ها، گام‌ها).
* توکن‌های پویا در همه رشته‌ها: `{count}` (تعداد کل محصولات)،
  `{year}`، `{brand}`، `{phone_sales_label}`، `{email}`، `{address}` و…
  (توسط `beryon_tokens()` جایگزین می‌شوند).
* تصاویر با انتخابگر رسانه وردپرس (`wp.media`) و fallback به فایل قالب.
* تب **«پیشرفته (همه کلیدها)»**: `SettingsSchema::advanced()` به‌صورت خودکار
  هر کلید `config/defaults.json` را که فیلد صریح ندارد (برچسب breadcrumbها،
  پیام‌های موفقیت، متن‌های آرشیو، فهرست استان‌ها و…) قابل ویرایش می‌کند؛
  یعنی افزودن کلید جدید به `defaults.json` بدون کدنویسی در پنل دیده می‌شود.

---

## ۵) به‌روزرسانی با Patch

خلاصه اجرایی (قرارداد کامل در `docs/PATCH_SPEC.md`):

```
patch.zip
├── patch.json      {format:1, theme:"beryon", version, requires, files[], sql[], post_apply[]}
├── signature.txt   HMAC-SHA256 بایت‌های patch.json (اگر updater.signature_key پر باشد امضا اجباری است:
│                   همین فایل، یا کلید signature داخل patch.json — docs/PATCH_SPEC.md بخش ۷)
└── files/…         فایل‌های جدید با مسیر نسبی قالب
```

مسیر اعمال: `validate → stage → verify sha256 → plan → backup → apply (اتمیک)
→ sql → post_apply → log`؛ در صورت هر خطا **بازگردانی خودکار**.

```bash
wp beryon patch build --version=1.1.0 --dir=app --out=/tmp/patch.zip
wp beryon patch inspect /tmp/patch.zip
wp beryon patch apply /tmp/patch.zip --dry-run
wp beryon patch apply /tmp/patch.zip --yes
wp beryon patch backups && wp beryon patch rollback <file> --yes
```

در پنل (بریون ← به‌روزرسانی): آپلود zip، حالت آزمایشی (فهرست `add/replace/delete`
بدون نوشتن فایل)، بررسی فید، بازگردانی، «بررسی یکپارچگی فایل‌ها» (مقایسه با اثر
انگشت ثبت‌شده برای تشخیص دست‌کاری) و «بازسازی اثر انگشت» (پس از جایگزینی دستی
فایل‌ها با zip/FTP).

---

## ۶) سئو

* عنوان/توضیح/کانونیکال برای هر نوع صفحه از `Seo::…` با قالب
  `seo.title_template`؛ `robots` تفکیکی (جست‌وجو و بایگانی `noindex`
  طبق تنظیمات).
* Open Graph + Twitter Card + تصویر پیش‌فرض `hero.webp`.
* **JSON-LD**: `FoodEstablishment`/`Organization`، `Product` (بدون قیمت، با
  برند و دسته)، `BreadcrumbList`، `Article`، `FAQPage` (برای صفحه نظرات)،
  `WebSite` + `SearchAction`.
* سایت‌محصول/دسته/نوشته با داده واقعی؛ `sitemap.xml` داخلی
  (`seo.sitemap_slug`) بدون افزونه جانبی.
* تاریخ شمسی در خروجی و در `datePublished` (پورت دقیق jalaali-js).
* `hreflang`/`lang="fa"` و `dir="rtl"` در `<html>`؛ عنوان‌های yektā (H1 واحد
  در هر صفحه)، متن جایگزین تصویر، و `sr-only` برای فهرست‌ها.

---

## ۷) کارایی

| اقدام | جزئیات |
|---|---|
| بدون وابستگی JS | هیچ jQuery/کتابخانه خارجی؛ `main.js` + `app.js` ≈ ۲۰KB (بدون gzip) |
| CSS یکپارچه | `beryon.css` + `wp.css` ≈ ۵۰KB، فشرده‌شده با بیلد داخلی |
| فونت خودمیمان | وزیرمتن woff2 با `font-display: swap` و `unicode-range` محدود به فارسی/ارقام → حذف درخواست به Google (قابل خاموش‌کردن) |
| آیکون‌ها | SVG درون‌خطی از `config/icons.json` (بدون فونت آیکون و بدون اسپریت اضافی در بدنه) |
| تصاویر | همه `webp` (به‌جز `ceo-photo.png` که باید دست‌نخورده بماند)، `loading="lazy"`، `width/height` برای حذف CLS، `srcset` از وردپرس |
| LCP | `preload` تصویر هیرو + `fetchpriority="high"` فقط در صفحه اصلی |
| پاک‌سازی | حذف ایموجی، oEmbed، `wp-block-library`، CSS ووکامرس؛ `defer` روی اسکریپت‌ها |
| DOM | کارت‌ها از کامپوننت مشترک؛ مارکی گواهینامه با `array_merge` (بدون DOM تکراری اضافی)؛ `no_found_rows` در کوئری‌های کمکی |
| کش | transient برای شمارش دسته‌ها، آخرین نوشته‌ها و `image_url`؛ `performance.cache_ttl` |

---

## ۸) امنیت

* فرم‌ها فقط از طریق REST با `X-WP-Nonce`، **honeypot** پنهان، محدودسازی
  نرخ (۵ در دقیقه برای هر IP)، سقف طول پیام، و پاسخ جعلی موفق برای ربات‌ها.
* هدرهای امنیتی، CSP (قابل report-only)، HSTS اختیاری، پنهان‌سازی نسخه
  وردپرس، پیام خطای عمومی لاگین، مسدودسازی user-agentهای مخرب.
* غیرفعال‌سازی ویرایشگر فایل پوسته (`DISALLOW_FILE_EDIT`) طبق تنظیمات.
* بررسی یکپارچگی فایل‌ها با اثر انگشت sha256 و هشدار در پنل.
* `PatchApplier`: جلوگیری از Path Traversal، allowlist مسیر/پسوند، سقف حجم،
  نوشتن اتمیک، و پشتیبان‌گیری پیش از هر تغییر.

---

## ۹) ابزارهای کنترل کیفیت

| ابزار | فرمان |
|---|---|
| لینتر PHP/Blade داخلی | `python3 tools/php_lint.py beryon-wp` |
| بررسی هم‌ارزی با ۱۸ صفحه HTML | `python3 tools/parity_check.py --strict` |
| بررسی ارجاع نماها به API قالب | `python3 tools/api_check.py` |
| **کامپایل ایستای همه نماهای Blade** | `python3 tools/blade_check.py --strict` |
| **بررسی نحوی ایستای PHP (جدید در ۱.۰.۲)** | `python3 tools/php_syntax_check.py beryon-wp --strict` |
| ساخت بسته Patch | `python3 tools/make_patch.py 1.0.4` (فایل‌ها از `git diff` نسبت به کامیت نسخه پیشین؛ روش قدیمی mtime: `--since "YYYY-MM-DD HH:MM"`) |
| بیلد دارایی‌ها (بدون npm) | `python3 tools/build_assets.py` |
| ساخت بسته نصب کامل (جدید در ۱.۰.۴) | `python3 tools/make_theme_zip.py` → `beryon-wp.zip` با یک پوشه سطح اول `beryon-wp/`؛ بیلد دارایی‌ها را خودش اجرا می‌کند (`--folder` برای نام پوشه دیگر) |
| تولید نماها از صفحات استاتیک | `python3 tools/make_front_page.py`، `tools/specs_*.py` |
| سلامت سایت | `wp beryon doctor` |

خروجی آخرین اجرا (۱۴۰۵/۰۸/۱۹ — نسخه ۱.۰.۴):

```
php_lint.py      → بررسی شد: ۱۰۷ فایل | همه بررسی‌ها پاس شد ✔
parity_check.py  → ۱۸/۱۸ صفحه OK | نماهای بررسی‌شده: ۳۹ | همه پاس ✔ (پس از build_assets.py)
api_check.py     → ۳۹ نما | ۱۵۷ فراخوانی | ۴۸ تابع | ۲۴ کلاس ✔
blade_check.py   → ۳۹ نما | خطا: ۰ | هشدار: ۰ (--strict) ✔
php_syntax_check → ۶۸ فایل PHP | خطای نحوی: ۰ | هشدار: ۰ (--strict) ✔
build_assets.py  → app.css ۵۰KB | app.js ۲۰KB | admin.css ۹.۸KB | بیلد موفق ✔
```

`parity_check.py` وجود `public/assets/manifest.json` را هم می‌سنجد. این فایل خروجی
بیلد است و در گیت نگه‌داری نمی‌شود (`beryon-wp/.gitignore`)؛ در clone تازه ابتدا
`python3 tools/build_assets.py` را اجرا کنید (CSS/JS را بایت‌به‌بایت همان خروجی
موجود می‌سازد و فقط manifest را اضافه می‌کند)، وگرنه parity با خطای «دارایی
بیلدشده مفقود: manifest.json» می‌ایستد.

این ابزارها ایستا هستند و خطای زمان اجرا را نمی‌گیرند (هر پنج ابزار روی نسخه
۱.۰.۳ سبز بود درحالی‌که همه نشانی‌ها نمای اشتباه نشان می‌دادند). از ۱.۰.۴ هر
انتشار باید یک بار روی وردپرس واقعی هم اجرا شود؛ روش و نتیجه در
`beryon-docs/wp-1.0.4-bugfix-report.md` آمده است.

`blade_check.py` مراحل کامپایل `app/MiniBlade.php` را بازتولید می‌کند و برای هر
نما بررسی می‌کند: توازن `<?php`/`?>`، توازن ساختارهای کنترلی (با تفکیک نحو
جایگزین از آکولادی)، نبود دستور کامپایل‌نشده، نام کلاس نسبی، و چاپ نشدن
HTML/`beryon_amp()` داخل `{{ }}` (که به متن فرار‌شده تبدیل می‌شود).

گزارش کامل ساخت و فهرست موارد باز: `beryon-docs/wp-theme-build-report.md`.

---

## ۱۰) دستورات wp-cli

```
wp beryon version
wp beryon seed [--force] [--media] [--skip-pages] [--skip-posts]
wp beryon options list [--group=seo] | get <key> | set <key> <value> | reset [--yes]
wp beryon cache flush
wp beryon patch inspect|apply|build|backups|rollback|feed
wp beryon integrity [--refresh]
wp beryon doctor
wp beryon images [--regenerate]
```

---

## ۱۱) نکته‌های مهم برای نگهداری

1. **`ceo-photo.png` را هرگز تغییر ندهید** (کپی بایت‌به‌بایت تصویر تأییدشده).
2. اگر `resources/css/beryon.css` را ویرایش کردید، حتماً بیلد دارایی‌ها را
   تازه کنید؛ خروجی سایت از `public/assets/css/app.css` خوانده می‌شود.
3. هدر شفاف فقط در **صفحه اصلی + دسکتاپ** فعال است (کلاس `home` روی `body`
   توسط `Assets::body_class()` و قاعده CSS در انتهای `beryon.css`).
4. هوک‌های JS را تغییر ندهید: `.header.is-stuck`، `.to-top.show`،
   `[data-subtoggle]`، `.reveal`، `[data-count]`، `[data-filter-wrap]`،
   `.chip[data-filter]`، `[data-search]`، `[data-count-label]`،
   `.no-result.show`، `[data-tabs]`، `.acc-item`، `.thumbs button[data-img]`،
   `[data-lightbox]`، `form[data-form]`، `.form-success.show`، `.track`،
   `[data-year]`.
5. پس از تغییر `Products::post_type()` یا نامک صفحه‌ها، `wp rewrite flush`.
6. متن‌های پیش‌فرض در `config/*.json` هستند؛ تغییر آن‌ها روی سایتِ دارای
   تنظیمات ذخیره‌شده اثر ندارد (تنظیمات ذخیره‌شده اولویت دارند) — برای
   بازنشانی: `wp beryon options reset`.

---

## ۱۲) تغییرات نسخه‌ها (changelog)

### ۱.۰.۴ — رفع ایرادهای بازبینی: مسیریابی نماها، داده نمونه، Patch، پنل تنظیمات

نخستین نسخه‌ای که روی وردپرس واقعی اجرا و آزموده شده است (وردپرس ۶.۷.۲، PHP
8.4.27 و 7.4.33). شواهد هر ردیف: `beryon-docs/wp-1.0.4-bugfix-report.md`.

| # | باگ | ریشه | اصلاح |
|---|---|---|---|
| ۱ | همه نشانی‌ها (صفحه اصلی، برگه‌ها، محصولات، دسته‌ها، ۴۰۴) فهرست وبلاگ را نشان می‌دادند | `MiniBlade::find()` فقط مسیرهای ثبت‌شده با `addPath()` را می‌گشت و این ثبت هنگام نخستین رندر انجام می‌شد؛ `Template::resolve()` که پیش از رندر اجرا می‌شود هیچ نمایی پیدا نمی‌کرد و به `index` برمی‌گشت | `resources/views` همیشه جزو مسیرهای جست‌وجوست (`searchPaths()`)؛ نماهای تازه `page`، `singular` و `partials/entry` برای محتوای بدون نمای اختصاصی؛ `singular` فقط برای درخواست تکی نامزد می‌شود |
| ۲ | «اجرای دوباره داده‌های نمونه» / `wp beryon seed` با خطای مرگبار می‌ایستاد و منوها ساخته نمی‌شد؛ هر اجرا برگه‌های فرزند را تکرار می‌کرد؛ تاریخ نوشته‌های نمونه ۱۹۹۹ بود | `wp_get_nav_menu_item_count()` در وردپرس وجود ندارد؛ برگه فرزند با اسلاگ تنها جست‌وجو می‌شد؛ خروجی `Jalali::toGregorian()` انجمنی است ولی با اندیس عددی خوانده می‌شد | `wp_get_nav_menu_items()`؛ جست‌وجو با مسیر کامل والد/فرزند؛ کلیدهای `gy/gm/gd`؛ اجرای بعدی نوشته‌های ۱۹۹۹ را خودش ترمیم می‌کند |
| ۳ | درون‌ریزی رسانه (`--media`) روی سکوی بدون `GLOB_BRACE` با خطای مرگبار می‌ایستاد؛ رسانه‌ای که در همان اجرا وارد می‌شد به دسته/محصول/نوشته وصل نمی‌شد | ثابت `GLOB_BRACE` همه‌جا تعریف نشده؛ فایل اصلی قالب به‌عنوان `tmp_name` به `media_handle_sideload()` داده می‌شد (آن تابع فایل را جابه‌جا می‌کند)؛ نتیجه «یافت نشد» جست‌وجوی پیوست پیش از درون‌ریزی کش می‌شد و متای `_beryon_asset` ثبت نمی‌شد | پیمایش پسوند به پسوند؛ کار روی رونوشت موقت (فایل قالب سر جایش می‌ماند)؛ ثبت متا و جایگزینی نگاشت کش (`Assets::remember_attachment()`) |
| ۴ | نشان دسته و «نوع محصول» اسلاگ لاتین بود | برچسب‌های محصول با نام لاتین ساخته می‌شدند و `Products::normalize()` کلیدهای `cat_short`/`type_label` را برنمی‌گرداند | نام فارسی از `catalog.json` (اسلاگ لاتین برای فیلتر می‌ماند)؛ برچسب‌های قدیمی در اجرای بعدی درون‌ریز ترمیم می‌شوند |
| ۵ | اعمال Patch پس از جایگزینی فایل‌ها با خطای مرگبار می‌ایستاد (نسخه، لاگ و اثر انگشت ثبت نمی‌شد) | داخل فضای نام `App\Updater` نام کوتاه `Updater` به کلاس ناموجود `App\Updater\Updater` تفسیر می‌شد | نام کامل `\App\Updater` در هر سه فراخوانی |
| ۶ | با تنظیم «کلید امضا» هیچ بسته‌ای پذیرفته نمی‌شد | امضای داخل مانیفست با متن خامِ دارای همان کلید مقایسه می‌شد و `signature.txt` اصلاً خوانده نمی‌شد | دو شکل امضا (`docs/PATCH_SPEC.md` بخش ۷) با `signing_payload()` مشترک میان سازنده و اعتبارسنج |
| ۷ | دکمه‌های «بازگردانی» و «بازسازی اثر انگشت» کار نمی‌کردند؛ نتیجه آپلود Patch دیده نمی‌شد؛ پیوند هشدارهای یکپارچگی/به‌روزرسانی ۴۰۳ می‌داد | اسکریپت پنل نام فایل را با کلید `file` می‌فرستاد و PHP کلید `backup` را می‌خواند؛ اکشن `beryon_fingerprint` ثبت نشده بود؛ برگشت به صفحه‌ای دیگر بدون پیام؛ نشانی `themes.php?page=…` | هر دو کلید پذیرفته می‌شود؛ `ajax_fingerprint()`؛ برگشت به صفحه به‌روزرسانی با پیام و فهرست `add/replace/delete`؛ نشانی `admin.php?page=beryon-updates` |
| ۸ | بسته‌ای که خود قالب می‌ساخت (`wp beryon patch build`) روی PHP 7.4 و 8.0 رد می‌شد | `requires.php` پیش‌فرض `8.1` سخت‌کد بود | از سربرگ `Requires PHP` در `style.css` خوانده می‌شود |
| ۹ | ذخیره یک زبانه تنظیمات مقدار زبانه‌های دیگر را خالی می‌کرد (در آزمون: ۲۳۴ از ۳۳۵ کلید) | `sanitize()` روی کل schema می‌چرخید و برای فیلدهایی که در فرم همان زبانه نبودند مقدار خالی می‌نوشت | پایه = مقدار ذخیره‌شده؛ فقط فیلدهای ارسال‌شده بازنویسی می‌شوند؛ فیلد پنهان `0` پیش از هر چک‌باکس |
| ۱۰ | «بازنشانی تنظیمات» ۲۱ هشدار می‌داد و فهرست‌ها را به «Array» تبدیل می‌کرد؛ تکرارشونده‌های زبانه «صفحات» ذخیره نمی‌شدند؛ تصویر انتخاب‌شده پاک نمی‌شد؛ زبانه «پیشرفته» مقدار بولی را رشته «1» می‌کرد | مقدار پیش‌فرض از همان مسیر پاک‌سازی فرم رد می‌شد؛ نام ورودی‌های تکرارشونده دوبار پیشوند می‌گرفت؛ مقدار ۰ برای فیلد رسانه نادیده گرفته می‌شد؛ نوع فیلد از نوع مقدار گرفته نمی‌شد | بازنشانی مستقیم به `config/defaults.json`؛ اصلاح نام ورودی‌ها و نوع `sublist`؛ پذیرش ۰؛ `checkbox`/`number` بر پایه نوع مقدار؛ پایان خط‌ها LF |
| ۱۱ | نخستین بارگذاری پیشخوان (و سپس هفته‌ای یک بار) خطای مرگبار می‌داد | هوک `admin_notices` به متد ناموجود `SettingsPage::welcome_notice` وصل می‌شد (PHP 8: TypeError) | متد اضافه شد؛ وضعیت در Option `beryon_welcome` است و پیام فقط پس از فعال‌سازی قالب، یک بار نمایش داده می‌شود |
| ۱۲ | `/sitemap.xml` به صفحه اصلی ریدایرکت می‌شد | متغیرهای `beryon_sitemap` در `query_vars` ثبت نشده بودند و `redirect_canonical` زودتر اجرا می‌شد؛ الگوی `…?$` آخرین حرف نشانی را اختیاری می‌کرد | فیلتر `query_vars`؛ اجرای `maybe_sitemap` با اولویت ۰؛ الگوی `/?$`؛ بازسازی قواعد بازنویسی با تغییر تنظیم سایت‌مپ، اعمال Patch و بازگردانی |
| ۱۳ | گزینه‌های «انواع فروش» در فرم نمایندگی چاپ نمی‌شد و ارسال چندگزینه‌ای خطای ۴۰۰ می‌داد | `beryon_rows()` ردیف‌های رشته‌ای را دور می‌ریخت؛ طرح REST فیلد را تک‌مقداری می‌خواست ولی فرم `sales_types[]` می‌فرستد | ردیف رشته‌ای پذیرفته می‌شود؛ طرح رشته/آرایه + `Forms::sanitize_choices()` |
| ۱۴ | تصویر کارت‌های مجله نشانی `http://blog-1.webp` داشت؛ تصویر شاخص نوشته نادیده گرفته می‌شد؛ نوشته بدون تصویر `<img src="">` می‌گرفت | نام فایل مستقیم در `esc_url()` چاپ می‌شد؛ متای درون‌ریز بر تصویر شاخص مقدم بود | `beryon_img()` در نماها؛ تصویر شاخص مقدم است (با `srcset` و ابعاد)؛ بدون تصویر، تگ `img` چاپ نمی‌شود |
| ۱۵ | صفحه‌بندی مجله بدون آیکون بود؛ کارت دسته‌ها در ۴۰۴ خالی بود؛ لوگوی انتخاب‌شده از رسانه نمایش داده نمی‌شد؛ عنوان پرانتزدار هشدار `preg_match` می‌داد | `beryon_svg()` چاپ می‌کند و چیزی برنمی‌گرداند؛ متغیر `categories` به‌جای `cats`؛ نماها فقط `general.logo_file` را می‌خواندند؛ `\u{…}` در PCRE نامعتبر است | `beryon_svg_html()` + متن `sr-only`؛ اصلاح نام متغیر؛ `beryon_logo_tag()` با حفظ نسبت تصویر؛ الگوی `\x{…}` |
| ۱۶ | متن و چکیده نوشته رمزدار در کارت‌ها، جست‌وجو، سربرگ صفحه و متای سئو دیده می‌شد | `post_content`/`post_excerpt` مستقیم خوانده می‌شد | `post_password_required()` در همه مسیرها؛ صفحه نوشته فرم رمز وردپرس را نشان می‌دهد |
| ۱۷ | تاریخ‌های پنل به‌شکل «۱۴۰۵/۰۸/۱۹ H:i» چاپ می‌شد؛ صندوق پیام‌ها در هر بارگذاری اخطار `wpdb::prepare` ثبت می‌کرد | `Jalali::format()` نویسه‌های ساعت را نمی‌شناخت؛ `prepare()` بدون جای‌نگهدار صدا زده می‌شد | پشتیبانی `H G i s` به وقت سایت؛ `prepare()` فقط وقتی پارامتر هست |
| ۱۸ | نوشته یا تصویر شاخص تازه تا یک ساعت در صفحه اصلی دیده نمی‌شد؛ دسته حذف‌شده دوباره ساخته می‌شد | کش `beryon_posts_*` هیچ‌جا پاک نمی‌شد؛ `ensure_terms()` در هر درخواست اجرا می‌شد | پاک‌سازی با ذخیره/حذف نوشته و تغییر تصویر شاخص؛ `ensure_terms()` یک بار (Option `beryon_terms_ready`) |
| ۱۹ | — | — | نسخه `style.css` به **1.0.4**؛ `tools/make_patch.py --base` (انتخاب فایل از گیت) و ابزار تازه `tools/make_theme_zip.py`؛ بسته‌های `beryon-patch-1.0.4.zip` و `beryon-wp.zip` |

#### به‌روزرسانی از ۱.۰.۳ به ۱.۰.۴

هر دو راه روی وردپرس آزمایشی اجرا شده است (گزارش ۱.۰.۴، بخش «مسیر ارتقا»).

**راه الف — جایگزینی پوشه قالب (توصیه‌شده):** `beryon-wp.zip` را از «نمایش ←
پوسته‌ها ← افزودن ← بارگذاری پوسته» بارگذاری و «جایگزینی نسخه فعلی با نسخه
بارگذاری‌شده» را انتخاب کنید، یا پوشه را با FTP جایگزین کنید. تنظیمات و محتوا
دست نمی‌خورد. دو نکته:

* وردپرس جایگزینی را فقط وقتی پیشنهاد می‌دهد که نام پوشه داخل zip با نام پوشه
  قالبِ نصب‌شده یکی باشد. پوشه داخل بسته `beryon-wp/` است؛ اگر قالب روی سایت در
  پوشه دیگری است (مثلاً `beryon`)، بسته را با
  `python3 tools/make_theme_zip.py --folder beryon` بسازید. با نام متفاوت، بسته
  به‌صورت قالب دوم نصب می‌شود و جایگاه منوها و لوگو به آن منتقل نمی‌شود.
* جایگزینی، کل پوشه قالب را پاک می‌کند؛ پشتیبان‌های Patch در
  `public/patches/backups` هم حذف می‌شوند (اگر لازمشان دارید پیش‌تر بردارید).

**راه ب — Patch:** `beryon-patch-1.0.4.zip` را از «بریون ← به‌روزرسانی» بارگذاری
کنید. فایل‌ها جایگزین می‌شوند و پشتیبان ساخته می‌شود، ولی **همان درخواست با صفحه
«خطای مهم» (HTTP 500) تمام می‌شود**؛ این خطای خودِ نسخه ۱.۰.۳ است (ردیف ۵ بالا)
و پس از آن دیگر رخ نمی‌دهد. صفحه را دوباره باز کنید. اگر «کلید امضا» تنظیم
شده، پیش از بارگذاری آن را خالی کنید (ردیف ۶).

پس از هر دو راه:

1. یک بار پیشخوان را باز کنید؛ نسخه خودکار به 1.0.4 همگام می‌شود.
2. «بریون ← به‌روزرسانی ← بازسازی اثر انگشت» را بزنید؛ وگرنه همان صفحه هشدار
   «تغییر غیرمجاز در فایل‌های هسته» را برای ۱۰ فایل از ۱۳ فایل پایش‌شده نشان
   می‌دهد (این فایل‌ها در ۱.۰.۴ عوض شده‌اند).
3. اگر پیش‌تر داده نمونه درج کرده‌اید، «بریون ← ابزارها ← اجرای دوباره داده‌های
   نمونه» را یک بار بزنید: منوها ساخته و برگه نخست تنظیم می‌شود و تاریخ
   نوشته‌ها و نام برچسب‌ها ترمیم می‌شود؛ محتوای موجود بازنویسی نمی‌شود.
   برگه‌های تکراری ساخته‌شده با نسخه پیشین (نامک با پسوند `-2`) را دستی حذف
   کنید.

### ۱.۰.۳ — رفع «سایت بی‌استایل»: پایه URL دارایی‌ها + نسخه پویا (گزارش دوم کاربر از سایت زنده)

| # | باگ | ریشه | اصلاح |
|---|---|---|---|
| ۱ | سایت و پنل بدون هیچ استایل قالب؛ تصاویر قالب با URL شکسته `themes/beryon-wppublic/assets/…` (اسلش جاافتاده = ۴۰۴ خاموش) | پایه URL در لایه‌های بالایی (`get_theme_file_uri`) روی نصب زنده رفتاری متفاوت/فیلترشده داشت و فایل `Assets.php` زنده هم کهنه بود | `theme_url()`/`public_url()` حالا مستقیم و قطعی از `trailingslashit(get_template_directory_uri())` ساخته می‌شوند — بدون وابستگی به فیلتر/تابع متغیر؛ امکان جاافتادن اسلش صفر شد |
| ۲ | پنل همیشه «نسخه 1.0.0» نشان می‌داد و بررسی `from_version` پچ بی‌معنی بود | `BERYON_VERSION` در `functions.php` سخت‌کد `'1.0.0'` بود | نسخه از سربرگ `style.css` خوانده می‌شود (منبع حقیقت) + `Updater::sync_version_option()` در `admin_init` گزینه نسخه را خودکار با فایل‌ها همگام می‌کند |
| ۳ | — | — | نسخه `style.css` به **1.0.3** |

### ۱.۰.۲ — رفع خطای مرگبار Parse + Warningهای پنل (گزارش کاربر از سایت زنده، PHP 8.4)

| # | باگ | ریشه | اصلاح |
|---|---|---|---|
| ۱ | خطای مرگبار `syntax error, unexpected identifier "utf"` در `app/Template.php:148` | رشته دوتایی‌گیومه صفحه اضطراری با گیومه داخلی escape‌نشده (`"<meta charset="utf-8">"`) و newline خام — ساخته اسکریپت پچ ۱.۰.۱ | بازنویسی بلوک بازگشت با رشته تک‌گیومه و escape صحیح (`'<' . 'meta charset="utf-8">' . '>'` و `"\n"`). نکته تلخ: همین صفحه مسئول نمایش خطاهاست، پس خرابی‌اش = صفحه سفید |
| ۲ | `Warning: Undefined array key "name"` در `SettingsPage.php:190` و `:830` | ردیف‌های `type=heading` در SettingsSchema کلید `name` ندارند | `$field['name'] ?? ''` + `continue` در `sanitize()` (دیگر کلید خالی در options ذخیره نمی‌شود) و guard در `field()`؛ خواندن `$value` هم به بعد از early-return منتقل شد |
| ۳ | ابزارهای QA این کلاس باگ را نمی‌گرفتند | لینترها توازن آکولاد/تگ را می‌سنجیدند، نه سلامت رشته‌ها | ابزار جدید `tools/php_syntax_check.py`: lexer ایستای PHP (رشته شکسته، توازن پرانتز، رشته/کامنت بسته‌نشده، nullable ضمنی deprecated در 8.4) — به دروازه QA با `--strict` اضافه شد؛ خودآزمون: الگوی خراب را FAIL و SQL چندخطی سالم را PASS می‌کند |
| ۴ | — | — | نسخه `style.css` به **1.0.2**؛ پچ `beryon-patch-1.0.2.zip` |

### ۱.۰.۱ — رفع صفحه سفید و بی‌استایل بودن پنل

پس از گزارش «صفحه اصلی کاملاً سفید» و «بی‌استایل بودن تنظیمات قالب»، ریشه‌ها
پیدا و اصلاح شد. همه موارد با ابزارهای خودکار (بخش ۹) راستی‌آزمایی شده‌اند.

| # | فایل | ایراد | اصلاح |
|---|---|---|---|
| ۱ | `app/MiniBlade.php` | در `evaluate()` متغیر `$__php` پیش از `eval('?>' . $__php)` با `unset()` پاک می‌شد → **خروجی همه نماها رشته خالی** = صفحه سفید | کد کامپایل‌شده در `$__beryonCode` نگه داشته می‌شود |
| ۲ | `app/MiniBlade.php` | `@endphp` به `<?php ` کامپایل می‌شد → `<?php … <?php` = خطای نحوی | به `?>` اصلاح شد |
| ۳ | `app/MiniBlade.php` | `@endforelse` → `endforeach;` درحالی‌که `@empty` شاخه `else:` می‌سازد → خطای نحوی | به `endif;` اصلاح شد |
| ۴ | `app/MiniBlade.php` | `@forelse ($items as $k => $v)` (فرم استاندارد Blade) به `foreach` ساده تبدیل و `@empty` بی‌`if` رها می‌شد | هر دو فرم `as` و سه‌بخشی پشتیبانی می‌شود؛ متغیر موقت یکتا برای حلقه‌های تودرتو |
| ۵ | `app/MiniBlade.php` | خطاها بلعیده می‌شدند (`return ''` وقتی `WP_DEBUG` خاموش است) | ثبت در `error_log` + نمایش برای مدیر + انتقال به صفحه اضطراری |
| ۶ | `app/MiniBlade.php` | `eval()` در فضای نام `App` اجرا می‌شد → `App\Products` به `App\App\Products` تبدیل می‌شد | کامپایل در فایل حافظه نهان (`wp-content/cache/beryon-views`) و `include` در فضای نام سراسری + گارد `ABSPATH` |
| ۷ | `app/Assets.php` | `wp_style_add_data(…, 'rtl', 'replace')` در سایت فارسی `app-rtl.css` ناموجود را جایگزین `app.css` می‌کرد → **۴۰۴ و حذف کل استایل سایت** | حذف شد (طراحی قالب ذاتاً RTL است) |
| ۸ | `app/Assets.php` | شاخه manifest وجود فایل را بررسی نمی‌کرد؛ اگر `public/assets` خالی بود، CSS/JS پنل و سایت ۴۰۴ می‌شد | بررسی `is_readable` + fallback فایل خام + **استایل/اسکریپت درون‌خطی اضطراری** |
| ۹ | `resources/css/admin.css` | کارت‌های آمار و نوار فیلتر «صندوق پیام‌ها» قاعده CSS نداشتند | `.beryon-stat-row/.beryon-stat(--warn/--ok/--brand)`, `.beryon-filters`, `.beryon-form` + واکنش‌گرایی |
| ۱۰ | `app/Admin/SettingsSchema.php` | `declared()` از `all()` می‌خواند و به دلیل محافظ بازگشت همیشه خالی برمی‌گشت → تب «پیشرفته» **همه کلیدها را تکرار می‌کرد** | خواندن مستقیم از سازنده‌های تب (`tabMethods()`) |
| ۱۱ | `app/Template.php` + `functions.php` | هیچ لایه‌ای خطای مرگبار را گزارش نمی‌کرد (صفحه سفید بدون سرنخ) | صفحه اضطراری معتبر HTML (با `wp_head`/`wp_footer`) + `beryon_fatal_guard()` روی هوک `shutdown` |
| ۱۲ | `functions.php` | `str_contains`/`str_starts_with` و `match()` فقط در PHP ۸ هستند → صفحه سفید روی PHP ۷.۴ | polyfill + تبدیل `match` به `if` → حداقل نسخه **PHP 7.4** |
| ۱۳ | `app/helpers.php` | `beryon_view()` تابع ناموجود `App\view()` را صدا می‌زد → خطای مرگبار در حلقه ووکامرس | به `\App\Acorn::view()` اصلاح شد |
| ۱۴ | نماها | `{{ beryon_pagination() }}` و `{{ beryon_amp(…) }}` → چاپ برچسب‌ها به‌صورت متن و `R&amp;amp;D` در چارت سازمانی | به `{!! !!}` اصلاح شد (۷ مورد) |
| ۱۵ | نماها | نام کلاس نسبی (`App\Products`, `WP_Term`) در مسیر `eval` شکسته می‌شد | پیشوند `\` در ۱۴ نما |
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

## ۱۳) تیم

`@codex` (معماری/کد) · `@themos` (وردپرس/ووکامرس) · `@wooger` (UI/UX و
فرانت) · `@securx` (امنیت/بهینه‌سازی) — مطابق `AGENTS.md`: خلاصه ۵ خطی +
بخش «باز» در هر تحویل، و سند هر ادعا.

مستندهای تیم در `beryon-docs/`: `benchmark-matrix.md`، `qa-report.md`،
`wp-conversion-plan.md`، `uiux-full-review.md`، `web-team-review.md`،
`fe-review.md`.

**مجوز:** GPL-3.0-or-later
