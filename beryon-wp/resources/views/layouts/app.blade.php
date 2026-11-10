{{--
  چیدمان اصلی قالب بریون
  ------------------------------------------------------------------
  ساختار سند دقیقاً مطابق قالب HTML نهایی است:
  head (meta + SEO + font + preload) → skip-link → topbar/header → mobile-menu
  → <main id="main"> محتوای صفحه → footer → to-top → lightbox → اسکریپت‌ها
--}}<!DOCTYPE html>
<html lang="{{ get_locale() === 'fa_IR' ? 'fa' : 'fa' }}" dir="rtl">
<head>
@include('partials.head')
</head>
<body @php(body_class())>
@include('partials.sprite')
@include('partials.header')
<main id="main">
@yield('content')
</main>
@include('partials.footer')
@include('partials.to-top')
@include('partials.lightbox')
@php(wp_footer())
</body>
</html>
