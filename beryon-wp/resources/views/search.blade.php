{{--
  نتایج جست‌وجو — قالب وردپرس بریون
  ------------------------------------------------------------------
  طراحی از همان کلاس‌های قالب HTML نهایی ساخته شده (page-hero ، filter-bar ،
  grid blog-grid ، product-card) تا هیچ CSS جدیدی لازم نباشد. نتایج شامل
  نوشته‌ها و محصولات است؛ جست‌وجوی محصولات با کوئری اختصاصی انجام می‌شود
  تا حتی اگر نوع پست محصول در جست‌وجوی اصلی وردپرس نباشد، نتیجه بدهد.

  سئو: طبق تنظیمات seo.noindex_search صفحه جست‌وجو noindex می‌شود.
--}}
@extends('layouts.app')

@section('content')
@php
  $search_term = (string) get_search_query();
  $found_posts = [];

  foreach ((array) $GLOBALS['wp_query']->posts as $found) {
      $card = beryon_post_card($found, 'excerpt');

      if ($card !== []) {
          $found_posts[] = $card;
      }
  }

  $found_products = \App\Products::query(['search' => $search_term, 'limit' => (int) beryon_opt('products.grid_limit', 24)]);
  $total          = count($found_posts) + count($found_products);
@endphp
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => 'نتایج جست‌وجو'],
      ]])
      <h1>جست‌وجو برای: «{{ $search_term }}»</h1>
      <p>{{ beryon_fa($total) }} نتیجه یافت شد.</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:56px">
    <div class="container">
      <div class="filter-bar reveal" style="margin-bottom:34px">
        <form role="search" method="get" action="{{ esc_url(home_url('/')) }}" class="search-form">
          <label class="sr-only" for="s">{{ beryon_opt('products.search_aria') }}</label>
          <input id="s" type="search" name="s" value="{{ $search_term }}" placeholder="{{ beryon_opt('products.search_placeholder') }}">
          <button class="btn btn-primary btn-sm" type="submit">جست‌وجو</button>
        </form>
      </div>

@if ($found_products !== [])
      <h2 class="sr-only">محصولات مرتبط</h2>
      <div class="grid products-grid">
@include('components.products-grid', ['products' => $found_products])
      </div>
@endif

@if ($found_posts !== [])
      <h2 class="sr-only">نوشته‌های مرتبط</h2>
      <div class="grid blog-grid">
@foreach ($found_posts as $index => $post)
        <article class="blog-card reveal{{ $index % 3 === 1 ? ' d1' : ($index % 3 === 2 ? ' d2' : '') }}">
          <a class="thumb" href="{{ esc_url($post['link']) }}">@if ((string) $post['image'] !== '')<img src="{{ esc_url(beryon_img((string) $post['image'])) }}" alt="{{ $post['alt'] }}" loading="lazy">@endif<span class="date">{{ $post['date'] }}</span></a>
          <div class="body">
            <h3><a href="{{ esc_url($post['link']) }}">{{ $post['title'] }}</a></h3>
            <p>{{ $post['excerpt'] }}</p>
            <a class="more" href="{{ esc_url($post['link']) }}">{{ beryon_opt('blog.more_label') }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
          </div>
        </article>
@endforeach
      </div>

{!! beryon_pagination() !!}
@endif

@if ($total === 0)
      <div class="no-result show" role="status">
        <p>نتیجه‌ای برای «{{ $search_term }}» یافت نشد؛ عبارت دیگری را جست‌وجو کنید یا از فهرست دسته‌بندی‌ها دیدن کنید.</p>
        <div class="hero-actions" style="margin-top:18px">
          <a class="btn btn-primary" href="{{ esc_url(beryon_products_url()) }}">مشاهده محصولات</a>
          <a class="btn btn-ghost" href="{{ esc_url(home_url('/')) }}">بازگشت به صفحه اصلی</a>
        </div>
      </div>
@endif
    </div>
  </section>
@endsection
