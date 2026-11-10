{{--
  سرصفحه سند — قالب بریون
  ------------------------------------------------------------------
  metaهای پایه دقیقاً مطابق قالب HTML نهایی نوشته شده‌اند؛ بقیه (عنوان،
  description، canonical، robots، Open Graph، Twitter، favicon، JSON-LD،
  theme-color، preload تصویر هیرو و preconnect فونت) از راه wp_head() توسط
  \App\Seo و \App\Assets چاپ می‌شوند. اگر افزونه سئوی دیگری فعال باشد، همان
  افزونه این بخش‌ها را تولید می‌کند و سئوی داخلی قالب خاموش می‌شود.
--}}
<meta charset="{{ get_bloginfo('charset') ?: 'UTF-8' }}">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
@php(wp_head())
<noscript><style>.reveal{opacity:1!important;transform:none!important}</style></noscript>
