# قالب بریون — Beryon Theme

مخزن رسمی پروژه قالب وب‌سایت **شرکت بریان گوشت آمل (برند بریون)** — beryon.com

این مخزن هر دو نسل قالب را کنار هم نگه می‌دارد:

| بخش | مسیر | توضیح |
|---|---|---|
| قالب وردپرس (Sage 11) | `beryon-wp/` | قالب فعال پروژه؛ Blade + معماری Sage 11، سازگار با WooCommerce (حالت کاتالوگ/استعلام)، پنل تنظیمات، سیستم به‌روزرسانی Patch-محور، سئو پیشرفته و بهینه‌سازی عملکرد. نسخه جاری: **1.0.3** |
| قالب HTML استاتیک | `beryon/` | ۱۸ صفحه HTML/CSS/JS پاسخ‌گرا و RTL که مبنای طراحی و مرجع هم‌ارزی (parity) قالب وردپرس است |
| مستندات | `beryon-docs/` | گزارش ساخت، گزارش‌های رفع اشکال ۱.۰.۱/۱.۰.۲/۱.۰.۳، ماتریس بنچ‌مارک رقبا، گزارش QA و UI/UX |
| ابزارهای QA و بیلد | `tools/` | اسکریپت‌های پایتون: کامپایل/بررسی Blade، هم‌ارزی ۱۸ صفحه، بررسی نحوی ایستای PHP، بیلد دارایی‌ها بدون npm، ساخت بسته Patch |
| مرجع هم‌ارزی | `_src/` | صفحات/پارتیشن‌های نرمال‌شده برای `tools/parity_check.py` |
| بسته‌های تیم‌های 에이جنت | `aiagent.amolnovin/` | خروجی تیم‌های کاری (uiux، wordpress، web، securx و…) |
| اسکریپت بیلد قالب HTML | `build_theme.py` | تولید/به‌روزرسانی صفحات استاتیک |

## نصب قالب وردپرس

1. پوشه `beryon-wp/` را در `wp-content/themes/` قرار دهید (یا `beryon-wp.zip` release را از پیشخوان بارگذاری کنید).
2. حداقل PHP ۷.۴ (تست‌شده تا ۸.۴)؛ `composer install` اختیاری است — قالب بدون vendor هم با autoload داخلی کار می‌کند.
3. فعال‌سازی قالب؛ تنظیمات در `تنظیمات قالب بریون` در پیشخوان است.
4. به‌روزرسانی‌های بعدی از مسیر `تنظیمات قالب بریون → به‌روزرسانی (Patch)` با بسته‌های `beryon-patch-*.zip` نصب می‌شوند (spec: `beryon-wp/docs/PATCH_SPEC.md`).

جزئیات کامل نصب، ساختار و QA: `beryon-wp/README.md`

## کنترل کیفیت (بدون نیاز به npm/composer)

```bash
python3 tools/php_lint.py beryon-wp                 # توازن ساختار ۱۰۲ فایل PHP
python3 tools/php_syntax_check.py beryon-wp --strict # lexer ایستای PHP (رشته شکسته و…)
python3 tools/blade_check.py --strict                # کامپایل ایستای ۳۶ نمای Blade
python3 tools/parity_check.py --strict               # هم‌ارزی با ۱۸ صفحه HTML
python3 tools/api_check.py                           # ارجاع نماها به API قالب
python3 tools/build_assets.py                        # بیلد CSS/JS/manifest
python3 tools/make_patch.py <version> --since "…"    # ساخت بسته Patch
```

## تاریخچه نسخه‌ها

- **1.0.3** — پایه URL قطعی دارایی‌ها (+ رفع بی‌استایلی نصب زنده)، نسخه پویا از style.css، همگام‌سازی خودکار گزینه نسخه
- **1.0.2** — رفع خطای مرگبار Parse در صفحه اضطراری + Warningهای پنل + ابزار `php_syntax_check.py`
- **1.0.1** — رفع صفحه سفید (MiniBlade) و بی‌استایلی (rtl replace) + نگهبان خطای مرگبار
- **1.0.0** — انتشار اولیه قالب وردپرس معادل ۱۸ صفحه HTML

گزارش‌های تفصیلی هر نسخه: `beryon-docs/wp-1.0.1-bugfix-report.md` (شامل الحاقیه‌های ۱.۰.۲ و ۱.۰.۳)

## مالکیت

کارفرما: شرکت بریان گوشت آمل — beryon.com | مخزن: github.com/amolnovin/beryon-theme
