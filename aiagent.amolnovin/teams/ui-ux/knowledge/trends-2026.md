# بروزترین روندهای طراحی سایت (نسخه اکتبر ۲۰۲۶)

این سند دانش مشترک تیم است. هر روند شامل تکنیک، کاربرد درست و محدودیت است. منابع اصلی در انتها آمده‌اند.

## ۱. Bento grid و لایوت‌های ماژولار
- **تکنیک:** CSS Grid با `grid-template-areas`، کارت‌های نامتقارن، subgrid
- **کاربرد:** ۶ تا ۹ ماژول مشخص برای ویژگی‌ها، خدمات، نمونه‌کارها
- **محدودیت:** بیش از ۱۲ کارت یا ارتفاع نامشخص محتوا برای این لایوت مناسب نیست
- **منبع:** Midrocket، Tenet، theedigital (ژانویه و مارس ۲۰۲۶)

## ۲. Glassmorphism کنترل‌شده
- **تکنیک:** `backdrop-filter: blur()` با پس‌زمینه نیمه‌شفاف و `border` ظریف
- **کاربرد:** header چسبان، کارت‌های کوتاه، overlay
- **محدودیت:** برای متن بلند ناخوانا و کنتراست آن قابل‌تست نیست
- **منبع:** theplusaddons (سپتامبر ۲۰۲۶)

## ۳. Scroll-driven animations با CSS
- **تکنیک:** `animation-timeline: view()` و `scroll()`، `animation-range`
- **وضعیت:** در مرورگرهای اصلی پشتیبانی شده، اما برای امنیت از `@supports` استفاده کن
- **کاربرد:** progress bar، reveal، sticky header تغییرشونده، parallax ساده
- **منبع:** MotionKit (ژوئیه ۲۰۲۶)، Mintec (ژوئیه ۲۰۲۶)

## ۴. View Transitions API
- **تکنیک:** `@view-transition { navigation: auto; }` برای انتقال بین صفحات چندصفحه‌ای؛ `document.startViewTransition()` برای تغییرات داخل صفحه
- **کاربرد:** انتقال از کارت لیست به صفحه جزئیات
- **محدودیت:** مدت انیمیشن کوتاه باشد؛ در مرورگر بدون پشتیبانی، صفحه معمولی لود می‌شود
- **منبع:** we.inc (سپتامبر ۲۰۲۶)، Mintec

## ۵. Parallax هوشمند
- **تکنیک:** تفاوت سرعت لایه‌ها با CSS (`translate` با مقدار `scroll()`) یا JS سبک
- **کاربرد:** hero، تصاویر محصول، بخش‌های داستان‌گو
- **محدودیت:** حداکثر ۳ لایه؛ در موبایل و reduced motion غیرفعال شود
- **منبع:** Tenet، uxpilot.ai (سپتامبر ۲۰۲۵)

## ۶. تایپوگرافی بزرگ و kinetic
- **تکنیک:** فونت متغیر با محور وزن و عرض، `clamp()` برای اندازه، SplitText یا JS ساده برای ورود کلمه‌به‌کلمه
- **کاربرد:** hero، عنوان بخش‌ها، صفحات برندی
- **منبع:** Midrocket، MotionKit

## ۷. Scrollytelling با pin
- **تکنیک:** GSAP ScrollTrigger (pin + scrub) یا sticky با CSS
- **کاربرد:** معرفی محصول، داستان برند، روایت آمار
- **محدودیت:** فقط در یک یا دو بخش؛ در موبایل ساده شود
- **منبع:** MotionKit، done.lu (ژوئن ۲۰۲۶)

## ۸. Dark mode طراحی‌شده از ابتدا
- **تکنیک:** توکن‌های semantic با `prefers-color-scheme`
- **منبع:** Midrocket

## ۹. Micro-interactions و Motion با معنا
- **تکنیک:** hover، focus، کلیک با transition کوتاه (۱۵۰ تا ۲۵۰ms)
- **منبع:** uxpilot.ai، Tenet

## ۱۰. Accessibility به‌عنوان پایه (نه ویژگی اضافی)
- کنتراست ۴.۵:۱ برای متن، فوکوس قابل‌مشاهده، alt، برچسب فرم، اهداف لمسی بزرگ، و پشتیبانی از `prefers-reduced-motion`
- WCAG 3.0 در مارس ۲۰۲۶ به‌صورت Working Draft منتشر شد؛ استاندارد فعلی قابل‌استناد WCAG 2.2 AA است
- **منبع:** we.inc، naturaily (آوریل ۲۰۲۶)

## ۱۱. عملکرد (Performance-first motion)
- انیمیشن فقط `transform` و `opacity`
- INP هدف زیر ۲۰۰ms است؛ JavaScript سنگین عامل اصلی افت آن است
- **منبع:** MotionKit

## ۱۲. تصاویر واقعی و هویت برند
- تصویر واقعی (لوکیشن، تیم، محصول) به جای استوک عمومی
- **منبع:** we.inc

## روندهایی که با احتیاط استفاده شوند
| روند | وضعیت | توصیه |
|---|---|---|
| Neo-brutalism | در حال کمرنگ شدن به عنوان سبک کامل | فقط برای یک CTA برجسته |
| Claymorphism | نیچ (آنبوردینگ، فین‌تک کودکان) | فقط در محصولات مخصوص |
| Y2K و نوستالژی | نیچ (کمپین، موسیقی، مد) | فقط برای صفحات کمپین کوتاه |
| Agentic / liquid UI | آغازین و پرهزینه | فقط برای آینده نقشه‌راه |
| 3D و WebGL | سنگین | فقط با lazy-load و توجیه عملکردی |

## منابع
- theplusaddons.com/blog/web-design-trends-2026 (۲۱ سپتامبر ۲۰۲۶)
- wearetenet.com/blog/ui-ux-design-trends
- theedigital.com/blog/web-design-trends (۵ ژانویه ۲۰۲۶)
- uxpilot.ai/blogs/web-design-trends-2026 (۲۵ سپتامبر ۲۰۲۵)
- midrocket.com/en/guides/ui-design-trends-2026 (۱۲ مارس ۲۰۲۶)
- we.inc/blog/website-design-trends-2026 (۲۶ سپتامبر ۲۰۲۶)
- done.lu/web-design-trends-2026-what-you-need-to-know (۱ ژوئن ۲۰۲۶)
- motionkit.io/blog/web-animation-trends-2026 (۱۸ ژوئیه ۲۰۲۶)
- naturaily.com/blog/web-design-trends (۲۰ آوریل ۲۰۲۶)
- mintec.co/blog/scroll-driven-view-transitions-css-2026 (۱۹ ژوئیه ۲۰۲۶)

> یادآوری: این منابع تحلیلی و بازاریابی هستند. قبل از تصمیم نهایی، پشتیبانی مرورگرها را در caniuse.com یا MDN بررسی کن.
