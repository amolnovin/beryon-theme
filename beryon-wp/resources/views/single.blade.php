{{--
  نوشته تکی وبلاگ — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق blog-single.html؛ محتوا از نوشته واقعی وردپرس،
  تاریخ به شمسی، زمان مطالعه محاسبه‌شده و سایدبار از آخرین نوشته‌ها،
  دسته‌های وبلاگ و کاتالوگ پر می‌شود.
--}}
@extends('layouts.app')

@section('content')
@php
  $post_id   = (int) get_the_ID();
  $post_data = beryon_post_card($post_id);
  $latest    = array_values(array_filter(beryon_latest_posts(4), static function ($item) use ($post_id) {
      return (int) $item['id'] !== $post_id;
  }));
  $latest    = array_slice($latest, 0, 3);
  $categories = get_categories(['hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC']);
@endphp
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => beryon_opt('blog.crumb', 'وبلاگ'), 'url' => beryon_blog_url()],
        ['label' => beryon_excerpt_text(get_the_title(), 40)],
      ]])
      <h1>{{ get_the_title() }}</h1>
      <p>{{ post_password_required($post_id) ? '' : beryon_excerpt_text(wp_strip_all_tags((string) get_post_field('post_excerpt', $post_id) ?: get_post_field('post_content', $post_id)), 130) }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:56px">
    <div class="container">
      <div class="post-layout">
        <article class="post-card">
@if (! empty($post_data['image']))
          <div class="cover"><img src="{{ esc_url(beryon_img((string) $post_data['image'])) }}" alt="{{ $post_data['alt'] }}"></div>
@endif
          <div class="post-body">
            <div class="post-meta">
              <span class="item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>{{ $post_data['date'] }}</span>
              <span class="item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>{{ beryon_opt('blog.author_label') }}</span>
              <span class="item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>{{ beryon_opt('blog.read_time_label') }}: {{ beryon_fa($post_data['minutes']) }} دقیقه</span>
            </div>
            {!! beryon_post_content($post_id) !!}

            <div class="author-box">
              <span class="av">{{ beryon_initial(beryon_opt('general.brand_name')) }}</span>
              <div><b>{{ beryon_opt('blog.author_box_title') }}</b><small>{!! beryon_amp(beryon_opt('blog.author_note')) !!}</small></div>
            </div>
          </div>
        </article>

        <aside class="sidebar">
@if ($latest !== [])
          <div class="side-box">
            <h3>{{ beryon_opt('blog.sidebar_latest') }}</h3>
@foreach ($latest as $item)
            <div class="mini-post">@if ((string) $item['image'] !== '')<img src="{{ esc_url(beryon_img((string) $item['image'])) }}" alt="" loading="lazy">@endif<div><a href="{{ esc_url($item['link']) }}">{{ beryon_excerpt_text($item['title'], 52) }}</a><small>{{ $item['date'] }}</small></div></div>
@endforeach
          </div>
@endif
@if (! empty($categories))
          <div class="side-box">
            <h3>{{ beryon_opt('blog.sidebar_cats') }}</h3>
            <div class="filter-chips" style="gap:8px">
@foreach ($categories as $category)
              <span class="chip" data-href="{{ esc_url((string) get_category_link($category)) }}">{{ $category->name }}</span>
@endforeach
            </div>
          </div>
@endif
          <div class="side-box side-cta">
            <h3>{{ beryon_opt('blog.sidebar_catalog_title') }}</h3>
            <p>{{ beryon_tokens((string) beryon_opt('blog.sidebar_catalog_text')) }}</p>
            <a class="btn btn-light btn-block" href="{{ esc_url(beryon_page_url('catalog', 'catalog')) }}">{{ beryon_opt('blog.sidebar_catalog_btn') }}</a>
          </div>
        </aside>
      </div>
    </div>
  </section>
@endsection
