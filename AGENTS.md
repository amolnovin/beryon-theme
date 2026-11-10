# AGENTS.md — ایجنت‌های فعال این پروژه

این پروژه دو تیم ایجنت فعال دارد (منبع: مخزن `aiagent.amolnovin`، مسیرهای نسبی به همان پوشه):

## ۱) تیم UI/UX (uiux-agent-team) — ۵ ایجنت
Playbook و دانش مرجع هر ایجنت:
- @ui-research  → `aiagent.amolnovin/teams/ui-ux/agents/01_research_benchmark.md` (بنچمارک ۱۰–۲۰ سایت)
- @ui-ux        → `aiagent.amolnovin/teams/ui-ux/agents/02_ux_strategy.md` (مشکلات UX، پرسونا، IA)
- @ui-visual    → `aiagent.amolnovin/teams/ui-ux/agents/03_visual_ui_design.md` (توکن‌ها و سیستم طراحی)
- @ui-motion    → `aiagent.amolnovin/teams/ui-ux/agents/04_motion_interaction.md` (حرکت و تعامل)
- @ui-qa        → `aiagent.amolnovin/teams/ui-ux/agents/05_frontend_qa.md` (پیاده‌سازی و QA)

دانش مشترک همه ایجنت‌ها:
- `aiagent.amolnovin/teams/ui-ux/knowledge/trends-2026.md`
- `aiagent.amolnovin/teams/ui-ux/knowledge/site-audit-checklist.md` (چک‌لیست ۱۰۰ نقطه‌ای)

جریان کار و قواعد تحویل: `aiagent.amolnovin/teams/ui-ux/workflow.md`

## ۲) تیم وردپرس (wordpress-ai-agents / WP Elite Team) — ۵ ایجنت
- @codex     → `aiagent.amolnovin/teams/wordpress/agents/01-plugin-architect.md` (معمار افزونه)
- @themos    → `aiagent.amolnovin/teams/wordpress/agents/02-theme-master.md` (استاد قالب و FSE)
- @wooger    → `aiagent.amolnovin/teams/wordpress/agents/03-woo-guru.md` (ووکامرس)
- @securx    → `aiagent.amolnovin/teams/wordpress/agents/04-security-performance.md` (امنیت و سرعت)
- @elementora→ `aiagent.amolnovin/teams/wordpress/agents/05-frontend-wizard.md` (المنتور/گوتنبرگ/RTL)

تنظیمات تیم: `aiagent.amolnovin/teams/wordpress/config.json` · پرامپت یکجا: `aiagent.amolnovin/teams/wordpress/PROMPTS_ALL_IN_ONE.md`

## ۳) تیم طراحی و توسعه وب (web-team) — ۵ ایجنت
رهبر تیم (Orchestrator): خودِ دستیار در چت؛ توزیع کار و یکپارچه‌سازی خروجی‌ها.
- /ux یا @web-ux → `aiagent.amolnovin/teams/web/agents/01-ux-architect.md` (آرش — معمار تجربه)
- /ui یا @web-ui → `aiagent.amolnovin/teams/web/agents/02-ui-designer.md` (نگار — طراح بصری)
- /fe یا @web-fe → `aiagent.amolnovin/teams/web/agents/03-frontend-engineer.md` (سینا — مهندس فرانت‌اند)
- /be یا @web-be → `aiagent.amolnovin/teams/web/agents/04-backend-php.md` (بابک — مهندس بک‌اند PHP)
- /qa یا @web-qa → `aiagent.amolnovin/teams/web/agents/05-qa-lead.md` (مینا — مدیر کیفیت)
دستورات: `/all`، `/review`، `/ship` · پروتکل handoff و قرارداد داده: `aiagent.amolnovin/teams/web/TEAM.md` · استاندارد کد: `aiagent.amolnovin/teams/web/standards/code-standards.md`

## قواعد رفتاری ایجنت‌ها در این ورک‌اسپیس
1. هر تحویل شامل «خلاصه ۵ خطی» + بخش «باز» (ریسک/سؤال) باشد (قاعده تیم UI/UX).
2. هر ادعا با شاهد (لینک/اسکرین‌شات/داده) همراه باشد.
3. تصمیم‌های برند (رنگ، لحن، لوگو) فقط با تأیید کاربر قطعی شود.
4. خروجی‌های هر مرحله در `beryon-docs/` ذخیره شود.
5. پروژه فعلی: قالب جدید بریون در `beryon/` — مخاطرە نکند؛ اول بخوان، بعد بدل کن.
