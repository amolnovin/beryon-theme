{{--
  Template Name: نظرات و شکایات بریون
--}}
{{--
  نظرات و شکایات — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق feedback.html؛ فیلدها از پیش name دارند و فقط
  action فرم (REST) و هانی‌پات اضافه شده است. پیام‌ها در جدول اختصاصی
  قالب ذخیره و در «بریون ← پیام‌های دریافتی» مدیریت می‌شوند.

  هوک‌های JS: form[data-form] ، .acc-item ، .form-success.show ، .track
--}}
@extends('layouts.app')

@section('content')
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => beryon_opt('feedback.crumb', 'نظرات و شکایات')],
      ]])
      <h1>{{ beryon_opt('feedback.title') }}</h1>
      <p>{{ beryon_opt('feedback.lead') }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:56px">
    <div class="container">
      <div class="contact-grid">
        <div class="info-cards reveal">
@foreach (beryon_rows('feedback.cards') as $card)
          <div class="info-card">
            <span class="ic">@php(beryon_svg($card['icon'] ?? 'chat'))</span>
            <div><b>{{ $card['title'] ?? '' }}</b><p>{!! beryon_tokens(beryon_amp($card['text'] ?? '')) !!}</p></div>
          </div>
@endforeach
        </div>

        <div class="form-card reveal d1">
          <form data-form="feedback" method="post" action="{{ esc_url(beryon_form_url()) }}" novalidate>
            <h2 style="font-size:1.2rem;margin-bottom:20px">{{ beryon_opt('feedback.form_title') }}</h2>
            <div class="form-grid">
              <div class="field"><label for="f-name">نام و نام خانوادگی <span class="req">*</span></label><input id="f-name" name="name" type="text" required placeholder="نام کامل"><span class="err">لطفاً نام خود را وارد کنید.</span></div>
              <div class="field"><label for="f-tel">شماره همراه <span class="req">*</span></label><input id="f-tel" name="tel" type="tel" inputmode="tel" required placeholder="09xxxxxxxxx"><span class="err">شماره همراه معتبر وارد کنید (مثال: 09123456789).</span></div>
              <div class="field"><label for="f-email">ایمیل (اختیاری)</label><input id="f-email" name="email" type="email" placeholder="you@example.com"><span class="err">ایمیل معتبر وارد کنید.</span></div>
              <div class="field"><label for="f-subj">موضوع</label>
                <select id="f-subj" name="subject">
@foreach (beryon_rows('feedback.subjects') as $subject)
                  <option>{{ $subject }}</option>
@endforeach
                </select>
              </div>
              <div class="field full"><label>نوع پیام <span class="req">*</span></label>
                <div class="radio-pills">
@foreach (beryon_rows('feedback.types') as $index => $type)
                  <input type="radio" id="t{{ $index + 1 }}" name="ptype" value="{{ $type['value'] ?? '' }}"{{ $index === 0 ? ' checked' : '' }}><label for="t{{ $index + 1 }}">{{ $type['label'] ?? '' }}</label>
@endforeach
                </div>
              </div>
              <div class="field full"><label for="f-msg">متن پیام <span class="req">*</span></label><textarea id="f-msg" name="message" required placeholder="شرح پیام خود را بنویسید؛ در صورت مرتبط بودن با سفارش، شماره سفارش را هم ذکر کنید تا سریع‌تر بررسی شود."></textarea><span class="err">لطفاً متن پیام را بنویسید.</span></div>
              <div class="hp-field" aria-hidden="true"><label for="f-hp">لطفاً این فیلد را خالی بگذارید</label><input id="f-hp" type="text" name="{{ beryon_opt('forms.honeypot_field', 'website_url') }}" tabindex="-1" autocomplete="off" value=""></div>

              <div class="full"><button class="btn btn-primary btn-block" type="submit">ارسال پیام <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="transform:scaleX(-1)"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></button></div>
            </div>
          </form>
          <div class="form-success" role="status">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m5 13 4 4L19 7"/></svg></span>
            <h3>{{ beryon_opt('feedback.success_title') }}</h3>
            <p>{{ beryon_opt('feedback.success_text') }}</p>
            <span class="track"></span>
          </div>
        </div>
      </div>

      <div class="sec-head reveal" style="margin-top:80px">
        <span class="ribbon-tag">{{ beryon_opt('feedback.faq_tag') }}</span>
        <h2>{{ beryon_opt('feedback.faq_title') }}</h2>
        <span class="underline"></span>
      </div>
      <div class="accordion reveal">
@foreach (beryon_rows('feedback.faqs') as $faq)
        <div class="acc-item"><button aria-expanded="false">{{ beryon_tokens((string) ($faq['q'] ?? '')) }} <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span></button><div class="panel"><div class="panel-in">{{ beryon_tokens((string) ($faq['a'] ?? '')) }}</div></div></div>
@endforeach
      </div>
    </div>
  </section>
@endsection
