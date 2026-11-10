{{--
  صفحه دسته‌بندی محصولات — قالب بریون
  ------------------------------------------------------------------
  یک قالب داده‌محور برای هر پنج دسته (سوسیس، کالباس، ژامبون، انجمادی، گیاهی)
  با مارک‌آپ دقیقاً مطابق cat-*.html قالب HTML نهایی:
  breadcrumb → page-hero → filter-bar (چیپ‌های نوع + جست‌وجو در صورت وجود) →
  گرید محصولات دسته → «گروه‌های دیگر» → متن سئویی دسته.

  داده دسته از term meta (توسط App\Seeder نوشته شده) و در نبود آن از
  config/catalog.json خوانده می‌شود؛ بنابراین خروجی همیشه کامل است.
--}}
@extends('layouts.app')

@section('content')
@php
  /** @var \WP_Term|null $beryon_term */
  $beryon_term = isset($term) && $term instanceof \WP_Term ? $term : get_queried_object();
  $beryon_slug = $beryon_term instanceof \WP_Term ? (string) $beryon_term->slug : '';
  $beryon_cat  = null;

  foreach ((array) $categories as $category) {
      if ((string) $category['slug'] === $beryon_slug) {
          $beryon_cat = $category;
          break;
      }
  }

  $beryon_cat  = is_array($beryon_cat) ? $beryon_cat : [];
  $reference   = \App\Products::catalog()['categories'][$beryon_slug] ?? [];
  $products    = \App\Products::query(['category' => $beryon_slug, 'limit' => (int) beryon_opt('products.grid_limit', 60)]);
  $others      = array_values(array_filter((array) $categories, static fn ($c) => (string) $c['slug'] !== $beryon_slug));

  $title       = (string) ($beryon_cat['title'] ?? ($reference['title'] ?? $beryon_cat['name'] ?? ''));
  $lead        = (string) ($beryon_cat['lead'] ?? ($reference['lead'] ?? ''));
  $chips       = (array) ($beryon_cat['chips'] ?? ($reference['chips'] ?? []));
  $chips_aria  = (string) ($beryon_cat['chips_aria'] ?? ($reference['chips_aria'] ?? 'فیلتر نوع محصول'));
  $sr          = (string) ($beryon_cat['sr'] ?? ($reference['sr'] ?? 'فهرست محصولات بریون'));
  $has_search  = (bool) ($reference['has_search'] ?? false);
  $search_ph   = (string) ($beryon_cat['placeholder'] ?? ($reference['search_placeholder'] ?? ''));
  $search_aria = (string) ($reference['search_aria'] ?? $search_ph);
  $no_result   = (string) ($reference['no_result'] ?? 'محصولی مطابق فیلتر شما یافت نشد.');
  $seo_html    = (string) ($beryon_cat['seo_html'] ?? ($reference['seo'] ?? ''));
@endphp
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => beryon_opt('products.archive_crumb', 'محصولات'), 'url' => beryon_products_url()],
        ['label' => (string) ($beryon_cat['crumb'] ?? ($reference['crumb'] ?? ($beryon_cat['name'] ?? '')))],
      ]])
      <h1>{{ beryon_tokens($title) }}</h1>
      <p>{{ beryon_tokens($lead) }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:56px">
    <div class="container" data-filter-wrap>
@if (beryon_opt('products.show_filters', true) && $chips !== [])
      <div class="filter-bar reveal">
        <div class="filter-chips" role="group" aria-label="{{ $chips_aria }}">
@foreach ($chips as $index => $chip)
          <button class="chip{{ $index === 0 ? ' active' : '' }}" data-filter="{{ is_array($chip) ? ($chip[0] ?? '') : ($chip['value'] ?? '') }}">{{ is_array($chip) ? ($chip[1] ?? '') : ($chip['label'] ?? '') }}</button>
@endforeach
        </div>
@if ($has_search && beryon_opt('products.show_search', true) && $search_ph !== '')
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

      <div class="sec-head reveal" style="margin:64px auto 40px">
        <span class="ribbon-tag">{{ beryon_opt('products.other_cats_tag') }}</span>
        <h2>{{ beryon_opt('products.other_cats_title') }}</h2>
        <span class="underline"></span>
      </div>
      <div class="grid cats-grid">
@include('components.cats-grid', ['cats' => $others])
      </div>
@if ($seo_html !== '')

      <div class="seo-text">
        {!! beryon_tokens($seo_html) !!}
      </div>
@endif
    </div>
  </section>
@endsection
