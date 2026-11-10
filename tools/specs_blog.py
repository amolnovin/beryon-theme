#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ساخت نماهای Blade وبلاگ (home/آرشیو نوشته‌ها و single)."""
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from make_pages import build  # noqa: E402
from specs_about_group import swap_breadcrumb, swap_block, save  # noqa: E402
from specs_forms import sub_once, honeypot  # noqa: E402

CARD = """        <article class="blog-card reveal{{ $index % 3 === 1 ? ' d1' : ($index % 3 === 2 ? ' d2' : '') }}">
          <a class="thumb" href="{{ esc_url($post['link']) }}"><img src="{{ esc_url($post['image']) }}" alt="{{ $post['alt'] }}" loading="lazy"><span class="date">{{ $post['date'] }}</span></a>
          <div class="body">
            <h3><a href="{{ esc_url($post['link']) }}">{{ $post['title'] }}</a></h3>
            <p>{{ $post['excerpt'] }}</p>
            <a class="more" href="{{ esc_url($post['link']) }}">{{ beryon_opt('blog.more_label') }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
          </div>
        </article>"""


def home():
    spec = {
        'src': 'blog.html',
        'dst': 'home.blade.php',
        'header': '''{{--
  صفحه وبلاگ (home.blade.php) — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق blog.html؛ کارت‌ها از نوشته‌های واقعی وردپرس با
  تاریخ شمسی خوانده می‌شوند. همین نما برای آرشیو وبلاگ و صفحه اصلی
  نوشته‌ها استفاده می‌شود (index.blade.php).
--}}
''',
        'pairs': [
            ('      <h1>مجله بریان گوشت</h1>',
             "      <h1>{{ beryon_opt('blog.title') }}</h1>"),
            ('      <p>راهنماهای آشپزی، دانستنی‌های تغذیه و اخبار دنیای فرآورده‌های گوشتی؛ محتوایی کاربردی برای سفره سالم‌تر.</p>',
             "      <p>{{ beryon_opt('blog.lead') }}</p>"),
            ('      <h2 class="sr-only">فهرست مقالات مجله بریان گوشت</h2>',
             "      <h2 class=\"sr-only\">{{ beryon_opt('blog.sr') }}</h2>"),
            ('          <h2 style="font-size:1.3rem">عضویت در خبرنامه مجله بریون</h2>',
             "          <h2 style=\"font-size:1.3rem\">{{ beryon_opt('blog.newsletter_title') }}</h2>"),
            ('          <p>هر ماه یک ایمیل شامل رسپی‌های تازه، نکات تغذیه و اطلاع‌یه محصولات جدید؛ بدون اسپم.</p>',
             "          <p>{{ beryon_opt('blog.newsletter_text') }}</p>"),
            ('          <form data-form novalidate style="display:flex;gap:12px;flex-wrap:wrap;margin-top:20px">',
             '          <form data-form="newsletter" method="post" action="{{ esc_url(beryon_form_url()) }}" novalidate style="display:flex;gap:12px;flex-wrap:wrap;margin-top:20px">'),
            ('''            <div class="field" style="flex:1;min-width:220px"><label for="nl" class="skip-link" style="position:absolute">ایمیل</label><input id="nl" type="email" required placeholder="ایمیل شما" style="border:none;background:#fff"></div>''',
             '''            <div class="field" style="flex:1;min-width:220px"><label for="nl" class="skip-link" style="position:absolute">ایمیل</label><input id="nl" name="email" type="email" required placeholder="ایمیل شما" autocomplete="email" style="border:none;background:#fff"></div>''' + '\n            <div class="hp-field" aria-hidden="true"><label for="nl-hp" class="skip-link" style="position:absolute">این فیلد را خالی بگذارید</label><input id="nl-hp" type="text" name="{{ beryon_opt(\'forms.honeypot_field\', \'website_url\') }}" tabindex="-1" autocomplete="off" value="" style="border:none;background:#fff"></div>'),
            ('            <button class="btn btn-light" type="submit">عضویت</button>',
             "            <button class=\"btn btn-light\" type=\"submit\">{{ beryon_opt('blog.newsletter_btn') }}</button>"),
            ('        <h3>عضویت شما ثبت شد</h3>',
             "        <h3>{{ beryon_opt('blog.newsletter_success_title') }}</h3>"),
            ('        <p>از این پس تازه‌های مجله بریون را در ایمیل خود دریافت خواهید کرد.</p>',
             "        <p>{{ beryon_opt('blog.newsletter_success_text') }}</p>"),
        ],
    }

    out = build(spec)
    out = swap_breadcrumb(out, [('خانه', "home_url('/')"), ("beryon_opt('blog.crumb', 'وبلاگ')", None)])

    grid = re.search(r'      <div class="grid blog-grid">\n.*?\n      </div>', out, re.S)
    assert grid
    loop = """      <div class="grid blog-grid">
@forelse (beryon_query_posts() as $index => $post)
%s
@empty
        <p class="no-result show">{{ beryon_opt('blog.empty') }}</p>
@endforelse
      </div>

%s""" % (CARD, '{{ beryon_pagination() }}')
    out = out[:grid.start()] + loop + out[grid.end():]
    save('home.blade.php', out)
    # index.blade.php = همین نما (بایگانی نوشته‌ها)
    save('index.blade.php', out.replace('home.blade.php', 'index.blade.php'))


if __name__ == '__main__':
    home()
