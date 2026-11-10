{{--
  سربرگ قالب بریون — نوار بالا + هدر اصلی + منوی موبایل
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً همان قالب HTML نهایی است؛ فقط مقدارهای ثابت از تنظیمات قالب
  (\App\Settings) و لینک‌ها از API وردپرس خوانده می‌شوند. آیکون‌ها از رجیستری
  config/icons.json با همان صفت‌های اصلی چاپ می‌شوند (بدون تغییر ظاهری).
--}}
<!-- نوار بالای صفحه -->
<div class="topbar">
  <div class="container">
    <div class="topbar-links">
      <a href="tel:{{ beryon_opt('general.phone_factory') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.6 2z"/></svg><span>تلفن کارخانه: {{ beryon_opt('general.phone_factory_label') }}</span></a>
      <a href="mailto:{{ beryon_opt('general.email') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m22 7-10 6L2 7"/></svg><span>{{ beryon_opt('general.email') }}</span></a>
      <span style="display:inline-flex;align-items:center;gap:7px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg><span>{{ beryon_opt('general.address') }}</span></span>
    </div>
    <div class="topbar-social">
@foreach ((array) beryon_opt('general.socials', []) as $social)
@if (! empty($social['url']))
      <a href="{{ esc_url($social['url']) }}" aria-label="{{ $social['label'] }} {{ beryon_opt('general.brand_name') }}"@if (! empty($social['rel'])) rel="{{ $social['rel'] }}"@endif target="_blank">@php(beryon_svg((string) ($social['icon'] ?? 'link')))</a>
@else
      <a href="#" aria-label="{{ $social['label'] }} {{ beryon_opt('general.brand_name') }}">@php(beryon_svg((string) ($social['icon'] ?? 'link')))</a>
@endif
@endforeach
    </div>
  </div>
</div>

<!-- هدر اصلی -->
<header class="header">
  <div class="container">
    <a class="brand" href="{{ esc_url(home_url('/')) }}" aria-label="{{ beryon_opt('general.brand_name') }} - صفحه اصلی">
      {!! beryon_image_tag(beryon_opt('general.logo_file', 'logo.png'), ['alt' => 'لوگوی ' . beryon_opt('general.brand_name') . ' - ' . beryon_opt('general.company_name'), 'width' => 74, 'height' => 56, 'lazy' => false]) !!}
      <span class="brand-text"><b>{{ beryon_opt('general.brand_name') }}</b><small>{{ beryon_opt('general.brand_latin') }}</small></span>
    </a>
    <ul class="nav" aria-label="منوی اصلی">
      {!! beryon_nav('primary', beryon_active_nav(), 'desktop') !!}
    </ul>
    <div class="header-cta">
      <a class="btn btn-primary btn-sm" href="{{ esc_url(beryon_page_url('agency', 'agency')) }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
        {{ beryon_opt('general.agency_cta_label') }}
      </a>
      <button class="burger" aria-label="باز کردن منو" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
    </div>
  </div>
</header>

<!-- منوی موبایل -->
<div class="mobile-menu" aria-hidden="true">
  <div class="overlay"></div>
  <div class="panel" role="dialog" aria-label="منوی موبایل">
    <div class="panel-head">
      {!! beryon_image_tag(beryon_opt('general.logo_file', 'logo.png'), ['alt' => 'لوگوی ' . beryon_opt('general.brand_name')]) !!}
      <button class="close" aria-label="بستن منو"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
    </div>
    <nav>
      {!! beryon_nav('primary', beryon_active_nav(), 'mobile') !!}
    </nav>
    <div class="panel-foot">
      <a class="btn btn-primary btn-block" href="{{ esc_url(beryon_page_url('agency', 'agency')) }}">{{ beryon_opt('general.agency_cta_label') }}</a>
      <a class="btn btn-ghost btn-block" href="tel:{{ beryon_opt('general.phone_factory') }}">تماس: {{ beryon_opt('general.phone_factory_label') }}</a>
    </div>
  </div>
</div>
