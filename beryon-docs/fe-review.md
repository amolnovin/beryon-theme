# [FE] سینا — بررسی مهندسی فرانت‌اند قالب بریون
HANDOFF → رهبر تیم / کارفرما · تاریخ: ۱۴۰۵/۰۸/۰۵ · دامنه: `beryon/assets/css/style.css`، `beryon/assets/js/main.js`، ۱۸ صفحه HTML، assets

## خلاصه (۵ خط)
۱) معماری فرانت سالم است: یک stylesheet توکن‌محور (۲۰ متغیر، ۴۹۴ قاعده، ۶ breakpoint)، یک JS خالص بدون وابستگی (gzip ‏۳KB)، HTML سمانتیک RTL-اول با ۵۰ ویژگی منطقی و **صفر** ویژگی فیزیکی left/right.
۲) چهار ایراد واقعی یافت و رفع شد: باگ scope پنل موفقیت فرم خبرنامه، نبود بازگشت فوکوس در لایت‌باکس، نبود پیشوند `-webkit-backdrop-filter` در ۵ نقطه (شکست بلور در Safari)، و preload که پس از تبدیل WebP جا مانده بود.
۳) بار تصاویر ۳۳٪ کاهش یافت: ۲٬۲۶۲KB → ۱۵۱۳KB (WebP q82)؛ فقط hero.jpg و cat-sausage.jpg برای og:image و JSON-LD نگه داشته شدند.
۴) CLS از همان اول مهار شده: همه thumbها ارتفاع ثابت + object-fit دارند و هیرو width/height صریح دارد؛ LCP با preload+fetchpriority بهینه است.
۵) جمع‌بندی: کد **تمیز و قابل تحویل** است؛ موارد باز فقط افزونه‌های اختیاری فاز بعد (PWA، view transitions، dark mode) و تست‌های محیط تولید.

## اندازه‌گیری‌ها (پیش → پس)
| قلم | پیش | پس |
|---|---|---|
| تصاویر شاخص (۱۱ فایل) | ۲٬۲۲ KB jpg | ۱٬۵۱۳ KB webp (−۳۳٪) |
| style.css | ۴۸ KB | ۴۸ KB (gzip ۹.۷ KB) + حذف ۳ توکن مرده |
| main.js | ۱۲ KB (gzip ۳ KB) | +۲ اصلاح باگ/فوکوس، بدون افزایش وزن |
| ارجاع‌های .jpg در صفحات | ۲۱۹ تصویر | فقط ۱۹ og:image (عمدی) |

## یافته‌ها و اقدام‌ها
| # | یافته | شدت | اقدام |
|---|---|---|---|
| ۱ | پنل موفقیت خبرنامه وبلاگ هرگز نمایش داده نمی‌شد (`parentElement` اشتباه) | بحرانی | scope به `closest('.form-card')‖closest('.container')` تغییر کرد |
| ۲ | لایت‌باکس فوکوس را به کاربر برنمی‌گرداند (WCAG 2.4.3) | مهم | ذخیره/بازگشت `lbLastFocus` |
| ۳ | `backdrop-filter` بدون پیشوند در هدر/اورلی/لایت‌باکس/تاریخ کارت/آیتم بنر | مهم | `-webkit-` در همه ۶ نقطه |
| ۴ | توکن‌های مرده `--blue/--surface/--radius-sm` | جزئی | حذف |
| ۵ | preload هیرو پس از WebP جا مانده بود | جزئی | به hero.webp + schema image به webp |
| ۶ | jpgهای بدون ارجاع پس از تبدیل | جزئی | حذف ۹ فایل (−۱.۷MB از مخزن) |

## انطباق با code-standards تیم
سربرگ فایل‌ها ✔ · نام‌گذاری kebab-case ✔ · HTML سمانتیک ✔ · CSS منطقی/RTL-first ✔ · JS: 'use strict'، IIFE، بدون global، listeners با passive ✔ · `node --check` پاس ✔ · بدون کتابخانه خارجی ✔ · تصاویر lazy + alt ✔

## باز (غیربحرانی)
۱) PWA (manifest + service-worker) منوط به تأیید کارفرما. ۲) view transitions بین صفحات. ۳) حالت تاریک اختیاری با توکن‌های موجود ممکن است. ۴) تست Safari/Firefox واقعی و Lighthouse هاست (gateهای QA). ۵) در صورت رشد CSS بالای ۶۰KB، تفکیک critical-CSS بررسی شود.
