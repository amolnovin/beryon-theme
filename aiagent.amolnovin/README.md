# 🤖 کتابخانه تیم‌های ایجنت هوش مصنوعی آمل نوین

مجموعه‌ی **۶ تیم تخصصی ایجنت هوش مصنوعی** آماده‌ی استفاده، شامل **۳۸ ایجنت** در حوزه‌های دیجیتال مارکتینگ، تولید محتوا، اینستاگرام، طراحی UI/UX، توسعه وب و وردپرس.

این مخزن هم فایل‌های فشرده‌ی اصلی (ZIP) دانلودشده از گوگل درایو را نگه می‌دارد و هم محتوای استخراج‌شده و قابل مرور هر تیم را.

> 📅 تاریخ دانلود از گوگل درایو: **۲۷ اکتبر ۲۰۲۶ (۵ آبان ۱۴۰۵)**
> 🌐 زبان پیش‌فرض همه‌ی پکیج‌ها: فارسی (fa) — قابل استفاده در ChatGPT، Claude، Gemini، Poe، Cursor و هر پلتفرم LLM دیگری.

---

## 📊 نمای کلی

| # | تیم | پوشه | نسخه | تعداد ایجنت | تعداد فایل | حجم استخراج‌شده |
|---|-----|------|------|:-----------:|:----------:|:---------------:|
| ۱ | دیجیتال مارکتینگ | [`teams/digital-marketing`](teams/digital-marketing) | 1.0 | ۱۰ | ۴۱ | ۲۳۲ KB |
| ۲ | نویسنده محتوا | [`teams/content`](teams/content) | 1.1.0 | ۷ | ۱۶ | ۱۰۴ KB |
| ۳ | اینستاگرام | [`teams/instagram`](teams/instagram) | 1.0.0 | ۶ | ۲۰ | ۲۳۲ KB |
| ۴ | UI و UX | [`teams/ui-ux`](teams/ui-ux) | 1.0.0 | ۵ | ۱۳ | ۸۰ KB |
| ۵ | طراحی سایت | [`teams/web`](teams/web) | 1.0 | ۵ | ۱۱ | ۱۱۲ KB |
| ۶ | وردپرس | [`teams/wordpress`](teams/wordpress) | 1.0 | ۵ | ۱۰ | ۵۶ KB |
| | **مجموع** | | | **۳۸ ایجنت** | **۱۱۱ فایل** | **۸۱۶ KB** |

---

## 📁 ساختار مخزن

```
aiagent.amolnovin/
├── README.md                 ← همین فایل (مستندات کامل)
├── CHECKSUMS.sha256          ← اثر انگشت (SHA-256) فایل‌های ZIP برای راستی‌آزمایی
├── archives/                 ← ۶ فایل ZIP اصلی دانلودشده از گوگل درایو
│   ├── 10-AI-Marketing-Agents.zip
│   ├── content-ai-agents-pack.zip
│   ├── insta-team-v1.0.0.zip
│   ├── uiux-agent-team.zip
│   ├── web-team.zip
│   └── wordpress-ai-agents.zip
└── teams/                    ← محتوای استخراج‌شده و قابل مرور هر تیم
    ├── digital-marketing/    ← تیم دیجیتال مارکتینگ (۱۰ ایجنت)
    ├── content/              ← تیم نویسنده محتوا (۷ ایجنت)
    ├── instagram/            ← تیم اینستاگرام (۶ ایجنت)
    ├── ui-ux/                ← تیم UI و UX (۵ ایجنت)
    ├── web/                  ← تیم طراحی سایت (۵ ایجنت)
    └── wordpress/            ← تیم وردپرس (۵ ایجنت)
```

---

## 👥 معرفی تیم‌ها

### ۱) تیم دیجیتال مارکتینگ — ۱۰ ایجنت
📂 `teams/digital-marketing` · 📦 `archives/10-AI-Marketing-Agents.zip`

یک تیم کامل دیجیتال مارکتینگ که ۲۴ ساعته برای سایت و کسب‌وکار کار می‌کند. هر ایجنت شامل سه فایل `system_prompt.txt` (پرامپت اصلی)، `config.json` (مدل پیشنهادی، temperature، ابزارها) و `tasks.md` (وظایف و KPI) است. یک داشبورد HTML آفلاین و قالب‌های CSV تقویم محتوایی و KPI هم همراه پکیج است.

| # | ایجنت | نقش |
|---|-------|------|
| ۰۱ | استراتژیست ارشد | Chief Marketing Strategist — استراتژی کلان، بودجه‌بندی و هماهنگی تیم |
| ۰۲ | متخصص سئو | SEO Master — سئوی تکنیکال، On-Page و Off-Page |
| ۰۳ | تولید محتوا و کپی‌رایتر | Content & Copy |
| ۰۴ | مدیر شبکه‌های اجتماعی | Social Media Manager |
| ۰۵ | متخصص تبلیغات پولی | Performance Ads |
| ۰۶ | طراح خلاق و ویدیوساز | Creative & Visual |
| ۰۷ | ایمیل مارکتینگ | Email & Automation |
| ۰۸ | تحلیل‌گر داده | Data & Analytics (GA4، Looker) |
| ۰۹ | تحقیق بازار و رقبا | Market Research |
| ۱۰ | بهینه‌سازی تبدیل | CRO & UX |

فایل‌های کلیدی: [`agents.json`](teams/digital-marketing/agents.json) (ایمپورت خودکار)، [`README.md`](teams/digital-marketing/README.md)، [`QUICK_START.md`](teams/digital-marketing/QUICK_START.md)، داشبورد [`digital-marketing-agents.html`](teams/digital-marketing/HTML%20Dashboard/digital-marketing-agents.html).

---

### ۲) تیم نویسنده محتوا — ۷ ایجنت
📂 `teams/content` · 📦 `archives/content-ai-agents-pack.zip`

تیم ۷ نفره‌ی تولید محتوا با SOP تحریریه‌ی استاندارد و خط تولید ۵ مرحله‌ای. هر ایجنت با یک تریگر فارسی (مثل `@نگار`) فعال می‌شود و `manifest.json` رجیستری ماشین‌خوانای پکیج است.

| # | ایجنت (تریگر) | نقش |
|---|----------------|------|
| ۱ | آریا `@آریا` | استراتژیست ارشد محتوا — استراتژی، پرسونا، تقویم محتوایی |
| ۲ | رها `@رها` | محقق و تحلیلگر محتوا — منابع دو‌زبانه و راستی‌آزمایی |
| ۳ | کیان `@کیان` | متخصص سئو، AI Overview و اسکیما |
| ۴ | نگار `@نگار` | نویسنده و کپی‌رایتر حرفه‌ای |
| ۵ | ترانه `@ترانه` | ویراستار ارشد و کنترل کیفیت |
| ۶ | داریوش `@داریوش` | انسانی‌سازی لحن و حذف ردپای AI |
| ۷ | سارا `@سارا` | شبکه‌های اجتماعی و محتوای بصری |

فایل‌های کلیدی: [`manifest.json`](teams/content/manifest.json)، خط تولید [`team-pipeline.md`](teams/content/workflows/team-pipeline.md)، SOP تحریریه [`editorial-sop.md`](teams/content/workflows/editorial-sop.md) و ۵ قالب آماده در پوشه [`templates`](teams/content/templates).

---

### ۳) تیم اینستاگرام — ۶ ایجنت
📂 `teams/instagram` · 📦 `archives/insta-team-v1.0.0.zip`

اتاق فرمان ۶ نفره‌ی مدیریت پیج اینستاگرام، **نیچ‌آگنوستیک** (قابل استفاده برای هر نوع پیجی: فروشگاه، برند شخصی، کلینیک، رستوران و...). دانش الگوریتم تا سپتامبر ۲۰۲۶ راستی‌آزمایی شده و با دستورات اسلش‌دار (مثل `/سناریو`، `/تقویم`) کار می‌کند.

| # | کد | ایجنت | نقش | خروجی اصلی |
|---|----|-------|------|------------|
| ۱ | R | رادین | Algorithm Intelligence Lead | بریف الگوریتم + بنچمارک رقبا |
| ۲ | S | سارینا | Head of Strategy | سند استراتژی و نقشه ۹۰ روزه |
| ۳ | A | آرمان | Creative Director / Ideation | مخزن ۳۰ تا ۱۰۰ ایده‌ی امتیازدهی‌شده |
| ۴ | N | نیکی | Script & Scenario Writer | بسته سناریوی آماده فیلم‌برداری |
| ۵ | M | مهراد | Growth Operations / Calendar | تقویم محتوایی |
| ۶ | D | دانیال | Analytics & Page Doctor | گزارش آنالیز + نقشه اصلاح پیج |

فایل‌های کلیدی: [`README.md`](teams/instagram/README.md) (اتاق فرمان)، [`MANIFEST.txt`](teams/instagram/MANIFEST.txt)، راهنمای نصب [`INSTALL.md`](teams/instagram/INSTALL.md)، فعال‌سازی [`ACTIVATE.md`](teams/instagram/ACTIVATE.md)، پایگاه دانش الگوریتم ۲۰۲۶ در [`00-knowledge-base`](teams/instagram/00-knowledge-base) و تقویم آماده‌ی [`content-calendar.xlsx`](teams/instagram/03-deliverables/content-calendar.xlsx).

---

### ۴) تیم UI و UX — ۵ ایجنت
📂 `teams/ui-ux` · 📦 `archives/uiux-agent-team.zip`

تیم ۵ نفره‌ی فوق‌تخصصی که ۱۰ تا ۲۰ سایت مرجع را بررسی و بنچمارک می‌کند، مشکلات UX را اولویت‌بندی می‌کند و با روندهای طراحی ۲۰۲۶ (bento grid، glassmorphism کنترل‌شده، scroll-driven animation، view transitions، فونت متغیر) طرح نهایی را به‌صورت HTML/CSS/JS ریسپانسیو تحویل می‌دهد.

| # | ایجنت | نقش |
|---|-------|------|
| ۱ | تحلیلگر پژوهش و بنچمارک | بررسی سایت‌های مرجع، ماتریس بنچمارک و فرصت‌های تمایز |
| ۲ | استراتژیست UX و معمار اطلاعات | پرسونا، سفر کاربر، معماری اطلاعات و فهرست مشکلات UX |
| ۳ | طراح بصری و سیستم طراحی | design tokens، لایوت‌ها و راهنمای سبک مطابق WCAG 2.2 AA |
| ۴ | مهندس حرکت و تعامل | انیمیشن، parallax و scroll-driven |
| ۵ | توسعه‌دهنده فرانت‌اند و QA | پیاده‌سازی ریسپانسیو، تست دسترس‌پذیری و عملکرد |

فایل‌های کلیدی: [`agents.json`](teams/ui-ux/agents.json) (مانیفست نصب با system prompt هر ایجنت)، [`workflow.md`](teams/ui-ux/workflow.md)، دانش مشترک روندهای ۲۰۲۶ و چک‌لیست ۱۰۰ نقطه‌ای در [`knowledge`](teams/ui-ux/knowledge) و قالب شروع در [`templates/starter`](teams/ui-ux/templates/starter).

---

### ۵) تیم طراحی سایت — ۵ ایجنت
📂 `teams/web` · 📦 `archives/web-team.zip`

تیم طراحی و توسعه‌ی وب که یک سایت کامل (HTML, CSS, JS, PHP به‌همراه UI/UX حرفه‌ای) تولید می‌کند. شامل پروتکل همکاری تیمی با قرارداد داده (Data Contract) میان فرانت و بک، استاندارد کد مشترک و یک اسکریپت اعتبارسنجی پایتون است.

| # | کد | ایجنت | تخصص |
|---|----|-------|------|
| ۱ | UX | آرش — معمار تجربه | تحقیق کاربر، معماری اطلاعات، وایرفریم، دسترس‌پذیری |
| ۲ | UI | نگار — طراح بصری | سیستم طراحی، کامپوننت، ریسپانسیو و RTL |
| ۳ | FE | سینا — مهندس فرانت‌اند | HTML5، CSS مدرن، JS/TS، Core Web Vitals، PWA |
| ۴ | BE | بابک — مهندس بک‌اند | PHP 8، Laravel، MySQL، REST API، امنیت و دیپلوی |
| ۵ | QA | مینا — مدیر کیفیت | تست، ممیزی Lighthouse/WAVE، کراس‌براوزر، CI/CD |

فایل‌های کلیدی: [`README.md`](teams/web/README.md)، پروتکل همکاری [`TEAM.md`](teams/web/TEAM.md)، پروفایل ایجنت‌ها در [`agents`](teams/web/agents)، استاندارد کد [`code-standards.md`](teams/web/standards/code-standards.md)، فرم بریف [`PROJECT_BRIEF.md`](teams/web/templates/PROJECT_BRIEF.md) و اسکریپت بررسی [`check_team.py`](teams/web/scripts/check_team.py).

---

### ۶) تیم وردپرس — ۵ ایجنت
📂 `teams/wordpress` · 📦 `archives/wordpress-ai-agents.zip`

تیم نخبگان وردپرس (WP Elite Team) با تمرکز بر اکوسیستم وردپرس و ووکامرس، شامل درگاه‌های پرداخت ایرانی و رعایت RTL. با تگ‌های ایجنت (مثل `@codex`) در چت فعال می‌شود و یک داشبورد HTML هم همراه دارد.

| # | تگ | ایجنت | تخصص |
|---|----|-------|------|
| ۱ | `@codex` | کدکس — معمار افزونه | ساخت افزونه OOP از صفر تا انتشار (CPT، REST API، امنیت) |
| ۲ | `@themos` | تیموس — استاد قالب | قالب‌های FSE، theme.json، Tailwind، تبدیل Figma به WP |
| ۳ | `@wooger` | ووگر — ووکامرس | درگاه‌های ایرانی (زرین‌پال، زیبال)، اشتراک، قسطی، HPOS |
| ۴ | `@securx` | سکیورکس — امنیت و سرعت | هاردنینگ، PageSpeed، Redis، Query Monitor |
| ۵ | `@elementora` | المنتورا — فرانت‌اند | ویجت المنتور، بلوک‌های گوتنبرگ، UI فارسی و RTL |

فایل‌های کلیدی: [`config.json`](teams/wordpress/config.json)، [`README.md`](teams/wordpress/README.md)، راهنمای ایمپورت [`IMPORT_GUIDE.md`](teams/wordpress/IMPORT_GUIDE.md)، همه پرامپت‌ها یکجا در [`PROMPTS_ALL_IN_ONE.md`](teams/wordpress/PROMPTS_ALL_IN_ONE.md) و داشبورد [`dashboard.html`](teams/wordpress/dashboard.html).

---

## 🗂️ فایل‌های ZIP (آرشیو دانلودشده از گوگل درایو)

همه‌ی فایل‌ها از گوگل درایو تیم آمل نوین دانلود و یکپارچگی آن‌ها با تست ZIP و هش SHA-256 تأیید شده است. فایل `content-ai-agents-pack.zip` در گوگل درایو نام `content-ai-agents-pack (1).zip` داشته و در این مخزن نام استاندارد گرفته است.

| فایل ZIP | حجم (بایت) | تعداد فایل داخل | شناسه‌ی فایل در گوگل درایو |
|----------|-----------:|:---------------:|----------------------------|
| `10-AI-Marketing-Agents.zip` | ۵۵٬۶۱۲ | ۴۱ | `1IqHxnTK8r-hdWxQZTuWrW-dRlO1K6G9_` |
| `content-ai-agents-pack.zip` | ۲۶٬۴۸۹ | ۱۶ | `1lGYDjwO7eJ-5cE4qy9wyEae75z6Ptq5L` |
| `insta-team-v1.0.0.zip` | ۸۶٬۵۱۹ | ۲۰ | `1dRctGUcyQL5y3GwCr3QTjlzK3T5OzJaL` |
| `uiux-agent-team.zip` | ۲۷٬۵۷۸ | ۱۳ | `1ZFkkTzg1csBJ_VP6rlfsiPYHdb0I-wwq` |
| `web-team.zip` | ۴۱٬۰۷۱ | ۱۱ | `1onQHrOzjCSsgYJb8c7rlD9XsE3guw20c` |
| `wordpress-ai-agents.zip` | ۱۴٬۹۰۸ | ۱۰ | `1p05OqKns7-J3ANSygg4NPyu-qkMxRwMQ` |
| **مجموع** | **۲۵۲٬۱۷۷ بایت (~۲۴۶ KB)** | **۱۱۱** | |

هشهای کامل در فایل [`CHECKSUMS.sha256`](CHECKSUMS.sha256) قرار دارند.

### راستی‌آزمایی یکپارچگی دانلود

```bash
# بررسی هش SHA-256
cd archives && sha256sum -c ../CHECKSUMS.sha256

# تست سلامت ساختار ZIP
for f in archives/*.zip; do unzip -t "$f" > /dev/null && echo "OK: $f"; done
```

---

## 🚀 روش استفاده

۱. **استفاده‌ی سریع:** فایل Markdown یا `system_prompt.txt` هر ایجنت را باز کنید و محتوایش را به‌عنوان System Prompt / Custom Instruction در ابزار AI دلخواه (ChatGPT، Claude، Gemini، Cursor، Poe و...) کپی کنید.
۲. **ایمپورت خودکار:** پکیج‌های دیجیتال مارکتینگ، UI/UX و وردپرس فایل مانیفست (`agents.json` یا `config.json`) دارند؛ پکیج محتوا `manifest.json` و پکیج اینستاگرام فایل `ACTIVATE.md` دارد.
۳. **اجرای تیمی:** با تریگرها (`@نگار`، `@codex`، ...) یا دستورات اسلش‌دار (`/سناریو`، `/all`) کل خط تولید یک تیم را اجرا کنید.
۴. جزئیات نصب هر تیم در README همان پوشه نوشته شده است.

---

## 📌 یادداشت‌ها

- محتوای این مخزن صرفاً مجموعه‌ی پرامپت‌ها، پروفایل ایجنت‌ها، قالب‌ها و مستندات است (فرمت‌های Markdown، JSON، CSV، HTML و یک فایل XLSX) و هیچ کد اجرایی مخربی ندارد؛ تنها اسکریپت موجود `teams/web/scripts/check_team.py` یک اعتبارسنج ساده‌ی ساختار فایل است.
- اگر فایل ZIP جدیدتری از گوگل درایو جایگزین شد، هشهای `CHECKSUMS.sha256` را هم به‌روزرسانی کنید.
