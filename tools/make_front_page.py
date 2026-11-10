#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
تبدیل _src/pages/index.html → beryon-wp/resources/views/front-page.blade.php

هر جایگزینی باید دقیقاً یک‌بار匹配 شود؛ در غیر این‌صورت اسکریپت با خطا متوقف می‌شود
تا مارک‌آپ به‌صورت خام و ناخواسته باقی نماند.
"""
import re
import sys

SRC = '_src/pages/index.html'
DST = 'beryon-wp/resources/views/front-page.blade.php'

html = open(SRC, encoding='utf-8').read()
assert html.startswith('<main id="main">')
body = html[len('<main id="main">'):].rstrip()
body = body[: -len('</main>')].rstrip('\n')

PAIRS = []


def sub(old, new, count=1):
    PAIRS.append((old, new, count))


# ------------------------------------------------------------------ هیرو
sub('<span class="hero-badge"><i></i> از سال ۱۳۸۴ | شهرک صنعتی آمل</span>',
    '<span class="hero-badge"><i></i> {{ beryon_tokens((string) beryon_opt(\'home.hero_badge\')) }}</span>')

sub('<h1>بریون؛ طعم اصیل <span class="hl">فرآورده‌های گوشتی</span> با بالاترین درصد گوشت</h1>',
    '<h1>{{ beryon_tokens((string) beryon_opt(\'home.hero_title_1\')) }} <span class="hl">{{ beryon_tokens((string) beryon_opt(\'home.hero_title_hl\')) }}</span> {{ beryon_tokens((string) beryon_opt(\'home.hero_title_2\')) }}</h1>')

sub('<p class="lead">شرکت بریان گوشت آمل با برند تجاری بریون، تولیدکننده بیش از ۶۱ قلم سوسیس، کالباس، ژامبون، محصولات انجمادی و گیاهی با استانداردهای بین‌المللی ISO 22000 و HACCP؛ بدون مواد نگهدارنده مصنوعی.</p>',
    '<p class="lead">{{ beryon_tokens((string) beryon_opt(\'home.hero_lead\')) }}</p>')

sub('<a class="btn btn-primary" href="products.html">',
    '<a class="btn btn-primary" href="{{ esc_url(beryon_products_url()) }}">')
sub('''<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>
            مشاهده محصولات''',
    '''@php(beryon_svg('box'))
            {{ beryon_opt('home.hero_btn_primary') }}''')
sub('<a class="btn btn-ghost" href="catalog.html">',
    '<a class="btn btn-ghost" href="{{ esc_url(beryon_page_url(\'catalog\', \'catalog\')) }}">')
sub('''<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            دانلود کاتالوگ''',
    '''@php(beryon_svg('download'))
            {{ beryon_opt('home.hero_btn_secondary') }}''')

# svgهای دکمه‌های هیرو → برون‌ریزی از رجیستری آیکون‌ها
HERO_BTN_SVG = re.search(r'<a class="btn btn-primary" href="\{\{ esc_url\(beryon_products_url\(\)\) \)\}">\s*(<svg.*?</svg>)', body, re.S)

# آمار هیرو
HERO_STATS = re.search(r'        <div class="hero-stats">\n(.*?)\n        </div>', body, re.S).group(0)
sub(HERO_STATS, '''        <div class="hero-stats">
@foreach (beryon_rows('home.hero_stats') as $stat)
          <div class="stat"><b><span data-count="{{ beryon_en_digits((string) beryon_tokens((string) ($stat['value'] ?? ''))) }}">۰</span>{{ $stat['suffix'] ?? '' }}</b><small>{{ $stat['label'] ?? '' }}</small></div>
@endforeach
        </div>''')

# تصویر هیرو
HERO_IMG = re.search(r'        <div class="frame"><img src="assets/img/hero.webp"[^>]*></div>', body, re.S).group(0)
sub(HERO_IMG, '''        <div class="frame">{!! beryon_image_tag((string) beryon_opt('home.hero_image', 'hero.webp'), ['alt' => beryon_tokens((string) beryon_opt('home.hero_image_alt')), 'width' => (int) beryon_opt('home.hero_image_w', 620), 'height' => (int) beryon_opt('home.hero_image_h', 460), 'lazy' => false]) !!}</div>''')

# کارت‌های شناور
FLOAT1 = re.search(r'        <div class="float-card f1">\n.*?\n        </div>', body, re.S).group(0)
sub(FLOAT1, '''        <div class="float-card f1">
          <span class="ic">@php(beryon_svg((string) beryon_opt('home.hero_float_1_icon', 'shield-check')))</span>
          <span>{{ beryon_opt('home.hero_float_1') }}<small>{{ beryon_opt('home.hero_float_1_sub') }}</small></span>
        </div>''')
FLOAT2 = re.search(r'        <div class="float-card f2">\n.*?\n        </div>', body, re.S).group(0)
sub(FLOAT2, '''        <div class="float-card f2">
          <span class="ic">@php(beryon_svg((string) beryon_opt('home.hero_float_2_icon', 'leaf')))</span>
          <span>{{ beryon_opt('home.hero_float_2') }}<small>{{ beryon_opt('home.hero_float_2_sub') }}</small></span>
        </div>''')

# ------------------------------------------------------------------ دسته‌ها
sub('''        <span class="ribbon-tag">سبد محصولات</span>
        <h2>دسته‌بندی محصولات بریون</h2>
        <p>طیف گسترده‌ای از فرآورده‌های گوشتی و گیاهی با درصد گوشت ۴۰ تا ۹۰ درصد، ویژه مصرف خانگی و فودسرویس</p>''',
    '''        <span class="ribbon-tag">{{ beryon_opt('home.cats_tag') }}</span>
        <h2>{{ beryon_opt('home.cats_title') }}</h2>
        <p>{{ beryon_opt('home.cats_desc') }}</p>''')
sub('      <div class="grid cats-grid">{{CAT_STRIP}}</div>',
    '''      <div class="grid cats-grid">
@include('components.cats-grid', ['cats' => $categories])
      </div>''')

# ------------------------------------------------------------------ چرا بریون
sub('''        <span class="ribbon-tag">مزیت رقابتی</span>
        <h2>چرا محصولات بریون بهترین هستند؟</h2>
        <p>از انتخاب گوشت تازه تا بسته‌بندی بهداشتی، همه‌چیز با دقت و بر پایه علم روز صنایع گوشتی</p>''',
    '''        <span class="ribbon-tag">{{ beryon_opt('home.features_tag') }}</span>
        <h2>{{ beryon_opt('home.features_title') }}</h2>
        <p>{{ beryon_opt('home.features_desc') }}</p>''')

FEATURES = re.search(r'      <div class="grid features">\n(.*?)\n      </div>', body, re.S).group(0)
sub(FEATURES, '''      <div class="grid features">
@foreach (beryon_rows('home.features') as $index => $feature)
        <article class="feature reveal{{ $index % 3 === 1 ? ' d1' : ($index % 3 === 2 ? ' d2' : '') }}">
          <span class="ic">@php(beryon_svg((string) ($feature['icon'] ?? '')))</span>
          <h3>{{ $feature['title'] ?? '' }}</h3>
          <p>{{ $feature['text'] ?? '' }}</p>
        </article>
@endforeach
      </div>''')

# ------------------------------------------------------------------ درباره
sub('''          <img class="main" src="assets/img/factory.webp" alt="خط تولید مدرن فرآورده‌های گوشتی شرکت بریان گوشت آمل" loading="lazy">
          <div class="exp"><b>+۲۰</b><small>سال تجربه تولید</small></div>''',
    '''          {!! beryon_image_tag((string) beryon_opt('home.about_image', 'factory.webp'), ['alt' => beryon_opt('home.about_image_alt'), 'class' => 'main']) !!}
          <div class="exp"><b>{{ beryon_opt('home.about_exp_value') }}</b><small>{{ beryon_opt('home.about_exp_label') }}</small></div>''')

sub('''          <span class="ribbon-tag">درباره بریون</span>
          <h2>پیشگام در تولید فرآورده‌های گوشتی با بالاترین کیفیت</h2>''',
    '''          <span class="ribbon-tag">{{ beryon_opt('home.about_tag') }}</span>
          <h2>{{ beryon_opt('home.about_title') }}</h2>''')

ABOUT_P = re.search(r'          <p>شرکت بریان گوشت در تیرماه ۱۳۸۴.*?</p>\n          <p>این مجموعه با مشاوره.*?</p>', body, re.S).group(0)
sub(ABOUT_P, '''@foreach (beryon_rows('home.about_paragraphs') as $paragraph)
          <p>{{ $paragraph }}</p>
@endforeach'''.strip('\n'))

CHECKS = re.search(r'          <ul class="check-list">\n(.*?)\n          </ul>', body, re.S).group(0)
sub(CHECKS, '''          <ul class="check-list">
@foreach (beryon_rows('home.about_checks') as $check)
            <li>@php(beryon_svg('check-circle')){{ $check }}</li>
@endforeach
          </ul>''')

sub('''            <a class="btn btn-primary" href="about.html">بیشتر بدانید</a>
            <a class="btn btn-ghost" href="certificates.html">گواهینامه‌ها</a>''',
    '''            <a class="btn btn-primary" href="{{ esc_url(beryon_page_url('about-us', 'about')) }}">{{ beryon_opt('home.about_btn_1') }}</a>
            <a class="btn btn-ghost" href="{{ esc_url(beryon_page_url('about-us/certificates', 'certificates')) }}">{{ beryon_opt('home.about_btn_2') }}</a>''')

# ------------------------------------------------------------------ نوار آمار
STATS = re.search(r'      <div class="grid">\n        <div class="stat reveal">.*?\n      </div>', body, re.S).group(0)
sub(STATS, '''      <div class="grid">
@foreach (beryon_rows('home.stats_band') as $index => $stat)
        <div class="stat reveal{{ $index === 1 ? ' d1' : ($index === 2 ? ' d2' : ($index === 3 ? ' d3' : '')) }}"><b><span data-count="{{ beryon_en_digits((string) beryon_tokens((string) ($stat['value'] ?? ''))) }}">۰</span>{{ $stat['suffix'] ?? '' }}</b><small>{{ $stat['label'] ?? '' }}</small></div>
@endforeach
      </div>''')

# ------------------------------------------------------------------ محصولات ویژه
sub('''        <span class="ribbon-tag">پیشنهادهای بریون</span>
        <h2>محصولات پرفروش و ویژه</h2>
        <p>محبوب‌ترین محصولات بریون در فروشگاه‌ها و فودسرویس‌های سراسر کشور</p>''',
    '''        <span class="ribbon-tag">{{ beryon_opt('home.featured_tag') }}</span>
        <h2>{{ beryon_opt('home.featured_title') }}</h2>
        <p>{{ beryon_opt('home.featured_desc') }}</p>''')
sub('      <div class="grid products-grid">{{GRID:featured}}</div>',
    '''      <div class="grid products-grid">
@include('components.products-grid', ['products' => App\\Products::query(['featured' => true, 'limit' => 8])])
      </div>''')

MORE = re.search(r'      <p class="text-center" style="margin-top:40px">.*?</p>', body, re.S).group(0)
sub(MORE, '''      <p class="text-center" style="margin-top:40px"><a class="btn btn-ghost" href="{{ esc_url(beryon_products_url()) }}">{{ beryon_tokens((string) beryon_opt('home.featured_more')) }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" style="width:17px;transform:scaleX(-1)"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a></p>''')

# ------------------------------------------------------------------ مارکی گواهینامه‌ها
CERTS = re.search(r'  <section class="certs-band".*?\n  </section>', body, re.S).group(0)
sub(CERTS, '''  <section class="certs-band" aria-label="{{ beryon_opt('home.certs_label') }}">
    <span class="label">{{ beryon_opt('home.certs_label') }}</span>
    <div class="marquee">
      <div class="track">
@foreach (array_merge(beryon_rows('home.cert_pills'), beryon_rows('home.cert_pills')) as $pill)
        <span class="cert-pill">@php(beryon_svg('cert-pill')){{ $pill }}</span>
@endforeach
      </div>
    </div>
  </section>''')

# ------------------------------------------------------------------ وبلاگ
sub('''        <span class="ribbon-tag">مجله بریان گوشت</span>
        <h2>تازه‌ترین مقالات و راهنماها</h2>
        <p>دانستنی‌های کاربردی درباره فرآورده‌های گوشتی، آشپزی و تغذیه سالم</p>''',
    '''        <span class="ribbon-tag">{{ beryon_opt('home.blog_tag') }}</span>
        <h2>{{ beryon_opt('home.blog_title') }}</h2>
        <p>{{ beryon_opt('home.blog_desc') }}</p>''')

BLOG = re.search(r'      <div class="grid blog-grid">\n(.*?)\n      </div>', body, re.S).group(0)
sub(BLOG, '''      <div class="grid blog-grid">
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
      </div>''')

# ------------------------------------------------------------------ بنر نمایندگی
CTA = re.search(r'      <div class="cta-banner reveal">\n(.*?)\n      </div>', body, re.S).group(0)
sub(CTA, '''      <div class="cta-banner reveal">
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
      </div>''')

# ------------------------------------------------------------------ اعمال
for old, new, count in PAIRS:
    found = body.count(old)
    if found != count:
        print('FAIL: %d matches (expected %d) for: %s' % (found, count, old[:120]))
        sys.exit(1)
    body = body.replace(old, new, count)

leftover = re.findall(r'<svg[^>]*>.*?</svg>', body, re.S)
print('باقی‌مانده svg درون‌خطی:', len(leftover))
for l in leftover:
    print('   ', l[:110])

HEADER = '''{{--
  صفحه اصلی — قالب بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق index.html قالب HTML نهایی است؛ همه متن‌ها، آمارها،
  مزیت‌ها، افتخارات و فراخوان‌ها از پنل «تنظیمات قالب بریون» می‌آیند و
  محصولات/دسته‌ها/نوشته‌ها از دیتابیس وردپرس خوانده می‌شوند.

  کلاس body: «home» توسط App\\Assets::body_class() اضافه می‌شود تا هدر شفاف
  فقط در صفحه اصلی و فقط در دسکتاپ فعال باشد (CSS خط ۵۹۸-۶۰۱).
--}}
@extends('layouts.app')

@section('content')
@php
  $posts = beryon_latest_posts((int) beryon_opt('home.blog_count', 3));
@endphp
'''

FOOTER = '''
@endsection
'''

open(DST, 'w', encoding='utf-8').write(HEADER + body.strip('\n') + '\n' + FOOTER)
print('written:', DST, len(HEADER + body + FOOTER))
