{{--
  بدنه فهرست وبلاگ — قالب وردپرس بریون
  ------------------------------------------------------------------
  بین home.blade.php (صفحه وبلاگ) و archive.blade.php (دسته/برچسب/بایگانی
  تاریخ) مشترک است تا مارک‌آپ یکسان بماند و فقط عنوان‌ها عوض شوند.

  ورودی: $opt_group = کلید گروه تنظیمات («blog» یا «blog_archive»)
--}}
  <section class="section" style="padding-top:56px">
    <div class="container">
      <h2 class="sr-only">{{ beryon_opt($opt_group . '.sr') }}</h2>
      <div class="grid blog-grid">
@forelse (beryon_query_posts() as $index => $post)
        <article class="blog-card reveal{{ $index % 3 === 1 ? ' d1' : ($index % 3 === 2 ? ' d2' : '') }}">
          <a class="thumb" href="{{ esc_url($post['link']) }}"><img src="{{ esc_url($post['image']) }}" alt="{{ $post['alt'] }}" loading="lazy"><span class="date">{{ $post['date'] }}</span></a>
          <div class="body">
            <h3><a href="{{ esc_url($post['link']) }}">{{ $post['title'] }}</a></h3>
            <p>{{ $post['excerpt'] }}</p>
            <a class="more" href="{{ esc_url($post['link']) }}">{{ beryon_opt($opt_group . '.more_label') }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
          </div>
        </article>
@empty
        <p class="no-result show">{{ beryon_opt($opt_group . '.empty') }}</p>
@endforelse
      </div>

{!! beryon_pagination() !!}

      <div class="cta-banner reveal" style="grid-template-columns:1fr;margin-top:56px;padding:38px 44px">
        <div>
          <h2 style="font-size:1.3rem">{{ beryon_opt($opt_group . '.newsletter_title') }}</h2>
          <p>{{ beryon_opt($opt_group . '.newsletter_text') }}</p>
          <form data-form="newsletter" method="post" action="{{ esc_url(beryon_form_url()) }}" novalidate style="display:flex;gap:12px;flex-wrap:wrap;margin-top:20px">
            <div class="field" style="flex:1;min-width:220px"><label for="nl" class="skip-link" style="position:absolute">ایمیل</label><input id="nl" name="email" type="email" required placeholder="ایمیل شما" autocomplete="email" style="border:none;background:#fff"></div>
            <div class="hp-field" aria-hidden="true"><label for="nl-hp" class="skip-link" style="position:absolute">این فیلد را خالی بگذارید</label><input id="nl-hp" type="text" name="{{ beryon_opt('forms.honeypot_field', 'website_url') }}" tabindex="-1" autocomplete="off" value="" style="border:none;background:#fff"></div>
            <button class="btn btn-light" type="submit">{{ beryon_opt($opt_group . '.newsletter_btn') }}</button>
          </form>
        </div>
      </div>
      <div class="form-success" role="status" style="background:#fff;border:1px solid var(--line);border-radius:20px;margin-top:18px">
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m5 13 4 4L19 7"/></svg></span>
        <h3>{{ beryon_opt($opt_group . '.newsletter_success_title') }}</h3>
        <p>{{ beryon_opt($opt_group . '.newsletter_success_text') }}</p>
      </div>
    </div>
  </section>
