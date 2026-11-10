{{--
  Template Name: تماس با بریون
--}}
{{--
  تماس با ما — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق contact.html است؛ فقط «name» فیلدها برای ارسال به
  REST (beryon/v1/form) و یک فیلد هانی‌پات ضدربات اضافه شده که با CSS
  پنهان می‌شود و هیچ اثری روی ظاهر صفحه ندارد.

  هوک‌های JS: form[data-form] ، .form-success.show ، .track
--}}
@extends('layouts.app')

@section('content')
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => beryon_opt('contact.crumb', 'تماس با ما')],
      ]])
      <h1>{{ beryon_opt('contact.title') }}</h1>
      <p>{{ beryon_opt('contact.lead') }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:56px">
    <div class="container">
      <div class="contact-grid">
        <div class="info-cards reveal">
@foreach (beryon_rows('contact.cards') as $card)
@php
  $card_href = beryon_card_href((string) ($card['href'] ?? ''));
@endphp
@if ($card_href !== '')
          <a class="info-card" href="{{ esc_url($card_href) }}">
            <span class="ic">@php(beryon_svg($card['icon'] ?? 'phone'))</span>
            <div><b>{{ $card['title'] ?? '' }}</b><p>{{ beryon_tokens((string) ($card['text'] ?? '')) }}</p></div>
          </a>
@else
          <div class="info-card">
            <span class="ic">@php(beryon_svg($card['icon'] ?? 'pin'))</span>
            <div><b>{{ $card['title'] ?? '' }}</b><p>{{ beryon_tokens((string) ($card['text'] ?? '')) }}</p></div>
          </div>
@endif
@endforeach
          <a class="map-card" href="{{ esc_url(beryon_opt('general.map_url')) }}" target="_blank" rel="noopener">
            <div>
              <span class="pin">@php(beryon_svg('pin'))</span>
              <p>{{ beryon_opt('contact.map_text') }}</p>
            </div>
          </a>
        </div>

        <div class="form-card reveal d1">
          <form data-form="contact" method="post" action="{{ esc_url(beryon_form_url()) }}" novalidate>
            <h2 style="font-size:1.2rem;margin-bottom:20px">{{ beryon_opt('contact.form_title') }}</h2>
            <div class="form-grid">
              <div class="field"><label for="c-name">نام <span class="req">*</span></label><input id="c-name" name="name" type="text" required placeholder="نام و نام خانوادگی" autocomplete="name"><span class="err">لطفاً نام خود را وارد کنید.</span></div>
              <div class="field"><label for="c-tel">شماره همراه <span class="req">*</span></label><input id="c-tel" name="tel" type="tel" inputmode="tel" required placeholder="09xxxxxxxxx" autocomplete="tel"><span class="err">شماره همراه معتبر وارد کنید.</span></div>
              <div class="field full"><label for="c-dep">موارد مربوطه</label>
                <select id="c-dep" name="department">
@foreach (beryon_rows('contact.departments') as $department)
                  <option>{{ $department }}</option>
@endforeach
                </select>
              </div>
              <div class="field full"><label for="c-msg">پیغام <span class="req">*</span></label><textarea id="c-msg" name="message" required placeholder="متن پیام شما…"></textarea><span class="err">لطفاً متن پیام را بنویسید.</span></div>
              <div class="hp-field" aria-hidden="true"><label for="c-hp">لطفاً این فیلد را خالی بگذارید</label><input id="c-hp" type="text" name="{{ beryon_opt('forms.honeypot_field', 'website_url') }}" tabindex="-1" autocomplete="off" value=""></div>

              <div class="full"><button class="btn btn-primary btn-block" type="submit">ارسال فرم <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="transform:scaleX(-1)"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></button></div>
            </div>
          </form>
          <div class="form-success" role="status">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m5 13 4 4L19 7"/></svg></span>
            <h3>{{ beryon_opt('contact.success_title') }}</h3>
            <p>{{ beryon_opt('contact.success_text') }}</p>
            <span class="track"></span>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
