# ساختار استاندارد بسته به‌روزرسانی (Patch) قالب بریون

**نسخه سند:** ۱.۰ — **قالب:** بریون (Sage 11) — **فرمت Patch:** `1`

این سند قرارداد کامل بسته‌های به‌روزرسانی قالب بریون را تعریف می‌کند؛ همان
قراردادی که `app/Updater/PatchApplier.php` اعتبارسنجی و اعمال می‌کند و
`app/Updater.php::create()` و دستور `wp beryon patch build` تولید می‌کنند.

---

## ۱) چرا Patch و نه آپلود کامل قالب؟

| روش | مزیت | محدودیت |
|---|---|---|
| آپلود zip کامل قالب از پیشخوان وردپرس | ساده | همه فایل‌ها بازنویسی می‌شوند؛ تغییرات محلی سایت از بین می‌رود؛ حجم بالا؛ بدون گزارش دقیق |
| **Patch (این روش)** | فقط فایل‌های تغییرکرده؛ پشتیبان‌گیری خودکار از فایل‌های دست‌خورده؛ بازگردانی خودکار در صورت خطا؛ گزارش و تاریخچه؛ امکان Dry-run؛ کنترل نسخه PHP/وردپرس/قالب | نیاز به تولید بسته مطابق این قرارداد |

Patch در این قالب **جایگزین** به‌روزرسانی کامل وردپرس نیست؛ برای انتشارهای
کوچک و متوسط (رفع باگ، تغییر متن پیش‌فرض، بهینه‌سازی CSS/JS، افزودن نما)
طراحی شده است.

---

## ۲) ساختار فایل بسته

```
patch-1.1.0.zip
├── patch.json              ← اجباری: مانیفست تغییرات (UTF-8، بدون BOM)
├── signature.txt           ← اختیاری: HMAC-SHA256 مانیفست (بند ۷)
└── files/                  ← اجباری: فایل‌های جدید با همان ساختار نسبی قالب
    ├── app/Seo.php
    ├── resources/css/wp.css
    └── public/assets/css/app.css
```

قواعد:

* مسیرها **نسبی به ریشه قالب** هستند (نه ریشه وردپرس): `app/…`، `resources/…`،
  `config/…`، `public/assets/…`، `style.css`.
* هرگز مسیر مطلق، `..`، یا پیوند نمادین مجاز نیست
  (`PatchApplier::safe_path()` با `realpath`/`strpos` جلوی Path Traversal را می‌گیرد).
* پوشه `files/` در بسته می‌تواند خالی باشد (Patch فقط SQL یا فقط متاداده)،
  اما کلید `files` در مانیفست باید وجود داشته باشد.

---

## ۳) مانیفست `patch.json`

```json
{
  "format": 1,
  "theme": "beryon",
  "id": "beryon-1.1.0-20261027",
  "version": "1.1.0",
  "from_version": "1.0.0",
  "channel": "stable",
  "label": "رفع باگ سئو و بهینه‌سازی CSS",
  "notes": "۱) اصلاح canonical در صفحه دسته محصول\n۲) فشرده‌سازی app.css",
  "author": "تیم بریون",
  "released_at": "2026-10-27T09:30:00+03:30",
  "requires": {
    "php": "8.0",
    "wordpress": "6.3",
    "theme_min": "1.0.0"
  },
  "files": [
    {
      "path": "app/Seo.php",
      "from": "files/app/Seo.php",
      "op": "copy",
      "sha256": "9f2c…",
      "size": 34812,
      "mode": "0644"
    },
    {
      "path": "resources/css/legacy.css",
      "op": "delete"
    },
    {
      "path": "public/patches/staging",
      "op": "mkdir"
    }
  ],
  "sql": [
    "UPDATE wp_options SET option_value = '1.1.0' WHERE option_name = 'beryon_theme_version'"
  ],
  "post_apply": ["flush_rewrites", "clean_caches", "refingerprint"]
}
```

### ۳-۱) کلیدهای اجباری

| کلید | نوع | توضیح | رفتار در صورت خطا |
|---|---|---|---|
| `format` | int | باید دقیقاً `1` باشد | رد بسته: «نسخه ساختار Patch پشتیبانی نمی‌شود» |
| `theme` | string | باید دقیقاً `beryon` باشد | رد بسته: «این Patch برای قالب بریون ساخته نشده است» |
| `version` | string | نسخه جدید (SemVer) | رد بسته |
| `files` | array | فهرست عملیات فایل (می‌تواند خالی باشد) | رد بسته |

### ۳-۲) کلیدهای اختیاری

| کلید | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `id` | string | `theme-version` | شناسه یکتا؛ در تاریخچه ثبت می‌شود |
| `from_version` | string | — | اگر تنظیم شود و با نسخه نصب‌شده تفاوت داشته باشد، هشدار (و در حالت سخت‌گیرانه، رد) |
| `channel` | string | `stable` | `stable` \| `beta` \| `dev` — با `updater.channel` سایت مقایسه می‌شود |
| `label`, `notes`, `author`, `released_at` | string | — | برای نمایش در پنل «به‌روزرسانی» |
| `requires.php` | string | — | حداقل نسخه PHP (مقایسه با `PHP_VERSION`) |
| `requires.wordpress` | string | — | حداقل نسخه وردپرس |
| `requires.theme_min` | string | — | حداقل نسخه قالب نصب‌شده |
| `sql` | array\<string\> | `[]` | دستورهای SQL مجاز (بند ۵) |
| `post_apply` | array\<string\> | خودکار | بند ۶ |
| `signature` | string | — | HMAC مانیفست (بند ۷) |

### ۳-۳) هر رکورد `files[]`

| کلید | اجباری | توضیح |
|---|---|---|
| `path` | بله | مسیر نسبی در قالب؛ باید از `safe_path()` و allowlist عبور کند |
| `op` | خیر | `copy` (پیش‌فرض) \| `delete` \| `mkdir` |
| `sha256` | برای `copy` بله | hash فایل داخل `files/…`؛ پس از نوشتن دوباره بررسی می‌شود |
| `from` | **بله** (برای `op:copy`) | مسیر منبع **داخل بسته**، همیشه با پیشوند `files/` (مانند `files/app/Seo.php`). `PatchApplier::validate_manifest()` نبود این پیشوند را رد می‌کند. تشخیص دست‌کاری محلی فایل مقصد از راه `sha256` پشتیبان‌گیری و گزارش `conflicts` انجام می‌شود، نه این کلید |
| `size` | خیر | اندازه بایت؛ برای بررسی سقف `updater.max_file_size` |
| `mode` | خیر | مجوز فایل مثل `0644` (فقط اگر `chmod` مجاز باشد) |

---

## ۴) محدودیت‌های امنیتی (از تنظیمات قالب)

| تنظیم | پیش‌فرض | اثر |
|---|---|---|
| `updater.allow_remote` | `false` | دانلود خودکار از فید غیرفعال است مگر فعال شود |
| `updater.feed_url` | `''` | نشانی فید JSON (بند ۸) |
| `updater.patch_slug_allowlist` | `app,resources,config,public/assets,docs,style.css,…` | فقط این مسیرهای ریشه مجازند |
| `updater.patch_ext_allowlist` | `php,css,js,json,md,svg,webp,woff2,txt` | فقط این پسوندها |
| `updater.max_patch_size` | `52428800` (۵۰MB) | سقف حجم zip |
| `updater.max_file_size` | `15728640` (۱۵MB) | سقف حجم هر فایل |
| `updater.backups_keep` | `5` | تعداد پشتیبان‌های نگهداری‌شده |
| `updater.signature_key` | `''` | اگر پر باشد، **امضا اجباری** می‌شود |
| `updater.auto_check` | `true` | بررسی زمان‌بندی‌شده با WP-Cron (`beryon_update_check`) |
| `updater.check_interval` | `daily` | `twicedaily` \| `daily` \| `weekly` |

علاوه بر این‌ها:

* فقط کاربر با توانایی `manage_options` (و دارای nonce معتبر) می‌تواند Patch
  اعمال کند؛ درخواست‌های AJAX با `wp_verify_nonce` بررسی می‌شوند.
* نوشتن فقط با «فایل موقت + `rename` اتمیک» انجام می‌شود تا در صورت قطع
  درخواست، فایل نیمه‌نوشته باقی نماند.
* پوشه کاری (staging) زیر `wp-content/uploads/beryon-updater` است و پس از
  هر اجرا پاک می‌شود (`cleanup_staging()`).

---

## ۵) بخش `sql`

فقط برای مهاجرت‌های کوچک و بی‌خطر. محدودیت‌های سخت:

* مجاز: `UPDATE`, `INSERT`, `DELETE` روی جداول وردپرس با پیشوند سایت،
  `ALTER TABLE … ADD COLUMN`, `CREATE TABLE IF NOT EXISTS`, `REPLACE INTO`.
* **ممنوع** (بسته رد می‌شود): `DROP`, `TRUNCATE`, `RENAME`, `GRANT`,
  چند دستور در یک رشته (`;` میانی)، کامنت SQL، و هر دستور بدون پیشوند.
* پیشوند جدول باید با `$wpdb->prefix` جایگزین شود؛ توصیه می‌شود به‌جای SQL،
  از `post_apply` و توابع PHP قالب استفاده کنید.
* اگر `sql` خالی است، کلید را حذف کنید.

---

## ۶) چرخه اعمال و `post_apply`

```
validate → stage (استخراج در uploads) → verify sha256 همه فایل‌ها
        → build_plan (مقایسه with from) → create_backup
        → apply (کپی اتمیک / حذف / mkdir) → sql → post_apply → log
        ↑ هر خطا → restore_backup خودکار → report
```

مقادیر مجاز `post_apply` (اگر نوشته نشود، خودکار همین سه‌تا اجرا می‌شود):

| مقدار | کار |
|---|---|
| `flush_rewrites` | `flush_rewrite_rules()` — پس از تغییر CPT/Taxonomy/نامک‌ها ضروری است |
| `clean_caches` | پاک‌سازی transientها و کش‌های داخلی قالب (`Settings::flush()`، `Products::flush_cache()`، `Assets::flush_image_cache()`، `Template::flush()`) |
| `refingerprint` | بازمحاسبه اثر انگشت فایل‌ها برای «بررسی یکپارچگی» (`Security::fingerprint()`) |

---

## ۷) امضا (اختیاری ولی توصیه‌شده)

اگر `updater.signature_key` در سایت تنظیم شده باشد، هر بسته **باید** امضا داشته
باشد، وگرنه رد می‌شود («امضای Patch تأیید نشد»).

```bash
# ۱) مانیفست نهایی را در patch.json بگذارید
# ۲) امضا را محاسبه کنید
openssl dgst -sha256 -hmac "$BERYON_SIGN_KEY" patch.json | awk '{print $2}' > signature.txt
```

معادل PHP:

```php
$signature = hash_hmac('sha256', file_get_contents('patch.json'), $key);
```

* الگوریتم: `HMAC-SHA256`، ورودی: **بایت‌های خام فایل `patch.json`**
  (بدون BOM، با همان whitespace). خروجی hex است؛ بزرگ/کوچک‌بودن حروف و
  فاصله یا خط جدید انتهای `signature.txt` نادیده گرفته می‌شود.
* کلید را هرگز در مخزن عمومی یا در بسته قرار ندهید.

### دو شکل پذیرفته‌شده امضا

| شکل | محل امضا | ورودی HMAC | چه چیزی آن را می‌سازد |
|---|---|---|---|
| ۱ | فایل `signature.txt` در ریشه zip | بایت‌های خام `patch.json` | دستور `openssl` بالا؛ برای بسته دست‌ساز و بسته‌ای که `tools/make_patch.py` می‌سازد (آن ابزار خودش امضا نمی‌کند) |
| ۲ | کلید `signature` داخل `patch.json` | مانیفست **بدون** کلید `signature` با قالب‌بندی خود سازنده | `wp beryon patch build` (`Updater::create()`) وقتی کلید امضا تنظیم است |

* در شکل ۲ متن امضاشده دقیقاً خروجی
  `wp_json_encode($manifest_without_signature, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)`
  است (ترتیب کلیدها حفظ می‌شود؛ پیاده‌سازی: `PatchApplier::signing_payload()`).
  برای بسته دست‌ساز شکل ۱ را به‌کار ببرید تا به قالب‌بندی JSON وابسته نشوید.
* اگر `signature.txt` در بسته باشد **فقط همان** بررسی می‌شود؛ کلید `signature`
  تنها در نبود `signature.txt` خوانده می‌شود.
* اگر کلید امضا در سایت خالی باشد امضای بسته بررسی نمی‌شود.
* تا نسخه ۱.۰.۳ هیچ‌کدام از دو شکل تأیید نمی‌شد (`signature.txt` خوانده نمی‌شد و
  امضای داخل مانیفست با متن خامِ دارای همان کلید مقایسه می‌شد). سایتی که روی
  ≤ ۱.۰.۳ است و کلید امضا دارد، برای رسیدن به ۱.۰.۴ باید فایل‌های قالب را
  جایگزین کند (zip کامل یا FTP) یا کلید را موقتاً خالی کند.

---

## ۸) فید به‌روزرسانی (اختیاری)

نشانی JSON که `updater.feed_url` به آن اشاره می‌کند:

```json
{
  "version": "1.1.0",
  "channel": "stable",
  "url": "https://example.com/beryon/patches/patch-1.1.0.zip",
  "sha256": "1f3a…",
  "label": "رفع باگ سئو و بهینه‌سازی CSS",
  "notes": "…",
  "released_at": "2026-10-27T09:30:00+03:30",
  "requires": { "php": "8.0", "wordpress": "6.3", "theme_min": "1.0.0" },
  "auto_install": false
}
```

* `sha256` در سطح فید، hash **کل فایل zip** است و پیش از اعمال بررسی می‌شود.
* `auto_install: true` فقط وقتی مؤثر است که `updater.allow_remote` فعال باشد.
* درخواست فید با `wp_remote_get`، timeout کوتاه و فقط در WP-Cron انجام می‌شود
  تا زمان پاسخ صفحات سایت افزایش نیابد.

---

## ۹) تولید بسته

### با WP-CLI (توصیه‌شده)

```bash
# همه فایل‌های تغییرکرده یک پوشه را بسته‌بندی می‌کند
wp beryon patch build --version=1.1.0 --label="رفع باگ سئو" --dir=app --out=/tmp/patch-1.1.0.zip

# بررسی بسته بدون اعمال
wp beryon patch inspect /tmp/patch-1.1.0.zip

# اجرای آزمایشی (هیچ فایلی نوشته نمی‌شود؛ فقط برنامه تغییرات)
wp beryon patch apply /tmp/patch-1.1.0.zip --dry-run

# اعمال واقعی
wp beryon patch apply /tmp/patch-1.1.0.zip --yes

# فهرست پشتیبان‌ها و بازگردانی
wp beryon patch backups
wp beryon patch rollback beryon-backup-2026-10-27-093000.zip --yes

# وضعیت فید
wp beryon patch feed --check
```

### با PHP (برای خط ساخت CI)

```php
require 'wp-load.php';

$zip = App\Updater::create(
    ['app/Seo.php', 'resources/css/wp.css', 'public/assets/css/app.css'],
    '1.1.0',
    [
        'label'   => 'رفع باگ سئو و بهینه‌سازی CSS',
        'notes'   => "۱) اصلاح canonical\n۲) فشرده‌سازی CSS",
        'channel' => 'stable',
    ]
);

echo $zip; // مسیر فایل zip تولیدشده
```

`Updater::create()` خودش `sha256`، `size`، `from` (مسیر `files/…` داخل بسته) و
`requires` را محاسبه و `files/` را با ساختار صحیح داخل zip می‌گذارد.

---

## ۱۰) گزارش، تاریخچه و بازگردانی

| منبع | محتوا |
|---|---|
| `public/patches/patch-log.jsonl` | یک خط JSON به‌ازای هر تلاش (موفق/ناموفق)، با زمان، کاربر، نسخه، خلاصه برنامه و خطاها |
| option `beryon_patch_log` | ۱۰۰ رکورد آخر همان گزارش (برای نمایش در پنل، حتی اگر فایل لاگ قابل خواندن نباشد) |
| option `beryon_theme_versions` | تاریخچه نسخه‌های اعمال‌شده |
| `public/patches/backups/` | zip پشتیبان فایل‌های جایگزین‌شده (به تعداد `updater.backups_keep`) |
| پنل «بریون ← به‌روزرسانی» | دکمه‌های بررسی، اعمال، بازگردانی، بررسی یکپارچگی + نمایش گزارش |

بازگردانی یک پشتیبان، فایل‌ها را بازمی‌گرداند و در صورت وجود
`from_version` در متادیتای آن، نسخه قالب را هم به حالت قبل برمی‌گرداند
(`PatchApplier::restore_backup($file, true)`).

---

## ۱۱) چک‌لیست انتشار یک Patch

1. نسخه را در `style.css` و `functions.php` (`BERYON_VERSION`) بالا ببرید.
2. اگر `public/assets/*` تغییر کرده، بیلد دارایی‌ها را تازه کنید
   (`npm run build` یا `python3 tools/build_assets.py`).
3. `wp beryon patch build --version=X.Y.Z --dir=…` را اجرا کنید (یا در مخزن:
   `python3 tools/make_patch.py X.Y.Z` که فایل‌ها را از `git diff` نسبت به کامیت
   نسخه پیشین برمی‌دارد).
4. `wp beryon patch inspect` → بررسی کنید `sha256`ها و `requires` درست‌اند.
5. روی سایت آزمایشی: `--dry-run` و سپس اعمال واقعی + تست صفحات کلیدی.
6. اگر امضا فعال است: بسته‌ای که `wp beryon patch build` روی سایتِ دارای کلید
   می‌سازد خودش امضا دارد (کلید `signature`)؛ برای بسته `tools/make_patch.py` یا
   بسته دست‌ساز، `signature.txt` را طبق بند ۷ بسازید و در ریشه zip بگذارید.
7. بسته را در فید منتشر کنید (یا برای نصب دستی در پنل آپلود کنید).
8. در سایت اصلی: `wp beryon integrity` و `wp beryon doctor` را اجرا کنید.

---

## ۱۲) عیب‌یابی

| پیام | علت | راه‌حل |
|---|---|---|
| «نسخه ساختار Patch پشتیبانی نمی‌شود» | `format` ≠ ۱ | مانیفست را با `Updater::create()` بسازید |
| «این Patch برای قالب بریون ساخته نشده است» | `theme` ≠ `beryon` | اصلاح کلید |
| «مسیر غیرمجاز» | خارج از allowlist یا شامل `..` | مسیر نسبی صحیح داخل قالب |
| «پسوند فایل مجاز نیست» | خارج از `patch_ext_allowlist` | افزودن پسوند در تنظیمات (با احتیاط) |
| «hash فایل مطابقت ندارد» | فایل داخل zip با `sha256` مانیفست فرق دارد | بسته را دوباره بسازید |
| «امضای Patch تأیید نشد» | بسته امضا ندارد، کلید اشتباه است یا مانیفست پس از امضا ویرایش شده (روی قالب ≤ ۱.۰.۳: همیشه) | امضا را روی مانیفست نهایی محاسبه کنید (بند ۷)؛ روی ≤ ۱.۰.۳ کلید امضا را موقتاً خالی کنید |
| «پوشه قالب قابل نوشتن نیست» | مجوز فایل‌سرور | `chown`/`chmod` صحیح یا اجرای موقت با دسترسی نوشتن |
| خطای غیرمنتظره پس از اعمال | — | بازگردانی خودکار انجام شده؛ `patch-log.jsonl` را بخوانید |
