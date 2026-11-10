{{--
  صفحه تکی محصول — قالب بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق product-single.html قالب HTML نهایی است:
  breadcrumb → page-hero → گالری (تصویر اصلی + بندانگشتی‌ها + لایت‌باکس) →
  نشان‌ها → عنوان → توضیح → دکمه‌های استعلام → اشتراک‌گذاری →
  تب‌ها (توضیحات | مشخصات | نگهداری) → محصولات مرتبط.

  هوک‌های JS که نباید تغییر کنند:
    #ps-main ، .thumbs button[data-img] ، [data-lightbox] ، [data-tabs] ،
    .tab-btns button[data-target] ، .tab-panel
--}}
@extends('layouts.app')

@section('content')
@php
  $product_id = (int) get_the_ID();
  $product    = \App\Products::normalize($product_id);
  $gallery    = beryon_product_gallery($product_id);
  $main_image = $gallery[0] ?? (string) ($product['image'] ?? '');
  $related    = \App\Products::related($product_id, (int) beryon_opt('products.related_count', 4));
  $cat_link   = (string) ($product['cat_link'] ?? beryon_products_url());
  $cat_name   = (string) ($product['cat_name'] ?? '');
  $keep_html  = (string) ($product['keep_html'] ?? '');
  $share_url  = rawurlencode((string) get_permalink($product_id));
  $share_text = rawurlencode((string) ($product['title'] ?? ''));
@endphp
  <section class="page-hero" style="padding-bottom:120px">
    <div class="container">
@php
  $crumbs = [
      ['label' => 'خانه', 'url' => home_url('/')],
      ['label' => (string) beryon_opt('products.archive_crumb', 'محصولات'), 'url' => beryon_products_url()],
  ];

  if ($cat_name !== '') {
      $crumbs[] = ['label' => $cat_name, 'url' => $cat_link];
  }

  $crumbs[] = ['label' => beryon_product_crumb((string) ($product['title'] ?? ''))];
@endphp
      @include('components.breadcrumb', ['items' => $crumbs])
      <p style="font-size:clamp(1.05rem,2.2vw,1.45rem);font-weight:800;color:#fff;margin-top:6px">{{ beryon_opt('products.sample_note') }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section-tight" style="padding-top:0">
    <div class="container">
      <div class="product-single">
        <div class="gallery">
          <div class="main-img"><img id="ps-main" src="{{ esc_url($main_image) }}" alt="{{ $product['title'] ?? '' }}" style="transition:opacity .2s"></div>
          <div class="thumbs" role="group" aria-label="تصاویر محصول">
@foreach ($gallery as $index => $image)
            <button class="{{ $index === 0 ? 'active' : '' }}" data-img="{{ esc_url($image) }}" aria-label="نمای {{ beryon_fa(beryon_nth_word($index)) }}"><img src="{{ esc_url($image) }}" alt="" loading="lazy"></button>
@endforeach
          </div>
          <div class="ps-actions" style="margin-top:18px">
            <button class="btn btn-ghost btn-sm" data-lightbox="{{ esc_url($main_image) }}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/></svg>
              {{ beryon_opt('products.lightbox_label') }}
            </button>
          </div>
        </div>

        <div class="ps-info">
          <div class="badges">
@if (! empty($product['cat_name']))
            <span class="badge">{{ $product['cat_name'] }}</span>
@endif
@if (! empty($product['meat']))
            <span class="badge gold">{{ beryon_fa($product['meat']) }}٪ {{ $product['meat_type'] }}</span>
            <span class="badge green">بدون نگهدارنده مصنوعی</span>
@elseif (($product['category'] ?? '') === 'plant')
            <span class="badge green">گیاهی</span>
@elseif (($product['category'] ?? '') === 'frozen')
            <span class="badge blue">آماده طبخ</span>
@endif
          </div>
          <h1>{{ $product['title'] ?? '' }}</h1>
          <p class="desc">{{ beryon_tokens((string) ($product['short'] ?? '')) }}</p>

          <div class="ps-actions">
            <a class="btn btn-primary" href="{{ esc_url(beryon_page_url('agency', 'agency')) }}">{{ beryon_opt('products.inquiry_label') }}</a>
            <a class="btn btn-ghost" href="{{ esc_url($cat_link) }}">سایر {{ $cat_name }}</a>
          </div>

          <div class="share-row">
            <span>{{ beryon_opt('products.share_label') }}</span>
            <a href="https://t.me/share/url?url={{ $share_url }}&text={{ $share_text }}" target="_blank" rel="noopener" aria-label="اشتراک در تلگرام"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M21.9 4.4 18.9 19c-.2 1-.8 1.2-1.7.8l-4.6-3.4-2.2 2.1c-.3.3-.5.5-.9.5l.3-4.7L18.4 6c.4-.3-.1-.5-.6-.2L7.2 12.5l-4.5-1.4c-1-.3-1-1 .2-1.4L20.6 3c.8-.3 1.5.2 1.3 1.4z"/></svg></a>
            <a href="https://wa.me/?text={{ $share_text }}%20{{ $share_url }}" target="_blank" rel="noopener" aria-label="اشتراک در واتس‌اپ"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2zm5.2 14.2c-.2.6-1.2 1.2-1.7 1.2-.4.1-1 .1-1.6-.1-.4-.1-.9-.3-1.5-.5-2.6-1.1-4.3-3.8-4.4-4-.1-.2-1.1-1.4-1.1-2.7s.7-1.9.9-2.2c.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .6.5s.8 1.9.8 2c.1.1.1.3 0 .5-.3.6-.7.9-.5 1.2.7 1.2 1.6 2 2.8 2.6.3.2.5.1.7-.1l.9-1c.2-.3.4-.2.7-.1l2 1c.3.1.5.2.6.4 0 .1 0 .7-.2 1.3z"/></svg></a>
            <a href="{{ esc_url((string) get_permalink($product_id)) }}" data-copy-link aria-label="کپی لینک"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg></a>
          </div>

          <div class="tabs" data-tabs>
            <div class="tab-btns" role="tablist">
              <button class="active" data-target="#tab-desc" role="tab">{{ beryon_opt('products.tab_desc') }}</button>
              <button data-target="#tab-spec" role="tab">{{ beryon_opt('products.tab_spec') }}</button>
              <button data-target="#tab-keep" role="tab">{{ beryon_opt('products.tab_keep') }}</button>
            </div>
            <div class="tab-panel active" id="tab-desc">
              <p style="font-size:.93rem">{{ beryon_tokens((string) ($product['short'] ?? '')) }}</p>
@if (! empty($product['desc_html']))
              {!! beryon_tokens((string) $product['desc_html']) !!}
@else
              <ul style="margin-top:12px">
@if (! empty($product['meat']))
                <li>درصد بالای گوشت: {{ beryon_fa($product['meat']) }}% {{ $product['meat_type'] }} تازه برای بافتی آبدار و مغذی</li>
@endif
@if (! empty($product['type_label']))
                <li>نوع محصول: {{ $product['type_label'] }}{{ ! empty($product['cat_name']) ? ' از گروه ' . $product['cat_name'] : '' }}</li>
@endif
@if (! empty($product['unit_weight']))
                <li>وزن هر قطعه {{ $product['unit_weight'] }}؛ بسته‌بندی {{ $product['packaging'] }}</li>
@endif
                <li>تولیدشده در کارخانه بریان گوشت آمل با استانداردهای ISO 22000، HACCP و GMP</li>
              </ul>
@endif
            </div>
            <div class="tab-panel" id="tab-spec">
              @include('woocommerce.single-product.spec-table', ['product' => $product])
            </div>
            <div class="tab-panel" id="tab-keep">
              <ul>
@foreach (\App\Products::lines_to_array($keep_html !== '' ? $keep_html : (string) beryon_opt('products.default_keep', '')) as $line)
                <li>{{ $line }}</li>
@endforeach
              </ul>
            </div>
          </div>
        </div>
      </div>
@if ($related !== [])

      <div class="sec-head reveal" style="margin-top:80px">
        <span class="ribbon-tag">{{ beryon_opt('products.related_tag') }}</span>
        <h2>{{ beryon_opt('products.related_title') }}</h2>
        <span class="underline"></span>
      </div>
      <div class="grid products-grid">
@include('components.products-grid', ['products' => $related])
      </div>
@endif
    </div>
  </section>
@endsection
