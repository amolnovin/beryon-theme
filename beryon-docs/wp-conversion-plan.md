# طرح تبدیل قالب بریون به قالب وردپرس — ایجنت تیموس (@themos)
ورودی: قالب استاتیک `beryon/` · هدف: قالب کلاسیک سفارشی، سبک و سئوشده برای beryon.com (بدون FSE، برای سازگاری با هاست و افزونه‌های فعلی)

## خلاصه
ساختار استاتیک ۱:۱ به قالب کلاسیک نگاشت شد: partialsها به `header.php/footer.php`، هر صفحه به یک template، و محصولات به CPT موجود سایت با متاهای درصد گوشت/وزن/بسته‌بندی. فرم‌ها با nonce و admin-post پیاده می‌شوند تا به افزونه فرم‌ساز وابسته نباشیم. JSON-LD و breadcrumbها از توکن‌های فعلی به توابع PHP تبدیل می‌شوند. برآورد: ۱۸ فایل قالب + ۴ فایل inc؛ بدون Page Builder.

## ساختار پیشنهادی قالب
```
beryon-theme/
├── style.css               ← هدر قالب + کل CSS فعلی (assets/css/style.css)
├── functions.php           ← enqueue، منوها، theme-support، ثبت CPT/Tax (guard شده)
├── header.php / footer.php ← از _src/partials (منو با wp_nav_menu + walker دو سطحی)
├── front-page.php          ← صفحه اصلی (بخش‌ها به get_template_part تفکیک: hero, cats, features, about, stats, featured, certs, blog, cta)
├── archive-product.php     ← products.html + نوار فیلتر (چیپ‌ها از termها، فیلتر سمت JS با data-attr)
├── taxonomy-product_cat.php← صفحات دسته (توضیح term + گرید + OTHER_CATS از termهای هم‌سطح)
├── single-product.php      ← product-single.html (گالری از gallery-post، مشخصات از meta، تب‌ها،.related از هم‌دسته‌ها)
├── single.php + sidebar.php← blog-single.html (سایدبار ویجت‌پذیر)
├── home.php                ← blog.html (لوپ مقالات)
├── page-about.php / page-ceo.php / page-org.php / page-certs.php
├── page-feedback.php / page-contact.php / page-agency.php  ← فرم‌ها با admin-post + nonce + کد پیگیری (meta ذخیره می‌شود)
├── page-catalog.php
├── template-parts/         ← card-product.php, card-blog.php, card-cat.php, page-hero.php, breadcrumb.php
├── inc/jsonld.php          ← خروجی JSON-LD (Organization/Breadcrumb/Product/Article) مطابق توکن‌های فعلی
├── inc/forms.php           ← اعتبارسنجی سروری + ثبت درخواست/پیام در CPT داخلی `bry_request`
└── assets/                 ← js/main.js (بدون تغییر) + img/
```

## نگاشت محتوا و سئو
1. CPT: اگر `product` موجود نبود ثبت شود؛ taxonomy `product_cat` با slugs فعلی (سوسیس، ژامبون، کالباس، محصولات-انجمادی، محصولات-گیاهی).
2. متاهای محصول: `_meat_percent`، `_unit_weight`، `_packing`، `_shelf_life`، `_tags` → همان بج‌ها و جدول مشخصات.
3. canonical و OG از توابع قالب؛ sitemap و robots از افزونه سئوی موجود (Rank Math/Yoast) — خروجی JSON-LD قالب با فیلتر قابل غیرفعال‌سازی.
4. ریدایرکت ۳۰۱ اسلاگ‌های قدیمی (/product/... و /productall/...) در inc/redirects.php نگه داشته شود.

## باز
1. سایت فعلی WooCommerce دارد یا CPT سفارشی؟ (پاسخ، ثبت CPT در functions.php را قطعی می‌کند؛ تا آن زمان guard شده است.)
2. فرم‌ها به دیتابیس سایت برسند یا به ایمیل/CRM؟ پیش‌فرض طرح: ذخیره در CPT داخلی + ایمیل به info@beryon.com.
3. در صورت تمایل کارفرما به گوتنبرگ/FSE، نسخه جایگزین با theme.json توسط همین ایجنت قابل ارائه است (هزینه زمانی بیشتر).
