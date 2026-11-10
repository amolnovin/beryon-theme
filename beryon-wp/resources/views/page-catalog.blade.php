{{--
  Template Name: کاتالوگ بریون
  ------------------------------------------------------------------
  صفحه کاتالوگ — مارک‌آپ دقیقاً مطابق catalog.html قالب HTML نهایی.
  فهرست دسته‌ها با شمارش واقعی محصولات از دیتابیس خوانده می‌شود و متن
  «چیزی حدود {count} محصول در ۵ گروه» از طریق توکن {count} پر می‌شود.
--}}
@extends('layouts.app')

@section('content')
@php
  $pdf_id  = (int) beryon_opt('catalog.pdf_id', 0);
  $pdf_url = (string) beryon_opt('catalog.pdf_url', '');

  if ($pdf_url === '' && $pdf_id > 0) {
      $pdf_url = (string) wp_get_attachment_url($pdf_id);
  }

  $flipbook = (string) beryon_opt('catalog.flipbook_url', '');
  $logo     = (string) beryon_opt('general.logo_file', 'logo.png');
@endphp
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => beryon_opt('catalog.crumb', 'کاتالوگ محصولات')],
      ]])
      <h1>{{ beryon_tokens((string) beryon_opt('catalog.title')) }}</h1>
      <p>{{ beryon_tokens((string) beryon_opt('catalog.lead')) }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:64px">
    <div class="container">
      <div class="catalog-wrap">
        <div class="book reveal">
          <div class="cover">
            {!! beryon_image_tag($logo, ['alt' => 'لوگوی ' . beryon_opt('general.brand_name') . ' روی جلد کاتالوگ']) !!}
            <div class="t"><b>{{ beryon_opt('catalog.cover_title') }}</b><small>{{ beryon_opt('catalog.cover_sub') }}</small></div>
            <span class="yr">{{ beryon_tokens((string) beryon_opt('catalog.cover_year')) }}</span>
          </div>
        </div>
        <div class="reveal d1">
          <span class="badge" style="margin-bottom:14px">{{ beryon_tokens((string) beryon_opt('catalog.badge')) }}</span>
          <h2 style="font-size:clamp(1.3rem,3vw,2rem);margin-bottom:14px">{{ beryon_opt('catalog.heading') }}</h2>
          <p style="color:var(--muted)">{{ beryon_tokens((string) beryon_opt('catalog.text')) }}</p>
          <ul class="catalog-list">
@foreach ($categories as $category)
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m5 13 4 4L19 7"/></svg>{{ $category['name'] }}<small>{{ beryon_fa($category['count']) }} محصول</small></li>
@endforeach
          </ul>
          <div class="hero-actions">
@if ($pdf_url !== '')
            <a class="btn btn-primary" href="{{ esc_url($pdf_url) }}" download title="{{ beryon_opt('catalog.download_label') }}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
              {{ beryon_opt('catalog.download_label') }}
            </a>
@else
            <a class="btn btn-primary" href="#" onclick="return false" title="{{ beryon_opt('catalog.download_pending') }}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
              {{ beryon_opt('catalog.download_pending') }}
            </a>
@endif
            <a class="btn btn-ghost" href="{{ esc_url($flipbook !== '' ? $flipbook : beryon_products_url()) }}">{{ beryon_opt('catalog.online_label') }}</a>
          </div>
        </div>
      </div>

      <div class="cta-banner reveal" style="grid-template-columns:1fr;margin-top:70px;padding:40px 44px">
        <div>
          <h2 style="font-size:1.35rem">{{ beryon_opt('catalog.cta_title') }}</h2>
          <p>{{ beryon_opt('catalog.cta_text') }} <a href="tel:{{ beryon_opt('general.phone_sales') }}" style="color:#fff;text-decoration:underline">{{ beryon_opt('general.phone_sales_label') }}</a></p>
          <div class="actions"><a class="btn btn-light" href="{{ esc_url(beryon_page_url('agency', 'agency')) }}">{{ beryon_opt('catalog.cta_btn') }}</a></div>
        </div>
      </div>
    </div>
  </section>
@endsection
