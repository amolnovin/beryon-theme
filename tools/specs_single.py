#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ساخت نمای single.blade.php (نوشته تکی وبلاگ) از blog-single.html."""
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from make_pages import build  # noqa: E402
from specs_about_group import swap_breadcrumb, swap_block, save  # noqa: E402

CAL_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>'
USER_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>'
CLOCK_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>'


def single():
    spec = {
        'src': 'blog-single.html',
        'dst': 'single.blade.php',
        'header': '''{{--
  نوشته تکی وبلاگ — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق blog-single.html؛ محتوا از نوشته واقعی وردپرس،
  تاریخ به شمسی، زمان مطالعه محاسبه‌شده و سایدبار از آخرین نوشته‌ها،
  دسته‌های وبلاگ و کاتالوگ پر می‌شود.
--}}
''',
        'pairs': [],
    }

    out = build(spec)
    out = out.replace("@section('content')\n", """@section('content')
@php
  $post_id   = (int) get_the_ID();
  $post_data = beryon_post_card($post_id);
  $latest    = array_values(array_filter(beryon_latest_posts(4), static function ($item) use ($post_id) {
      return (int) $item['id'] !== $post_id;
  }));
  $latest    = array_slice($latest, 0, 3);
  $categories = get_categories(['hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC']);
@endphp
""", 1)

    out = swap_breadcrumb(out, [
        ('خانه', "home_url('/')"),
        ("beryon_opt('blog.crumb', 'وبلاگ')", "beryon_blog_url()"),
        ("beryon_excerpt_text(get_the_title(), 40)", None),
    ])

    # عنوان و لید هیرو
    out = re.sub(r'      <h1>.*?</h1>\n      <p>.*?</p>',
                 "      <h1>{{ get_the_title() }}</h1>\n      <p>{{ beryon_excerpt_text(wp_strip_all_tags((string) get_post_field('post_excerpt', $post_id) ?: get_post_field('post_content', $post_id)), 130) }}</p>",
                 out, count=1, flags=re.S)

    # پوشش + بدنه مقاله
    out = swap_block(
        out,
        r'        <article class="post-card">\n.*?\n        </article>',
        """        <article class="post-card">
@if (! empty($post_data['image']))
          <div class="cover"><img src="{{ esc_url($post_data['image']) }}" alt="{{ $post_data['alt'] }}"></div>
@endif
          <div class="post-body">
            <div class="post-meta">
              <span class="item">%s{{ $post_data['date'] }}</span>
              <span class="item">%s{{ beryon_opt('blog.author_label') }}</span>
              <span class="item">%s{{ beryon_opt('blog.read_time_label') }}: {{ beryon_fa($post_data['minutes']) }} دقیقه</span>
            </div>
            {!! beryon_post_content($post_id) !!}

            <div class="author-box">
              <span class="av">{{ beryon_initial(beryon_opt('general.brand_name')) }}</span>
              <div><b>{{ beryon_opt('blog.author_box_title') }}</b><small>{{ beryon_amp(beryon_opt('blog.author_note')) }}</small></div>
            </div>
          </div>
        </article>""" % (CAL_SVG, USER_SVG, CLOCK_SVG),
        'post-card',
    )

    # سایدبار
    out = swap_block(
        out,
        r'        <aside class="sidebar">\n.*?\n        </aside>',
        """        <aside class="sidebar">
@if ($latest !== [])
          <div class="side-box">
            <h3>{{ beryon_opt('blog.sidebar_latest') }}</h3>
@foreach ($latest as $item)
            <div class="mini-post"><img src="{{ esc_url($item['image']) }}" alt="" loading="lazy"><div><a href="{{ esc_url($item['link']) }}">{{ beryon_excerpt_text($item['title'], 52) }}</a><small>{{ $item['date'] }}</small></div></div>
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
        </aside>""",
        'sidebar',
    )
    save('single.blade.php', out)


if __name__ == '__main__':
    single()
