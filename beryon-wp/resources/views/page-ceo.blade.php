{{--
  Template Name: معرفی مدیرعامل بریون
--}}
{{--
  معرفی مدیرعامل — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق ceo.html است. تصویر ceo-photo.png بدون هیچ تغییری
  (کپی بایت‌به‌بایت) در public/assets/images/ قرار دارد و فقط با CSS
  نمایش داده می‌شود.
--}}
@extends('layouts.app')

@section('content')
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => beryon_opt('about.crumb', 'درباره ما'), 'url' => beryon_page_url('about-us', 'about')],
        ['label' => beryon_opt('ceo.crumb', 'معرفی مدیرعامل')],
      ]])
      <h1>{{ beryon_tokens((string) beryon_opt('ceo.title')) }}</h1>
      <p>{{ beryon_tokens((string) beryon_opt('ceo.lead')) }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:56px">
    <div class="container">
      <div class="about-split">
        <div class="about-media reveal">
          {!! beryon_image_tag((string) beryon_opt('ceo.photo', 'ceo-photo.png'), ['alt' => beryon_opt('ceo.photo_alt'), 'class' => 'main', 'attr' => ['style' => 'height:520px;object-fit:cover;object-position:top center']]) !!}
          <div class="exp"><b>{{ beryon_opt('ceo.exp_value') }}</b><small>{{ beryon_opt('ceo.exp_label') }}</small></div>
        </div>
        <div class="about-content reveal d1">
          <span class="ribbon-tag">{{ beryon_opt('ceo.tag') }}</span>
          <h2>{{ beryon_opt('ceo.name') }}</h2>
@foreach (beryon_rows('ceo.paragraphs') as $paragraph)
          <p>{{ beryon_tokens((string) $paragraph) }}</p>
@endforeach
          <ul class="check-list">
@foreach (beryon_rows('ceo.checks') as $check)
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m8.5 12.5 2.5 2.5 5-6"/></svg>{{ beryon_tokens((string) $check) }}</li>
@endforeach
          </ul>
        </div>
      </div>

      <div class="sec-head reveal" style="margin-top:70px">
        <span class="ribbon-tag">{{ beryon_opt('ceo.achievements_tag') }}</span>
        <h2>{{ beryon_opt('ceo.achievements_title') }}</h2>
        <span class="underline"></span>
      </div>
      <div class="grid features">
@foreach (beryon_rows('ceo.achievements') as $index => $item)
        <article class="feature reveal{{ $index % 3 === 1 ? ' d1' : ($index % 3 === 2 ? ' d2' : '') }}"><span class="ic">@php(beryon_svg($item['icon'] ?? 'book'))</span><h3>{{ $item['title'] ?? '' }}</h3><p>{{ $item['text'] ?? '' }}</p></article>
@endforeach
      </div>

      <blockquote class="cta-banner reveal" style="grid-template-columns:1fr;margin-top:56px;padding:44px">
        <div>
          <h2 style="font-size:clamp(1.1rem,2.4vw,1.5rem);line-height:2">{{ beryon_opt('ceo.quote') }}</h2>
          <p style="margin-top:10px">{{ beryon_opt('ceo.quote_by') }}</p>
        </div>
      </blockquote>
    </div>
  </section>
@endsection
