{{--
  پانوشت + دکمه بازگشت به بالا + لایت‌باکس — قالب بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق قالب HTML نهایی؛ متن‌ها از تنظیمات قالب می‌آیند و
  ستون‌های لینک از منوهای «footer_quick» و «footer_cats» (یا منوی پیش‌فرض قالب).
  اسکریپت اصلی سایت از راه wp_footer() در چیدمان چاپ می‌شود (defer).
--}}
<!-- فوتر -->
<footer class="footer">
  <div class="container">
    <div class="grid">
      <div>
        <span class="logo-foot">{!! beryon_image_tag(beryon_opt('general.logo_file', 'logo.png'), ['alt' => 'لوگوی ' . beryon_opt('general.brand_name')]) !!}</span>
        <p>{!! beryon_tokens((string) beryon_opt('general.footer_text')) !!}</p>
        <div class="socials">
@foreach ((array) beryon_opt('general.socials', []) as $social)
          <a href="{{ ! empty($social['url']) ? esc_url($social['url']) : '#' }}" aria-label="{{ $social['label'] }}"@if (! empty($social['url'])) target="_blank" rel="noopener"@endif>@php(beryon_svg((string) ($social['icon'] ?? 'link')))</a>
@endforeach
        </div>
      </div>
      <div>
        <h2>{{ beryon_opt('general.footer_quick_title') }}</h2>
        <ul>
{!! beryon_nav('footer_quick', '', 'footer') !!}
        </ul>
      </div>
      <div>
        <h2>{{ beryon_opt('general.footer_cats_title') }}</h2>
        <ul>
{!! beryon_nav('footer_cats', '', 'footer') !!}
        </ul>
      </div>
      <div>
        <h2>ارتباط با ما</h2>
        <div class="contact-line"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg><span>{{ beryon_opt('general.address_full') }}</span></div>
        <div class="contact-line"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.6 2z"/></svg><span>تلفن کارخانه: <a href="tel:{{ beryon_opt('general.phone_factory') }}" style="display:inline">{{ beryon_opt('general.phone_factory_label') }}</a><br>واحد فروش: <a href="tel:{{ beryon_opt('general.phone_sales') }}" style="display:inline">{{ beryon_opt('general.phone_sales_label') }}</a></span></div>
        <div class="contact-line"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m22 7-10 6L2 7"/></svg><span><a href="mailto:{{ beryon_opt('general.email') }}" style="display:inline">{{ beryon_opt('general.email') }}</a></span></div>
        <div class="contact-line"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><span>{{ beryon_opt('general.work_hours') }}</span></div>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <span data-year>{{ beryon_year() }}</span> — {!! beryon_tokens((string) beryon_opt('general.copyright')) !!}</span>
      <span>{!! beryon_tokens((string) beryon_opt('general.credit')) !!}</span>
    </div>
  </div>
</footer>
