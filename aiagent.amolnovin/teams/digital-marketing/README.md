# پک 10 ایجنت هوش مصنوعی دیجیتال مارکتینگ
### نسخه 1.0 — قابل استفاده در ChatGPT / Claude / Gemini / Poe / هر AI دیگری

> یک تیم کامل دیجیتال مارکتینگ که 24 ساعته برای سایت و کسب‌وکارت کار می‌کند.

---

## 📦 محتویات پک

```
10-AI-Marketing-Agents/
├── README.md (همین فایل)
├── QUICK_START.md (راهنمای نصب 5 دقیقه‌ای)
├── agents.json (فایل اصلی برای ایمپورت خودکار)
├── HTML Dashboard/
│   └── digital-marketing-agents.html (مرکز کنترل تعاملی - آفلاین)
├── agents/ (10 پوشه مجزا)
│   ├── 01-strategist/
│   ├── 02-seo/
│   ├── 03-content/
│   ├── 04-social/
│   ├── 05-ads/
│   ├── 06-creative/
│   ├── 07-email/
│   ├── 08-analytics/
│   ├── 09-research/
│   └── 10-cro/
├── prompts/
│   ├── all_prompts.md (همه پرامپت‌ها یکجا)
│   └── prompts.json (برای توسعه‌دهندگان)
└── templates/
    ├── content_calendar_template.csv
    ├── kpi_dashboard_template.csv
    └── strategy_template.md
```

---

## 👥 لیست 10 ایجنت

| # | نام ایجنت | نقش | فایل |
|---|---|---|---|
| 01 | استراتژیست ارشد | Chief Marketing Strategist | `agents/01-strategist/` |
| 02 | متخصص سئو | SEO Master | `agents/02-seo/` |
| 03 | تولید محتوا و کپی‌رایتر | Content & Copy | `agents/03-content/` |
| 04 | مدیر شبکه‌های اجتماعی | Social Media Manager | `agents/04-social/` |
| 05 | متخصص تبلیغات پولی | Performance Ads | `agents/05-ads/` |
| 06 | طراح خلاق و ویدیوساز | Creative & Visual | `agents/06-creative/` |
| 07 | ایمیل مارکتینگ | Email & Automation | `agents/07-email/` |
| 08 | تحلیل‌گر داده | Data & Analytics | `agents/08-analytics/` |
| 09 | تحقیق بازار و رقبا | Market Research | `agents/09-research/` |
| 10 | بهینه‌سازی تبدیل | CRO & UX | `agents/10-cro/` |

هر پوشه شامل 3 فایل است:
- `system_prompt.txt` → پرامپت اصلی برای ساخت Custom GPT / Project
- `config.json` → تنظیمات (مدل پیشنهادی، temperature، ابزارها)
- `tasks.md` → وظایف کامل روزانه/هفتگی/ماهانه + KPI

---

## ⚡ نصب سریع در 3 روش

### روش A: ChatGPT (پیشنهادی)
1. به chat.openai.com برو → سمت چپ Explore GPTs → Create
2. نام و آیکون را از `config.json` کپی کن
3. محتوای `system_prompt.txt` را در بخش Instructions پیست کن
4. در Knowledge فایل `tasks.md` را آپلود کن
5. Save کن — تمام!

### روش B: Claude Projects
1. Claude.ai → Projects → New Project
2. نام پروژه = نام ایجنت
3. در Custom Instructions محتوای `system_prompt.txt` را بگذار
4. فایل `tasks.md` را در Project Knowledge آپلود کن

### روش C: هر AI دیگر (Gemini Gems, Poe, Copilot)
- فقط `system_prompt.txt` را به عنوان System Prompt کپی کن.

### روش D: استفاده گروهی (حرفه‌ای)
فایل `agents.json` را به توسعه‌دهنده بده تا با یک اسکریپت همه 10 ایجنت را در n8n / Flowise / LangChain بسازد.

---

## 🔄 چرخه همکاری

```
09 تحقیق → 01 استراتژی → 02+03 سئو و محتوا → 06 طراحی → 04+05 انتشار → 10 تبدیل → 07 نگهداشت → 08 تحلیل → 01 بهینه‌سازی
```

---

## 🛠 نکته برای استفاده در همین چت

در همین چت فعلی هم می‌توانی بگویی:
> "ایجنت 3 فعال شو و برای محصول X مقاله بنویس"
و من همان نقش را می‌گیرم.

---

**ساخته شده:** 2026-09-08 | **سازنده:** Arena Agent Mode | **زبان:** فارسی + انگلیسی (پرامپت‌ها دوزبانه)
**پشتیبانی:** هر سوال داشتی همین چت بپرس!
