# نحوه ایمپورت در ابزارهای مختلف

## ChatGPT - Custom GPTs
1. https://chat.openai.com/gpts/editor
2. Create a GPT -> Configure
3. Name: از config.json بخوان (مثلا "Agent 02 — متخصص سئو 🔍")
4. Description: از tasks.md خط اول
5. Instructions: محتوای system_prompt.txt را کامل پیست کن
6. Conversation starters: 3 نمونه از بخش "دستور نمونه" در tasks.md
7. Knowledge: فایل tasks.md را آپلود کن
8. Capabilities: Web Browsing + Code Interpreter را روشن کن (برای 02,08,09)
9. Save

## Claude - Projects
Claude.ai > Projects > New Project > Custom instructions = system_prompt.txt
Knowledge = tasks.md + templates

## Gemini - Gems
gemini.google.com > Gems > New Gem > Instructions = system_prompt.txt

## Poe
poe.com > Create Bot > Prompt = system_prompt.txt

## n8n / Make (اتوماسیون)
- از فایل agents.json استفاده کن
- هر agent یک Workflow جدا با Webhook + AI Agent Node
- System Prompt از prompts.json
- Model از config.json

