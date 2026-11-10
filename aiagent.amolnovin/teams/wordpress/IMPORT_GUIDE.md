# راهنمای استفاده از تیم در پروژه‌های دیگر

این پکیج شامل 5 ایجنت متخصص وردپرس است که می‌توانید در هر پروژه هوش مصنوعی دیگری استفاده کنید.

## محتویات پکیج
- `config.json` - تنظیمات تیم و لیست ایجنت‌ها
- `agents/` - پرامپت کامل هر ایجنت (5 فایل .md)
- `dashboard.html` - پنل بصری HTML برای نمایش تیم
- `README.md` - توضیحات کلی

## روش 1: استفاده در ChatGPT / Claude / هر AI دیگر
1. فایل `PROMPTS_ALL_IN_ONE.md` را باز کن
2. کل محتوای آن را کپی کن و به عنوان System Prompt یا در ابتدای چت جدید Paste کن
3. بعد از آن بنویس: "تیم فعال شد؟" - باید 5 ایجنت خودشان را معرفی کنند

## روش 2: استفاده در پروژه کدنویسی (Cursor, VS Code AI, etc)
فولدر `wordpress-ai-agents` را در روت پروژه‌ات کپی کن و در فایل `.cursorrules` یا `AGENTS.md` این را اضافه کن:

```
@wordpress-ai-agents/agents/01-plugin-architect.md
@wordpress-ai-agents/agents/02-theme-master.md
@wordpress-ai-agents/agents/03-woo-guru.md
@wordpress-ai-agents/agents/04-security-performance.md
@wordpress-ai-agents/agents/05-frontend-wizard.md
```

## روش 3: استفاده در n8n / Make / Agent Frameworks
از `config.json` برای ساخت 5 Agent مجزا استفاده کن. هر Agent یک LLM Node با System Prompt خودش است.

## تگ‌های فراخوانی
- @codex -> افزونه
- @themos -> قالب
- @wooger -> ووکامرس
- @securx -> امنیت
- @elementora -> فرانت‌اند

ساخته شده: 2026-09-08
توسط: WP Elite Team
