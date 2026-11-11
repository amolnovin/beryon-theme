{{--
  صفحه ۴۰۴ — قالب وردپرس بریون
  ------------------------------------------------------------------
  طراحی با همان کلاس‌های قالب HTML نهایی (page-hero ، cta-banner ، grid
  blog-grid ، product-card) تا هیچ CSS جدیدی لازم نباشد و هویت بصری حفظ شود.
  پیوندهای پیشنهادی از داده‌های واقعی (دسته‌ها و آخرین نوشته‌ها) می‌آیند.

  سئو: وضعیت HTTP ۴۰۴ توسط وردپرس ارسال می‌شود و این صفحه noindex است.
--}}
@extends('layouts.app')

@section('content')
@php
  $suggested = \App\Products::query(['featured' => true, 'limit' => 3]);
  $latest    = beryon_latest_posts(3);
@endphp
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => 'صفحه یافت نشد'],
      ]])
      <h1>۴۰۴ — صفحه‌ای که دنبالش بودید پیدا نشد</h1>
      <p>ممکن است نشانی صفحه تغییر کرده یا حذف شده باشد؛ از مسیرهای زیر ادامه دهید.</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:56px">
    <div class="container">
      <div class="filter-bar reveal" style="margin-bottom:34px">
        <form role="search" method="get" action="{{ esc_url(home_url('/')) }}" class="search-form">
          <label class="sr-only" for="s404">جست‌وجو در سایت</label>
          <input id="s404" type="search" name="s" value="" placeholder="{{ beryon_opt('products.search_placeholder') }}">
          <button class="btn btn-primary btn-sm" type="submit">جست‌وجو</button>
        </form>
      </div>

      <div class="cta-banner reveal" style="grid-template-columns:1fr;padding:38px 44px">
        <div>
          <h2 style="font-size:1.3rem">دسترسی سریع به بخش‌های اصلی</h2>
          <p>سبد محصولات، کاتالوگ، گواهینامه‌ها و راه‌های ارتباطی {{ beryon_opt('general.brand_name') }}.</p>
          <div class="actions">
            <a class="btn btn-light" href="{{ esc_url(beryon_products_url()) }}">مشاهده همه محصولات</a>
            <a class="btn btn-outline-light" href="{{ esc_url(beryon_page_url('catalog', 'catalog')) }}">دانلود کاتالوگ</a>
            <a class="btn btn-outline-light" href="{{ esc_url(beryon_page_url('contact-us', 'contact')) }}">تماس با ما</a>
          </div>
        </div>
      </div>

@if (! empty($categories))
      <div class="sec-head reveal" style="margin-top:70px">
        <span class="ribbon-tag">{{ beryon_opt('home.cats_tag') }}</span>
        <h2>{{ beryon_opt('home.cats_title') }}</h2>
        <span class="underline"></span>
      </div>
      <div class="grid cats-grid">
@include('components.cats-grid', ['cats' => $categories])
      </div>
@endif

@if ($suggested !== [])
      <div class="sec-head reveal" style="margin-top:70px">
        <span class="ribbon-tag">{{ beryon_opt('home.featured_tag') }}</span>
        <h2>{{ beryon_opt('home.featured_title') }}</h2>
        <span class="underline"></span>
      </div>
      <div class="grid products-grid">
@include('components.products-grid', ['products' => $suggested])
      </div>
@endif

@if ($latest !== [])
      <div class="sec-head reveal" style="margin-top:70px">
        <span class="ribbon-tag">{{ beryon_opt('home.blog_tag') }}</span>
        <h2>{{ beryon_opt('home.blog_title') }}</h2>
        <span class="underline"></span>
      </div>
      <div class="grid blog-grid">
@foreach ($latest as $index => $post)
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
@endif
    </div>
  </section>
@endsection
