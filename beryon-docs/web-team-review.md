# بررسی و تکمیل پروژه بریون — تیم طراحی و توسعه وب (web-team)
پایپ‌لاین: مراحل ۵ (BUILD-BE)، ۶ (VERIFY) و ۷ (SHIP) — مراحل ۱–۴ پیش‌تر توسط تیم UI/UX و قالب موجود طی شده‌اند.

---
## [UX] آرش — معمار تجربه
**خلاصه:** IA و جریان تبدیل کامل است؛ KPIهای پیشنهادی برای پایش پس از لانچ تعریف شد. ریسک اصلی: نبود جست‌وجوی سراسری در هدر برای ۶۱ محصول.
- KPIها: نرخ کلیک CTA نمایندگی از hero ≥ ۳٪ · تکمیل فرم نمایندگی ≥ ۲۵٪ از شروع · زمان تاInteractive < ۲.۵s · bounce صفحه محصولات < ۴۵٪.
- جریان فرم‌ها مطابق قرارداد داده پایین استاندارد شد.
**باز:** افزودن جست‌وجوی سراسری (هم‌راستا با یافته تیم UI/UX).

## [UI] نگار — طراح بصری
**خلاصه:** سیستم توکن‌ها منسجم و RTL-اول است (استفاده گسترده از inset-inline / margin-inline). دو مورد فیزیکی مانده که در RTL فعلی صحیح رندر می‌شوند ولی برای حالت d Flip آماده نیستند.
- موارد: `border-radius` جلد کاتالوگ و `translateX(50%)` خط تایم‌لاین/چارت — با منطق منطقی جایگزین شوند (فاز بعد).
- حالت‌های hover/focus/active برای همه تعامل‌ها تعریف شده؛ focus-visible سراسری ✔.
**باز:** توکن‌کردن ۳ رنگ اینلاین در کارت‌های معرفی صفحه درباره‌ما.

## [FE] سینا — مهندس فرانت‌اند
**خلاصه:** مطابق code-standards: HTML سمانتیک، بدون فریم‌ورک، JS خالص ~۶KB، انیمیشن فقط transform/opacity، تصاویر lazy + ابعاد در هیرو/لوگو. Core Web Vitals پیش‌بینی: LCP≈۱.۸s (preload هیرو)، CLS≈۰ (aspect-ratio ضمنی تصاویر کارت‌ها)، TBT پایین.
- اصلاح این نشست: preload هیرو فقط در صفحه اصلی، حذف وزن فونت بلااستفاده.
**باز:** افزودن `width/height` صریح به تصاویر کارت‌ها یا کلاس `.thumb img{aspect-ratio}` برای حذف کامل ریسک CLS؛ افزودن manifest+service-worker برای PWA منوط به تأیید کارفرما.

## [BE] بابک — مهندس بک‌اند (PHP)
**خلاصه:** سایت استاتیک است؛ لذا لایه سرور در سه قلم تحویل شد: ۱) `beryon/.htaccess` (هدرهای امنیتی CSP/X-Frame/nosniff، فشرده‌سازی، کش immutable برای assetها، no-cache برای HTML، مسدودی فایل‌های حساس) ۲) قرارداد داده فرم‌ها (پایین) ۳) نقشه اتصال به وردپرس در `wp-conversion-plan.md`.
### قرارداد داده فرم‌ها (Data Contract)
```
ENDPOINT   : POST /api/feedback   |   POST /api/agency   |   POST /api/contact
REQUEST    : feedback { name:str, phone:str(11), email?:str, subject:str, ptype:enum, message:str≥10 }
             agency   { first,last,phone:str(11), province:str, city:str, address:str, coverage:str, sales:enum[], agree:true }
             contact  { name:str, phone:str(11), dept:str, message:str≥10 }
RESPONSE   : 200 { ok:true, ref:'BRY-YYYY-XXXXX' }
ERRORS     : 422 { ok:false, field, message } | 429 { ok:false, message:'تعداد درخواست زیاد است' }
SECURITY   : CSRF در هدر X-CSRF-Token | rate limit 5/min/IP | اعتبارسنجی سمت سرور + prepared statements | خروجی‌ها escape
```
**باز:** پیاده‌سازی endpoint واقعی پس از انتخاب بستر (وردپرس admin-post یا PHP خالص).

## [QA] مینا — مدیر کیفیت
**خلاصه:** چک‌لیست راه‌اندازی زیر تدوین شد؛ موارد تست‌شده با شاهد مشخص‌اند. ممیزی امنیتی OWASP برای لایه استاتیک: بدون ورودی سروری → سطح حمله فعلی صفر؛ هدرها با .htaccess پوشش داده شد.
### چک‌لیست SHIP
- [x] ریسپانسیو ۳۹۰/۷۶۸/۱۲۸۰ (شاهد: qa-mobile-home.png, qa-home-desktop.png)
- [x] تعامل‌ها: فیلتر/جست‌وجو، فرم‌ها+کد پیگیری، منوی موبایل، گالری، تب، آکاردئون، لایت‌باکس
- [x] دسترسی: تک‌H1، بدون پرش heading، alt کامل، aria-current، فوکوس لایت‌باکس، reduced-motion
- [x] سئو: title/desc/canonical/OG/JSON-LD/sitemap/robots
- [x] امنیت سرور: .htaccess (CSP، کش، مسدودی)
- [ ] کراس‌براوزر واقعی: Firefox/Safari (محیط فعلی فقط Chromium) — پیش از لانچ
- [ ] Lighthouse روی هاست واقعی (هدف: P≥۹۰، A≥۹۵، SEO≥۹۵)
- [ ] تست فرم‌ها پس از اتصال بک‌اند (contract بالا)
**باز:** دو مورد تیک‌نخورده بالا،_gate لانچ هستند.

---
### جمع‌بندی رهبر تیم
قالب بریون از دید تیم وب **قابل تحویل (shippable)** است؛ دو gate باقی‌مانده (کراس‌براوزر + Lighthouse واقعی) به محیط تولید وابسته‌اند. همه اسناد در `beryon-docs/` و تنظیمات سرور در `beryon/.htaccess`.
