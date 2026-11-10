# استانداردهای کد تیم (Code Standards)

این فایل برای **همه‌ی** ایجنت‌ها لازم‌الاجراست. خروجی‌ای که با این استانداردها نخواند، در مرحله‌ی `QA` رد می‌شود.

---

## ۱) زبان و نام‌گذاری

| مورد | قاعده | مثال |
|---|---|---|
| نام فایل HTML/PHP | کوچک، با خط تیره | `booking-form.php` |
| نام فایل CSS/JS | کوچک، با خط تیره | `nav-menu.js` |
| کلاس CSS | BEM | `.card__title--large` |
| متغیر CSS | `--<group>-<name>` | `--color-primary-500` |
| متغیر PHP | camelCase | `$bookingDate` |
| ثابت PHP | UPPER_SNAKE | `MAX_UPLOAD_SIZE` |
| تابع PHP | camelCase با فعل | `getActiveServices()` |
| جدول دیتابیس | جمع، snake_case | `appointments` |
| ستون دیتابیس | snake_case | `created_at` |
| شناسه در JS | camelCase | `submitHandler` |
| متن کاربر | فارسی روان | «شمارهٔ موبایل معتبر نیست» |
| کامنت کد | انگلیسی | `// prevent double submit` |

---

## ۲) سربرگ هر فایل

```php
<?php
/**
 * Booking form handler.
 *
 * @package Clinic
 * @author  BE agent
 */
declare(strict_types=1);
```

```js
/**
 * Booking form — client-side validation & submit.
 * Requires: core.js (apiRequest)
 * @author FE agent
 */
```

---

## ۳) قواعد HTML

- `<!DOCTYPE html>` + `<html lang="fa" dir="rtl">`
- `<meta charset="UTF-8">` و `<meta name="viewport" content="width=device-width, initial-scale=1">`
- یک `<h1>` در هر صفحه؛ عنوان‌ها پله‌پله (از `h2` به `h4` نپر)
- هر تصویر `alt` معنادار (یا `alt=""` برای تصویر تزئینی)
- هر `input` یک `label` با `for`
- دکمه‌ها `<button type="button|submit">`، نه `<div onclick>`

---

## ۴) قواعد CSS

```css
/* ✅ درست — RTL-safe */
.card { padding-inline: var(--space-3); margin-inline-start: var(--space-2); }

/* ❌ غلط */
.card { padding-left: 16px; margin-left: 8px; }
```

- همه‌ی مقادیر از توکن: `var(--space-3)` نه `16px`
- واحد: `rem` برای فونت و فاصله، `%`/`fr` برای چیدمان، `px` فقط برای border و shadow
- موبایل-اول؛ breakpoint با `min-width`
- `@media (prefers-reduced-motion: reduce)` برای همه‌ی انیمیشن‌ها
- `:focus-visible` همیشه استایل دارد

---

## ۵) قواعد JavaScript

- `'use strict'` یا ES Modules
- `const` پیش‌فرض، `let` فقط وقتی بازتخصیص لازم است، `var` ممنوع
- بدون `innerHTML` با داده‌ی کاربر → از `textContent` یا escape استفاده کن
- همه‌ی `fetch` با مدیریت خطا و حالت loading
- هیچ global جدیدی جز یک namespace (`window.App`)
- ماژول‌ها کوچک و تک‌مسئولیتی

```js
// ✅
async function submitBooking(form) {
  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  try {
    const res = await apiRequest('/api/appointments', { method: 'POST', body: data });
    showSuccess(res.data.ref);
  } catch (err) {
    showFieldError(err.field, err.message);
  } finally {
    btn.disabled = false;
  }
}
```

---

## ۶) قواعد PHP

- `declare(strict_types=1);` در همه‌ی فایل‌ها
- PDO با prepared statement — بدون استثنا
- اعتبارسنجی سمت سرور برای **همه‌ی** ورودی‌ها
- `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` برای هر خروجی HTML
- توکن CSRF برای هر POST
- `password_hash()` / `password_verify()`
- بدون `extract()`، بدون `eval()`، بدون `include` با ورودی کاربر
- جداکردن لایه‌ها: controller منطق کسب‌وکار ندارد

---

## ۷) قواعد دیتابیس

- charset `utf8mb4` و collation `utf8mb4_unicode_ci`
- همه‌ی جدول‌ها `id`، `created_at`، `updated_at`
- کلید خارجی با `ON DELETE` صریح
- ایندکس روی هر ستونی که در `WHERE`/`ORDER BY` استفاده می‌شود
- حذف soft (`deleted_at`) برای داده‌های مهم

---

## ۸) Git

```
feat:  قابلیت جدید          fix:    رفع باگ
style: فقط ظاهر/قالب        perf:   بهینه‌سازی
refactor: بازنویسی بدون تغییر رفتار
docs:  مستندات              test:   تست
```
- هر commit یک تغییر منطقی
- `.env` هرگز commit نمی‌شود

---

## ۹) ساختار پوشه‌ی پروژه (مرجع)

```
project/
├── index.php
├── includes/
│   ├── config.php        # خواندن .env
│   ├── db.php            # اتصال PDO
│   ├── functions.php
│   ├── security.php      # CSRF, headers, sanitize
│   ├── header.php
│   └── footer.php
├── api/
│   └── v1/*.php
├── admin/
│   ├── index.php
│   └── ...
├── assets/{css,js,img}/
├── database/schema.sql
├── uploads/              # بیرون از دسترسی مستقیم، با .htaccess
├── logs/
├── .env.example
└── docs/
```

---

## ۱۰) تعریف کد «تمیز» از دید تیم

1. خواندنش از نوشتنش سخت‌تر نباشد
2. نام‌ها منظور را برسانند — کامنت برای توضیح «چرا» باشد نه «چه»
3. هیچ تابعی بیش از ~۴۰ خط؛ هیچ فایل بیش از ~۳۰۰ خط
4. هیچ منطق تکراری — یک بار بنویس، یک بار استفاده کن
5. خطاها هندل شوند، نه بلعیده (`catch {}` خالی ممنوع)
