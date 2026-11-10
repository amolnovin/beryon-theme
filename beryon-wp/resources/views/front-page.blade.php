{{--
  صفحه اصلی — قالب بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق index.html قالب HTML نهایی است؛ همه متن‌ها، آمارها،
  مزیت‌ها، افتخارات و فراخوان‌ها از پنل «تنظیمات قالب بریون» می‌آیند و
  محصولات/دسته‌ها/نوشته‌ها از دیتابیس وردپرس خوانده می‌شوند.

  کلاس body: «home» توسط \App\Assets::body_class() اضافه می‌شود تا هدر شفاف
  فقط در صفحه اصلی و فقط در دسکتاپ فعال باشد (CSS خط ۵۹۸-۶۰۱).
--}}
@extends('layouts.app')

@section('content')
@php
  $posts = beryon_latest_posts((int) beryon_opt('home.blog_count', 3));
@endphp
  <!-- ===== هیرو ===== -->
  <section class="hero">
    <div class="container">
      <div class="hero-content">
        <span class="hero-badge"><i></i> {{ beryon_tokens((string) beryon_opt('home.hero_badge')) }}</span>
        <h1>{{ beryon_tokens((string) beryon_opt('home.hero_title_1')) }} <span class="hl">{{ beryon_tokens((string) beryon_opt('home.hero_title_hl')) }}</span> {{ beryon_tokens((string) beryon_opt('home.hero_title_2')) }}</h1>
        <p class="lead">{{ beryon_tokens((string) beryon_opt('home.hero_lead')) }}</p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="{{ esc_url(beryon_products_url()) }}">
            @php(beryon_svg('box'))
            {{ beryon_opt('home.hero_btn_primary') }}
          </a>
          <a class="btn btn-ghost" href="{{ esc_url(beryon_page_url('catalog', 'catalog')) }}">
            @php(beryon_svg('download'))
            {{ beryon_opt('home.hero_btn_secondary') }}
          </a>
        </div>
        <div class="hero-stats">
@foreach (beryon_rows('home.hero_stats') as $stat)
          <div class="stat"><b><span data-count="{{ beryon_en_digits((string) beryon_tokens((string) ($stat['value'] ?? ''))) }}">۰</span>{{ $stat['suffix'] ?? '' }}</b><small>{{ $stat['label'] ?? '' }}</small></div>
@endforeach
        </div>
      </div>
      <div class="hero-media reveal">
        <span class="ribbon" aria-hidden="true"></span>
        <div class="frame">{!! beryon_image_tag((string) beryon_opt('home.hero_image', 'hero.webp'), ['alt' => beryon_tokens((string) beryon_opt('home.hero_image_alt')), 'width' => (int) beryon_opt('home.hero_image_w', 620), 'height' => (int) beryon_opt('home.hero_image_h', 460), 'lazy' => false]) !!}</div>
        <div class="float-card f1">
          <span class="ic">@php(beryon_svg((string) beryon_opt('home.hero_float_1_icon', 'shield-check')))</span>
          <span>{{ beryon_opt('home.hero_float_1') }}<small>{{ beryon_opt('home.hero_float_1_sub') }}</small></span>
        </div>
        <div class="float-card f2">
          <span class="ic">@php(beryon_svg((string) beryon_opt('home.hero_float_2_icon', 'leaf')))</span>
          <span>{{ beryon_opt('home.hero_float_2') }}<small>{{ beryon_opt('home.hero_float_2_sub') }}</small></span>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== دسته‌بندی محصولات ===== -->
  <section class="section" style="padding-top:30px">
    <div class="container">
      <div class="sec-head reveal">
        <span class="ribbon-tag">{{ beryon_opt('home.cats_tag') }}</span>
        <h2>{{ beryon_opt('home.cats_title') }}</h2>
        <p>{{ beryon_opt('home.cats_desc') }}</p>
        <span class="underline"></span>
      </div>
      <div class="grid cats-grid">
@include('components.cats-grid', ['cats' => $categories])
      </div>
    </div>
  </section>

  <!-- ===== چرا بریون ===== -->
  <section class="section" style="background:#fff">
    <div class="container">
      <div class="sec-head reveal">
        <span class="ribbon-tag">{{ beryon_opt('home.features_tag') }}</span>
        <h2>{{ beryon_opt('home.features_title') }}</h2>
        <p>{{ beryon_opt('home.features_desc') }}</p>
        <span class="underline"></span>
      </div>
      <div class="grid features">
@foreach (beryon_rows('home.features') as $index => $feature)
        <article class="feature reveal{{ $index % 3 === 1 ? ' d1' : ($index % 3 === 2 ? ' d2' : '') }}">
          <span class="ic">@php(beryon_svg((string) ($feature['icon'] ?? '')))</span>
          <h3>{{ $feature['title'] ?? '' }}</h3>
          <p>{{ $feature['text'] ?? '' }}</p>
        </article>
@endforeach
      </div>
    </div>
  </section>

  <!-- ===== درباره بریون ===== -->
  <section class="section">
    <div class="container">
      <div class="about-split">
        <div class="about-media reveal">
          {!! beryon_image_tag((string) beryon_opt('home.about_image', 'factory.webp'), ['alt' => beryon_opt('home.about_image_alt'), 'class' => 'main']) !!}
          <div class="exp"><b>{{ beryon_opt('home.about_exp_value') }}</b><small>{{ beryon_opt('home.about_exp_label') }}</small></div>
        </div>
        <div class="about-content reveal d1">
          <span class="ribbon-tag">{{ beryon_opt('home.about_tag') }}</span>
          <h2>{{ beryon_opt('home.about_title') }}</h2>
@foreach (beryon_rows('home.about_paragraphs') as $paragraph)
          <p>{{ $paragraph }}</p>
@endforeach
          <ul class="check-list">
@foreach (beryon_rows('home.about_checks') as $check)
            <li>@php(beryon_svg('check-circle')){{ $check }}</li>
@endforeach
          </ul>
          <div class="hero-actions">
            <a class="btn btn-primary" href="{{ esc_url(beryon_page_url('about-us', 'about')) }}">{{ beryon_opt('home.about_btn_1') }}</a>
            <a class="btn btn-ghost" href="{{ esc_url(beryon_page_url('about-us/certificates', 'certificates')) }}">{{ beryon_opt('home.about_btn_2') }}</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== نوار آمار ===== -->
  <section class="stats-band">
    <div class="container">
      <div class="grid">
@foreach (beryon_rows('home.stats_band') as $index => $stat)
        <div class="stat reveal{{ $index === 1 ? ' d1' : ($index === 2 ? ' d2' : ($index === 3 ? ' d3' : '')) }}"><b><span data-count="{{ beryon_en_digits((string) beryon_tokens((string) ($stat['value'] ?? ''))) }}">۰</span>{{ $stat['suffix'] ?? '' }}</b><small>{{ $stat['label'] ?? '' }}</small></div>
@endforeach
      </div>
    </div>
  </section>

  <!-- ===== محصولات ویژه ===== -->
  <section class="section">
    <div class="container">
      <div class="sec-head reveal">
        <span class="ribbon-tag">{{ beryon_opt('home.featured_tag') }}</span>
        <h2>{{ beryon_opt('home.featured_title') }}</h2>
        <p>{{ beryon_opt('home.featured_desc') }}</p>
        <span class="underline"></span>
      </div>
      <div class="grid products-grid">
@include('components.products-grid', ['products' => \App\Products::query(['featured' => true, 'limit' => 8])])
      </div>
      <p class="text-center" style="margin-top:40px"><a class="btn btn-ghost" href="{{ esc_url(beryon_products_url()) }}">{{ beryon_tokens((string) beryon_opt('home.featured_more')) }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" style="width:17px;transform:scaleX(-1)"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a></p>
    </div>
  </section>

  <!-- ===== مارکی گواهینامه‌ها ===== -->
  <section class="certs-band" aria-label="{{ beryon_opt('home.certs_label') }}">
    <span class="label">{{ beryon_opt('home.certs_label') }}</span>
    <div class="marquee">
      <div class="track">
@foreach (array_merge(beryon_rows('home.cert_pills'), beryon_rows('home.cert_pills')) as $pill)
        <span class="cert-pill">@php(beryon_svg('cert-pill')){{ $pill }}</span>
@endforeach
      </div>
    </div>
  </section>

  <!-- ===== وبلاگ ===== -->
  <section class="section">
    <div class="container">
      <div class="sec-head reveal">
        <span class="ribbon-tag">{{ beryon_opt('home.blog_tag') }}</span>
        <h2>{{ beryon_opt('home.blog_title') }}</h2>
        <p>{{ beryon_opt('home.blog_desc') }}</p>
        <span class="underline"></span>
      </div>
      <div class="grid blog-grid">
@foreach ($posts as $index => $post)
        <article class="blog-card reveal{{ $index % 3 === 1 ? ' d1' : ($index % 3 === 2 ? ' d2' : '') }}">
          <a class="thumb" href="{{ esc_url($post['link']) }}">{!! beryon_image_tag((string) $post['image'], ['alt' => (string) $post['alt']]) !!}<span class="date">{{ beryon_fa($post['date']) }}</span></a>
          <div class="body">
            <h3><a href="{{ esc_url($post['link']) }}">{{ $post['title'] }}</a></h3>
            <p>{{ $post['excerpt'] }}</p>
            <a class="more" href="{{ esc_url($post['link']) }}">{{ beryon_opt('blog.more_label') }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
          </div>
        </article>
@endforeach
      </div>
    </div>
  </section>

  <!-- ===== بنر نمایندگی/کاتالوگ ===== -->
  <section class="section" style="padding-top:0">
    <div class="container">
      <div class="cta-banner reveal">
        <div>
          <h2>{{ beryon_opt('home.cta_title') }}</h2>
          <p>{{ beryon_opt('home.cta_text') }}</p>
          <div class="actions">
            <a class="btn btn-light" href="{{ esc_url(beryon_page_url('agency', 'agency')) }}">{{ beryon_opt('home.cta_btn_1') }}</a>
            <a class="btn btn-outline-light" href="{{ esc_url(beryon_page_url('contact-us', 'contact')) }}">{{ beryon_opt('home.cta_btn_2') }}</a>
          </div>
        </div>
        <div class="side">
@foreach (beryon_rows('home.cta_items') as $item)
          <div class="item">@php(beryon_svg((string) ($item['icon'] ?? ''))){{ beryon_tokens((string) ($item['text'] ?? '')) }}</div>
@endforeach
        </div>
      </div>
    </div>
  </section>

@endsection
