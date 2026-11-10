{{--
  Template Name: گواهینامه‌ها و افتخارات بریون
--}}
{{--
  گواهینامه‌ها و افتخارات — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق certificates.html؛ کارت‌ها از ردیف‌های تنظیم‌پذیر
  certificates.intl و certificates.national ساخته می‌شوند و کلاس‌های
  تأخیر انیمیشن (d1/d2/d3) مطابق قالب HTML با پیمانه ۴ تکرار می‌شوند.
--}}
@extends('layouts.app')

@section('content')
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => beryon_opt('about.crumb', 'درباره ما'), 'url' => beryon_page_url('about-us', 'about')],
        ['label' => beryon_opt('certificates.crumb', 'گواهینامه‌ها')],
      ]])
      <h1>{{ beryon_tokens((string) beryon_opt('certificates.title')) }}</h1>
      <p>{{ beryon_tokens((string) beryon_opt('certificates.lead')) }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:56px">
    <div class="container">
      <div class="sec-head reveal">
        <span class="ribbon-tag">{{ beryon_opt('certificates.intl_tag') }}</span>
        <h2>{{ beryon_opt('certificates.intl_title') }}</h2>
        <p>{{ beryon_opt('certificates.intl_desc') }}</p>
        <span class="underline"></span>
      </div>
      <div class="grid certs-grid">
@foreach (beryon_rows('certificates.intl') as $index => $cert)
        <article class="cert-card reveal{{ $index % 4 === 1 ? ' d1' : ($index % 4 === 2 ? ' d2' : ($index % 4 === 3 ? ' d3' : '')) }}"><span class="ic">@php(beryon_svg('medal'))</span><h3>{!! beryon_amp($cert['title'] ?? '') !!}</h3><small>{{ $cert['sub'] ?? '' }}</small></article>
@endforeach
      </div>

      <div class="sec-head reveal" style="margin-top:76px">
        <span class="ribbon-tag">{{ beryon_opt('certificates.national_tag') }}</span>
        <h2>{{ beryon_opt('certificates.national_title') }}</h2>
        <span class="underline"></span>
      </div>
      <div class="grid certs-grid">
@foreach (beryon_rows('certificates.national') as $index => $cert)
        <article class="cert-card reveal{{ $index % 4 === 1 ? ' d1' : ($index % 4 === 2 ? ' d2' : ($index % 4 === 3 ? ' d3' : '')) }}"><span class="ic" style="background:linear-gradient(135deg,var(--brand-soft),#ffd9d5);color:var(--brand)">@php(beryon_svg('trophy'))</span><h3>{{ $cert['title'] ?? '' }}</h3><small>{{ $cert['sub'] ?? '' }}</small></article>
@endforeach
      </div>

      <div class="cta-banner reveal" style="grid-template-columns:1fr;margin-top:64px;padding:40px 44px">
        <div>
          <h2 style="font-size:1.35rem">{{ beryon_opt('certificates.cta_title') }}</h2>
          <p>{{ beryon_opt('certificates.cta_text') }}</p>
          <div class="actions"><a class="btn btn-light" href="{{ esc_url(beryon_page_url('contact-us', 'contact')) }}">{{ beryon_opt('certificates.cta_btn_1') }}</a><a class="btn btn-outline-light" href="{{ esc_url(beryon_page_url('about-us', 'about')) }}">{{ beryon_opt('certificates.cta_btn_2') }}</a></div>
        </div>
      </div>
    </div>
  </section>
@endsection
