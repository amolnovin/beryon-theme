# گزارش ساخت قالب وردپرس بریون (Sage 11) — فاز ۲ و ۳ کامل

> **به‌روزرسانی ۱۴۰۵/۰۸/۱۲:** پس از گزارش کاربر (صفحه اصلی سفید و پنل بی‌استایل)،
> نسخه **۱.۰.۱** منتشر شد. ریشه‌ها: `unset($__php)` در `MiniBlade::evaluate()`،
> کامپایل نادرست `@endphp`/`@endforelse`/`@forelse`، و `wp_style_add_data(rtl, replace)`.
> گزارش کامل: `beryon-docs/wp-1.0.1-bugfix-report.md`
>
> **به‌روزرسانی دوم (همان روز):** پس از نصب ۱.۰.۱ روی سایت زنده (PHP 8.4)، خطای
> `Parse error ... Template.php:148` گزارش شد — رشته شکسته در صفحه اضطراری، ساخته
> اسکریپت پچ. نسخه **۱.۰.۲** منتشر شد (۴ فایل؛ ابزار جدید `php_syntax_check.py`
> به دروازه QA اضافه شد). الحاقیه کامل در همان گزارش بالا.

**تاریخ:** ۱۴۰۵/۰۸/۰۵ (2026-10-27) — **مسیر قالب:** `beryon-wp/` — **بسته تحویل:** `beryon-wp.zip` (۴.۷MB، ۱۵۶ فایل)

## خلاصه ۵ خطی

1. همه **۱۸ صفحه HTML نهایی** به **۳۶ نمای Blade** نگاشت شدند و هارنس خودکار
   `tools/parity_check.py --strict` تأیید می‌کند که **۱۸/۱۸ صفحه هم‌ارز**اند
   (هیچ `href="*.html"`، هیچ `src="assets/…"` و هیچ کلاس CSS جامانده).
2. لایه دارایی‌ها کامل شد: `resources/{css,js}` + فونت خودمیمان وزیرمتن
   (۵ وزن woff2 با `unicode-range`) + بیلد آفلاین `tools/build_assets.py`
   که `public/assets/{css,js,fonts,images}` و `manifest.json` را می‌سازد
   (app.css = ۵۰KB، app.js = ۲۰KB، بدون jQuery و بدون CDN).
3. پنل تنظیمات با تب خودکار **«پیشرفته»** تکمیل شد: هر کلید
   `config/defaults.json` که فیلد صریح نداشت (۴۲ کلید: breadcrumbها،
   پیام‌های موفقیت فرم‌ها، متن‌های آرشیو، فهرست استان‌ها) اکنون از
   `SettingsSchema::advanced()` به‌صورت خودکار قابل ویرایش است.
4. سه ابزار QA خودکار اضافه شد و همه سبزند: `php_lint.py`
   (**۱۰۰ فایل PHP/Blade بدون خطا**)، `parity_check.py` (**۱۸/۱۸**)،
   `api_check.py` (**۱۴۹ فراخوانی نما → همه به API موجود ارجاع می‌دهند**).
5. یک **باگ واقعی در CSS قالب HTML نهایی** پیدا و رفع شد: ۳ قاعده به شکل
   `…-webkit-backdrop-filter:blur(3px)};backdrop-filter:blur(3px)}` نوشته شده
   بودند (۳ آکولاد اضافه → بی‌اعتبارشدن قاعده بعدی)؛ در
   `beryon/assets/css/style.css` و `beryon-wp/resources/css/beryon.css`
   اصلاح و `beryon-final.zip` دوباره ساخته شد (تعداد آکولاد حالا ۵۰۴/۵۰۴).

---

## آنچه در این فاز ساخته شد (فهرست فایل‌ها)

### نماهای Blade (جدید در این فاز)

| فایل | منبع | نکته |
|---|---|---|
| `resources/views/front-page.blade.php` | `index.html` | با ترنسفورمر `tools/make_front_page.py`؛ همه ۹ بخش (هیرو، دسته‌ها، مزیت‌ها، درباره، آمار، ویژه‌ها، مارکی گواهینامه، وبلاگ، فراخوان) |
| `resources/views/single-product.blade.php` | `product-single.html` | گالری + بندانگشتی‌ها + لایت‌باکس + نشان‌ها + اشتراک‌گذاری + ۳ تب + مرتبط‌ها |
| `resources/views/woocommerce/single-product/spec-table.blade.php` | — | جدول مشخصات داده‌محور از `products.default_spec_rows` |
| `resources/views/page-about.blade.php` | `about.html` | پاراگراف‌ها، چک‌لیست و گاه‌شمار حلقه‌شده |
| `resources/views/page-ceo.blade.php` | `ceo.html` | تصویر `ceo-photo.png` با استایل درون‌خطی عیناً حفظ شد |
| `resources/views/page-org-chart.blade.php` | `org-chart.html` | درخت سازمانی از `org.units` (با زیرشاخه) + شرح وظایف |
| `resources/views/page-certificates.blade.php` | `certificates.html` | دو گرید (بین‌المللی/ملی) با پیمانه ۴ برای `d1/d2/d3` |
| `resources/views/page-contact-us.blade.php` | `contact.html` | کارت‌های تماس + نقشه + فرم REST با `name`های صحیح |
| `resources/views/page-feedback.blade.php` | `feedback.html` | فرم + رادیو-پیچ‌ها + آکاردئون پرسش‌ها |
| `resources/views/page-agency.blade.php` | `agency.html` | ۴ گام + فرم ۴ بخشی + ۳۱ استان از `config/provinces.json` |
| `resources/views/page-catalog.blade.php` | `catalog.html` | جلد کاتالوگ + فهرست دسته‌ها با **شمارش واقعی** + دکمه PDF شرطی |
| `resources/views/home.blade.php` + `index.blade.php` | `blog.html` | کارت‌ها از نوشته‌های DB با تاریخ شمسی + خبرنامه |
| `resources/views/partials/blog-list.blade.php` | — | بدنه مشترک فهرست وبلاگ (برای home و archive) |
| `resources/views/single.blade.php` | `blog-single.html` | متا (تاریخ/نویسنده/زمان مطالعه) + محتوا + سایدبار |
| `resources/views/archive.blade.php` | — | بایگانی دسته/برچسب/تاریخ با همان بدنه مشترک |
| `resources/views/search.blade.php` | — | نتایج (محصول + نوشته) با کلاس‌های موجود قالب |
| `resources/views/404.blade.php` | — | جست‌وجو + دسترسی سریع + دسته‌ها + ویژه‌ها + آخرین نوشته‌ها |
| `resources/views/woocommerce/{cart,checkout,myaccount/my-account}.blade.php` | — | هوک‌های ووکامرس داخل پوسته بصری بریون |

### دارایی‌ها

* `resources/css/app.css` (نقطه ورود: `beryon.css` + `wp.css`)، `wp.css`
  (۸ بخش: هانی‌پات، فرم جست‌وجو، چیپ لینک‌دار، صفحه‌بندی، پیام REST،
  ووکامرس، ویرایشگر، دسترس‌پذیری/واکنش‌گرایی)، `fonts.css`، `admin.css`،
  `editor.css`
* `resources/js/app.js` (لایه وردپرس: ارسال REST با nonce، کد پیگیری واقعی،
  خطای فیلد، نرخ محدود، fallback آفلاین، کپی لینک، چیپ لینک‌دار، انیمیشن
  لود تصویر، اسکرول نرم با آفست هدر، `rel` امن) + `admin.js` (ردیف‌ساز،
  `wp.media`، همگام‌سازی رنگ، AJAX بررسی/یکپارچگی/بازگردانی، کنترل اندازه
  فایل پچ) + `main.js` بدون تغییر
* `resources/fonts/` و `public/assets/fonts/` → وزیرمتن ۳۳.۰۰۳
  (Regular/Medium/SemiBold/Bold/Black)
* خروجی بیلد: `public/assets/css/{app,fonts,admin,editor}.css`،
  `public/assets/js/{app,admin}.js`، `public/assets/manifest.json`

### PHP

* `app/helpers.php`: `beryon_latest_posts`, `beryon_post_card`,
  `beryon_read_minutes`, `beryon_query_posts`, `beryon_pagination`,
  `beryon_blog_url`, `beryon_post_content`, `beryon_initial`, `beryon_amp`,
  `beryon_nth_word`, `beryon_product_gallery`, `beryon_product_crumb`,
  `beryon_form_url`, `beryon_provinces`, `beryon_card_href`
* `app/Settings.php`: `json()` (خواندن هر فایل `config/*.json` با کش)
* `app/Products.php`: `lines_to_array()` + `type_label` در `normalize()`
* `app/Template.php`: نامزد `single` برای نوشته + `is_woo_route()`
* `app/Setup.php`: `template_include` حالا مسیرهای ووکامرس را هم به
  `index.php` قالب برمی‌گرداند (تا مارک‌آپ بریون حفظ شود)
* `app/Assets.php`: تفکیک فونت خودمیمان از Google Fonts بر اساس
  `performance.fonts_self_host`
* `app/Admin/SettingsSchema.php`: تب خودکار `advanced` + `declared()` +
  `advanced_labels()`

### داده و مستندات

* `config/provinces.json` (۳۱ استان)
* `config/defaults.json`: کلیدهای جدید `catalog.*`, `*.crumb`,
  `contact.success_*`, `agency.{crumb,agree_text,cta_*}`, `blog.{crumb,sr,empty,author_box_title}`,
  گروه کامل `blog_archive`, `products.default_keep`, `ceo.photo*`,
  `forms.provinces`؛ `og_image` به `hero.webp`
* `config/icons.json`: ۴ نام‌گذاری معنایی جدید (`clipboard`, `card`,
  `org-tree`, `cert-2`) → **۷۳ آیکون**
* `README.md` قالب (۱۲ بخش: نصب، ساختار، نگاشت صفحات، پنل، Patch، سئو،
  کارایی، امنیت، QA، wp-cli، نکته‌های نگهداری، تیم)
* `docs/PATCH_SPEC.md` (۱۲ بخش: قرارداد کامل بسته، مانیفست، محدودیت‌های
  امنیتی، SQL، چرخه اعمال، امضای HMAC، فید، تولید بسته، گزارش‌گیری،
  چک‌لیست انتشار، عیب‌یابی)
* `public/patches/patch.sample.json` (نمونه مرجع با sha256 واقعی)
* `screenshot.png` (برای نمایش در پیشخوان وردپرس)

### ابزارها (`/home/user/tools/`)

`make_pages.py` (ترنسفورمر عمومی صفحه→Blade با جایگزینی کنترل‌شده)،
`specs_about_group.py`، `specs_forms.py`، `specs_blog.py`، `specs_single.py`،
`build_assets.py` (بیلد آفلاین CSS/JS + manifest)، `parity_check.py`،
`api_check.py`، `php_lint.py` (اصلاح شمارش `@empty` داخل `@forelse`)

---

## شواهد (خروجی واقعی ابزارها)

```
$ python3 tools/php_lint.py beryon-wp
بررسی شد: ۱۰۰ فایل
همه بررسی‌ها پاس شد ✔

$ python3 tools/parity_check.py --strict
OK  about.html           → page-about.blade.php             کلاس ۲۶/۲۷
OK  agency.html          → page-agency.blade.php            کلاس ۲۷/۳۰
OK  blog-single.html     → single.blade.php                 کلاس ۲۲/۲۲
OK  blog.html            → home.blade.php                   کلاس ۲۱/۲۳
OK  cat-frozen.html      → taxonomy-product_cat.blade.php   کلاس ۲۰/۲۱
OK  cat-jambon.html      → taxonomy-product_cat.blade.php   کلاس ۱۹/۲۰
OK  cat-kalbas.html      → taxonomy-product_cat.blade.php   کلاس ۲۰/۲۱
OK  cat-plant.html       → taxonomy-product_cat.blade.php   کلاس ۱۹/۲۰
OK  cat-sausage.html     → taxonomy-product_cat.blade.php   کلاس ۲۰/۲۱
OK  catalog.html         → page-catalog.blade.php           کلاس ۲۱/۲۱
OK  ceo.html             → page-ceo.blade.php               کلاس ۲۰/۲۲
OK  certificates.html    → page-certificates.blade.php      کلاس ۱۸/۲۱
OK  contact.html         → page-contact-us.blade.php        کلاس ۲۴/۲۴
OK  feedback.html        → page-feedback.blade.php          کلاس ۳۰/۳۰
OK  index.html           → front-page.blade.php             کلاس ۵۴/۵۷
OK  org-chart.html       → page-org-chart.blade.php         کلاس ۱۷/۱۹
OK  product-single.html  → single-product.blade.php         کلاس ۳۲/۳۲
OK  products.html        → archive-product.blade.php        کلاس ۱۶/۱۷
نماهای بررسی‌شده: ۳۶ | صفحات استاتیک: ۱۸
همه بررسی‌های هم‌ارزی پاس شد ✔

$ python3 tools/api_check.py
نماهای بررسی‌شده: ۳۶ | فراخوانی‌ها: ۱۴۹ | توابع قالب: ۴۴ | کلاس‌ها: ۲۴
همه فراخوانی‌های نماها به API موجود ارجاع می‌دهند ✔

$ python3 tools/build_assets.py
CSS app.css      :  ۵۰۰۰۶ B  (خام ۵۵۳۱۶ B، کاهش ۱۰٪)
CSS fonts.css    :   ۱۶۲۹ B
CSS admin.css    :   ۷۸۶۰ B
CSS editor.css   :   ۱۲۵۰ B
JS  app.js       :  ۲۰۳۵۴ B
JS  admin.js     :  ۱۲۴۴۹ B
فونت‌ها: ۵ فایل | تصاویر: ۱۲ فایل
manifest.json    : ۶ ورودی
بیلد دارایی‌ها موفق ✔

$ node --check public/assets/js/app.js   → OK
$ node --check public/assets/js/admin.js → OK
$ node --check resources/js/main.js      → OK
```

---

## بخش باز (Open)

1. **اجرای واقعی در وردپرس:** در این سندباکس `php`/`composer` نصب نیست
   (و `apt` بدون root ممکن نیست)، بنابراین هیچ‌گاه رندر واقعی Blade اجرا
   نشده. همه بررسی‌ها ایستا است. روی میزبان باید:
   `composer install --no-dev` → فعال‌سازی قالب → `wp beryon seed` →
   `wp beryon doctor` → بازدید ۱۸ صفحه.
2. **بیلد Vite واقعی:** خروجی‌ها با `tools/build_assets.py` (فشرده‌سازی
   محافظه‌کار) ساخته شده‌اند، نه `vite build`. اگر میزبان `npm install`
   دارد، `npm run build` را اجرا کنید تا PostCSS/autoprefixer/Tailwind هم
   اعمال شود؛ نام خروجی‌ها یکسان است (`css/app.css`، `js/app.js`، …).
3. **تست مرورگری/Playwright:** در این نشست Playwright در دسترس نبود؛
   بررسی‌ها DOM/متنی است. تست بصری (موبایل ۳۶۰px، تبلت، دسکتاپ) و
   Core Web Vitals واقعی هنوز مانده است.
4. **ناسازگاری محتوای استاتیک با داده:** متن‌های قالب HTML «۶۱ محصول» و
   کاتالوگ «۱۶/۱۳/۳/۲۲/۷» را تبلیغ می‌کنند اما داده واقعی **۴۳ محصول**
   (۱۲/۱۰/۲/۱۳/۶) است. در قالب وردپرس همه‌جا با توکن `{count}` و شمارش
   واقعی پر می‌شود، پس خروجی سایت وردپرسی با متن‌های قدیمی فرق دارد
   (عمدی). اگر باید دقیقاً «۶۱» نمایش داده شود، باید ۱۸ محصول دیگر
   seed شود.
5. **باگ CSS در بسته HTML نهایی:** `beryon-final.zip` بازسازی شد (رفع ۳
   آکولاد اضافه) اما **بازبینی بصری مجدد** آن سه قاعده
   (`.mobile-menu .overlay`، `.blog-card .date`، `.book .cover .yr`) برای
   اطمینان از ظاهر `backdrop-filter` انجام نشده.
6. **`blog-3.webp` هنوز کپی بایت‌به‌بایت `cat-frozen.webp` است** (دو مقاله
   تصویر یکسان دارند)؛ نیاز به تولید تصویر اختصاصی دارد.
7. **غلط‌های تایپی منبع که عمداً حفظ/رفع شدند:** در `feedback.html` سؤال
   سوم با «/how» شروع می‌شد → در `feedback.faqs` اصلاح شد؛ تاریخ کارت
   چهارم وبلاگ «۱۴۴/۰۵/۰۶» بود → در `blog.json` تاریخ شمسی درست شد؛
   کد پیگیری نمونه «BRY-۱۴۰-۰۰۰۰» در قالب HTML ثابت بود → در Blade خالی
   است و JS مقدار واقعی REST را می‌گذارد.
8. **ووکامرس:** نماهای `cart`/`checkout`/`myaccount` فقط هوک‌های اصلی
   ووکامرس را داخل پوسته بریون صدا می‌زنند؛ چون سایت «فروش ندارد»
   (حالت کاتالوگ/استعلام) تست سبد و پرداخت انجام نشده و CSS اختصاصی
   جدول سبد/فرم پرداخت هم کامل نیست (فقط قواعد پایه در `wp.css`).
9. **امضای Patch:** `updater.signature_key` خالی است، پس امضا اختیاری
   است. برای انتشار عمومی باید کلید تولید و در `wp-config.php`/تنظیمات
   قرار گیرد (نه در مخزن).
10. **فید به‌روزرسانی:** `updater.feed_url` خالی و `allow_remote=false`؛
    برای به‌روزرسانی خودکار باید فید JSON (بند ۸ `docs/PATCH_SPEC.md`)
    روی سرور منتشر شود.
11. **`resources/images` و `public/assets/images` هر دو در zip هستند**
    (≈۱.۹MB تکراری) تا بیلد در میزبان بدون منبع شکست نخورد؛ اگر حجم بسته
    مهم است، `resources/images` را از بسته انتشار حذف کنید.
12. **ترجمه/`beryon.pot`:** اسکریپت `npm run translate` تعریف شده اما
    فایل POT تولید نشده (نیاز به `wp-cli i18n`).
