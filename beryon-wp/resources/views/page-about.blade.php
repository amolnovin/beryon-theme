{{--
  Template Name: درباره بریون
--}}
{{--
  صفحه درباره بریون — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق about.html قالب HTML نهایی است؛ متن‌ها از پنل
  «تنظیمات قالب بریون → درباره، مدیرعامل و چارت» (گروه about) می‌آیند.
--}}
@extends('layouts.app')

@section('content')
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => beryon_opt('about.crumb', 'درباره ما')],
      ]])
      <h1>{{ beryon_tokens((string) beryon_opt('about.title')) }}</h1>
      <p>{{ beryon_tokens((string) beryon_opt('about.lead')) }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:56px">
    <div class="container">
      <div class="about-split">
        <div class="about-media reveal">
          {!! beryon_image_tag((string) beryon_opt('about.image', 'factory.webp'), ['alt' => beryon_opt('about.image_alt'), 'class' => 'main']) !!}
          <div class="exp"><b>{{ beryon_opt('about.exp_value') }}</b><small>{{ beryon_opt('about.exp_label') }}</small></div>
        </div>
        <div class="about-content reveal d1">
          <span class="ribbon-tag">{{ beryon_opt('about.tag') }}</span>
          <h2>{{ beryon_opt('about.heading') }}</h2>
@foreach (beryon_rows('about.paragraphs') as $paragraph)
          <p>{{ beryon_tokens((string) $paragraph) }}</p>
@endforeach
          <ul class="check-list">
@foreach (beryon_rows('about.checks') as $check)
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m8.5 12.5 2.5 2.5 5-6"/></svg>{{ beryon_tokens((string) $check) }}</li>
@endforeach
          </ul>
        </div>
      </div>
    </div>
  </section>

  <section class="section" style="background:#fff">
    <div class="container">
      <div class="sec-head reveal">
        <span class="ribbon-tag">{{ beryon_opt('about.timeline_tag') }}</span>
        <h2>{{ beryon_opt('about.timeline_title') }}</h2>
        <p>{{ beryon_opt('about.timeline_desc') }}</p>
        <span class="underline"></span>
      </div>
      <div class="timeline">
@foreach (beryon_rows('about.timeline') as $event)
        <div class="tl-item reveal"><span class="card"><span class="year">{{ $event['year'] ?? '' }}</span><h3>{{ $event['title'] ?? '' }}</h3><p>{{ $event['text'] ?? '' }}</p></span></div>
@endforeach
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="sec-head reveal">
        <span class="ribbon-tag">{{ beryon_opt('about.cards_tag') }}</span>
        <h2>{{ beryon_opt('about.cards_title') }}</h2>
        <span class="underline"></span>
      </div>
      <div class="grid blog-grid">
        <a class="blog-card reveal" href="{{ esc_url(beryon_page_url('about-us/introduction-of-ceo', 'ceo')) }}">
          <span class="thumb" style="background:linear-gradient(135deg,var(--brand-soft),#fff);display:grid;place-items:center;height:180px"><svg viewBox="0 0 24 24" width="64" fill="none" stroke="#d5232c" stroke-width="1.6" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
          <span class="body"><h3>معرفی مدیرعامل</h3><p>دکتر بهرام شهره؛ دامپزشک، فیزیولوژیست و استاد دانشگاه با بیش از سه دهه تجربه علمی.</p><span class="more">مشاهده صفحه <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span></span>
        </a>
        <a class="blog-card reveal d1" href="{{ esc_url(beryon_page_url('about-us/organizational-chart', 'org')) }}">
          <span class="thumb" style="background:linear-gradient(135deg,var(--brand-soft),#fff);display:grid;place-items:center;height:180px"><svg viewBox="0 0 24 24" width="64" fill="none" stroke="#d5232c" stroke-width="1.6" stroke-linecap="round"><rect x="9" y="2" width="6" height="5" rx="1.5"/><rect x="2" y="16" width="6" height="5" rx="1.5"/><rect x="16" y="16" width="6" height="5" rx="1.5"/><path d="M12 7v5M5 16v-2h14v2M12 12v2"/></svg></span>
          <span class="body"><h3>چارت سازمانی</h3><p>ساختار واحدهای تولید، کیفیت، فروش و بازرگانی بریان گوشت آمل.</p><span class="more">مشاهده صفحه <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span></span>
        </a>
        <a class="blog-card reveal d2" href="{{ esc_url(beryon_page_url('about-us/certificates', 'certificates')) }}">
          <span class="thumb" style="background:linear-gradient(135deg,var(--brand-soft),#fff);display:grid;place-items:center;height:180px"><svg viewBox="0 0 24 24" width="64" fill="none" stroke="#d5232c" stroke-width="1.6" stroke-linecap="round"><circle cx="12" cy="9" r="6"/><path d="M15.5 14 17 22l-5-3-5 3 1.5-8M10 9l1.5 1.5L14.5 7"/></svg></span>
          <span class="body"><h3>گواهینامه‌ها و افتخارات</h3><p>استانداردهای ISO، HACCP، GMP و تندیس‌های ملی و بین‌المللی بریون.</p><span class="more">مشاهده صفحه <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span></span>
        </a>
      </div>
    </div>
  </section>
@endsection
