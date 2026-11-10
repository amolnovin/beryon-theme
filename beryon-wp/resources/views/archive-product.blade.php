{{--
  آرشیو محصولات — قالب بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق صفحه products.html قالب HTML نهایی است:
  breadcrumb → page-hero → filter-bar (چیپ دسته‌ها + جست‌وجوی زنده) → گرید محصولات
  → پیام «نتیجه‌ای یافت نشد» → متن سئویی.
  هوک‌های data-filter-wrap / data-product-grid / data-search / data-count-label
  توسط assets/js/main.js استفاده می‌شوند و نباید تغییر کنند.
--}}
@extends('layouts.app')

@section('content')
@php
  $products = \App\Products::query(['limit' => (int) beryon_opt('products.grid_limit', 60)]);
  $chips    = (array) beryon_opt('products.archive_chips', []);
  $catalog  = \App\Products::catalog()['archive'] ?? [];
  $crumb    = (string) beryon_opt('products.archive_crumb', $catalog['crumb'] ?? 'محصولات');
  $lead     = (string) beryon_opt('products.archive_lead', $catalog['hero_p'] ?? '');
  $sr       = (string) beryon_opt('products.archive_sr', $catalog['sr'] ?? 'فهرست محصولات بریون');
  $search_ph = (string) beryon_opt('products.search_placeholder', $catalog['search_placeholder'] ?? 'جست‌وجوی محصول…');
  $search_aria = (string) beryon_opt('products.search_aria', $catalog['search_aria'] ?? 'جست‌وجوی محصول');
  $no_result = (string) beryon_opt('products.no_result', $catalog['no_result'] ?? 'محصولی مطابق فیلتر شما یافت نشد.');
@endphp
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => $crumb],
      ]])
      <h1>{{ beryon_tokens((string) beryon_opt('products.archive_title')) }}</h1>
      <p>{{ beryon_tokens($lead !== '' ? $lead : (string) beryon_opt('products.archive_desc')) }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:56px">
    <div class="container" data-filter-wrap>
@if (beryon_opt('products.show_filters', true))
      <div class="filter-bar reveal">
        <div class="filter-chips" role="group" aria-label="{{ $catalog['chips_aria'] ?? 'فیلتر دسته‌بندی' }}">
@foreach ($chips as $index => $chip)
          <button class="chip{{ $index === 0 ? ' active' : '' }}" data-filter="{{ $chip['value'] ?? '' }}">{{ $chip['label'] ?? '' }}</button>
@endforeach
        </div>
@if (beryon_opt('products.show_search', true))
        <label class="filter-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
          <input type="search" data-search placeholder="{{ $search_ph }}" aria-label="{{ $search_aria }}">
        </label>
@endif
        <span class="filter-count" data-count-label></span>
      </div>
@endif

      <h2 class="sr-only">{{ $sr }}</h2>
      <div class="grid products-grid" data-product-grid>
@include('components.products-grid', ['products' => $products])
      </div>
      <div class="no-result">{{ $no_result }}</div>

@if ((string) beryon_opt('products.archive_seo_html') !== '')
      <div class="seo-text">
        {!! beryon_tokens((string) beryon_opt('products.archive_seo_html')) !!}
      </div>
@endif
    </div>
  </section>
@endsection
