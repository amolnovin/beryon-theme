{{--
  پرداخت — قالب وردپرس بریون
  ------------------------------------------------------------------
  سایت بریون معرفی محصول و استعلام است (بدون فروش آنلاین)، اما برای سازگاری
  کامل با ووکامرس این نما هم با همان هویت بصری قالب (page-hero ، container ،
  cta-banner) رندر می‌شود تا در صورت فعال بودن فروشگاه طراحی نشکند.
--}}
@extends('layouts.app')

@section('content')
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => 'پرداخت'],
      ]])
      <h1>پرداخت</h1>
      <p>تکمیل اطلاعات ارسال و ثبت نهایی سفارش.</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:56px">
    <div class="container">
      <div class="form-card reveal" style="padding:28px">
        @php(do_action('woocommerce_before_checkout_form'))
        @php(woocommerce_checkout())
        @php(do_action('woocommerce_after_checkout_form'))
      </div>

      <div class="cta-banner reveal" style="grid-template-columns:1fr;margin-top:44px;padding:34px 40px">
        <div>
          <h2 style="font-size:1.25rem">{{ beryon_opt('agency.cta_title', 'برای خرید عمده و دریافت لیست قیمت، درخواست نمایندگی ثبت کنید.') }}</h2>
          <p>{{ beryon_opt('agency.cta_text', 'کارشناسان فروش بریون شرایط پخش، سهمیه و قیمت عمده را اعلام می‌کنند.') }}</p>
          <div class="actions">
            <a class="btn btn-light" href="{{ esc_url(beryon_products_url()) }}">مشاهده محصولات</a>
            <a class="btn btn-outline-light" href="{{ esc_url(beryon_page_url('agency', 'agency')) }}">{{ beryon_opt('products.inquiry_label') }}</a>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
