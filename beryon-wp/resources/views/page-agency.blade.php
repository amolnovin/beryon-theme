{{--
  Template Name: اعطای نمایندگی بریون
--}}
{{--
  اعطای نمایندگی — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق agency.html؛ فهرست استان‌ها از config/provinces.json
  (یا forms.provinces در تنظیمات) و گزینه‌های نوع فروش/مالکیت از ردیف‌های
  تنظیم‌پذیر می‌آید. «name» فیلدها مطابق متد جمع‌آوری داده app/Forms.php است.

  هوک‌های JS: form[data-form] ، .radio-pills ، .check-line ، .form-success.show
--}}
@extends('layouts.app')

@section('content')
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => beryon_opt('agency.crumb', 'اعطای نمایندگی')],
      ]])
      <h1>{{ beryon_opt('agency.title') }}</h1>
      <p>{{ beryon_tokens((string) beryon_opt('agency.lead')) }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section-tight">
    <div class="container">
      <div class="grid steps">
@foreach (beryon_rows('agency.steps') as $index => $step)
        <article class="step reveal{{ $index === 1 ? ' d1' : ($index === 2 ? ' d2' : ($index === 3 ? ' d3' : '')) }}"><span class="ic">@php(beryon_svg($step['icon'] ?? 'file'))</span><h2>{{ $step['title'] ?? '' }}</h2><p>{{ $step['text'] ?? '' }}</p></article>
@endforeach
      </div>
    </div>
  </section>

  <section class="section" style="padding-top:20px">
    <div class="container">
      <div class="form-card reveal" style="max-width:920px;margin-inline:auto">
        <form data-form="agency" method="post" action="{{ esc_url(beryon_form_url()) }}" novalidate>
          <h2 style="font-size:1.25rem;margin-bottom:8px">{{ beryon_opt('agency.form_title') }}</h2>
          <p style="color:var(--muted);font-size:.88rem;margin-bottom:24px">{{ beryon_opt('agency.form_note') }}</p>

          <h2 class="form-section-title"><span class="n">۱</span> اطلاعات فردی</h2>
          <div class="form-grid">
            <div class="field"><label for="a-fn">نام <span class="req">*</span></label><input id="a-fn" name="first_name" type="text" required autocomplete="given-name"><span class="err">نام الزامی است.</span></div>
            <div class="field"><label for="a-ln">نام خانوادگی <span class="req">*</span></label><input id="a-ln" name="last_name" type="text" required autocomplete="family-name"><span class="err">نام خانوادگی الزامی است.</span></div>
            <div class="field"><label for="a-tel">شماره همراه <span class="req">*</span></label><input id="a-tel" name="tel" type="tel" inputmode="tel" required placeholder="09xxxxxxxxx" autocomplete="tel"><span class="hint">نام کاربری نماینده همین شماره خواهد بود.</span><span class="err">شماره همراه معتبر وارد کنید.</span></div>
            <div class="field"><label for="a-em">ایمیل</label><input id="a-em" name="email" type="email" placeholder="you@example.com" autocomplete="email"><span class="err">ایمیل معتبر وارد کنید.</span></div>
          </div>

          <h2 class="form-section-title"><span class="n">۲</span> اطلاعات محل کار</h2>
          <div class="form-grid">
            <div class="field"><label for="a-co">نام شرکت</label><input id="a-co" name="company" type="text" autocomplete="organization"></div>
            <div class="field"><label for="a-prov">استان <span class="req">*</span></label>
              <select id="a-prov" name="province" required>
                <option value="">— انتخاب کنید —</option>
@foreach (beryon_provinces() as $province)
                <option>{{ $province }}</option>
@endforeach
              </select>
              <span class="err">استان را انتخاب کنید.</span>
            </div>
            <div class="field"><label for="a-city">شهر <span class="req">*</span></label><input id="a-city" name="city" type="text" required autocomplete="address-level2"><span class="err">شهر الزامی است.</span></div>
            <div class="field"><label for="a-wtel">تلفن محل کار</label><input id="a-wtel" name="work_tel" type="tel" placeholder="0xx-xxxxxxxx"></div>
            <div class="field full"><label for="a-addr">آدرس محل کار <span class="req">*</span></label><input id="a-addr" name="address" type="text" required autocomplete="street-address"><span class="err">آدرس الزامی است.</span></div>
            <div class="hp-field" aria-hidden="true"><label for="a-hp">لطفاً این فیلد را خالی بگذارید</label><input id="a-hp" type="text" name="{{ beryon_opt('forms.honeypot_field', 'website_url') }}" tabindex="-1" autocomplete="off" value=""></div>

            <div class="field"><label for="a-warea">متراژ محل کار</label><input id="a-warea" name="work_area" type="number" min="0" placeholder="متر مربع"></div>
            <div class="field"><label for="a-own">نوع مالکیت دفتر فروش</label><select id="a-own" name="work_ownership"><option value="">— انتخاب کنید —</option>
@foreach (beryon_rows('agency.ownership_types') as $ownership)
                  <option>{{ $ownership }}</option>
@endforeach
                </select></div>
          </div>

          <h2 class="form-section-title"><span class="n">۳</span> اطلاعات انبار</h2>
          <div class="form-grid">
            <div class="field full"><label for="a-waddr">آدرس انبار</label><input id="a-waddr" name="warehouse_address" type="text"></div>
            <div class="field"><label for="a-wtel2">تلفن انبار</label><input id="a-wtel2" name="warehouse_tel" type="tel"></div>
            <div class="field"><label for="a-warea2">متراژ انبار</label><input id="a-warea2" name="warehouse_area" type="number" min="0" placeholder="متر مربع"></div>
            <div class="field"><label for="a-own2">نوع مالکیت انبار</label><select id="a-own2" name="warehouse_ownership"><option value="">— انتخاب کنید —</option>
@foreach (beryon_rows('agency.ownership_types') as $ownership)
                  <option>{{ $ownership }}</option>
@endforeach
                </select></div>
          </div>

          <h2 class="form-section-title"><span class="n">۴</span> توان فروش و توزیع</h2>
          <div class="form-grid">
            <div class="field full"><label>نوع فروش <span class="req">*</span></label>
              <div class="radio-pills">
@foreach (beryon_rows('agency.sales_types') as $index => $sales_type)
                <input type="checkbox" id="s{{ $index + 1 }}" name="sales_types[]" value="{{ $sales_type }}"><label for="s{{ $index + 1 }}">{{ $sales_type }}</label>
@endforeach
              </div>
              <span class="hint">می‌توانید چند مورد را انتخاب کنید.</span>
            </div>
            <div class="field"><label for="a-vis">تعداد ویزیتور</label><input id="a-vis" name="visitors" type="number" min="0" placeholder="نفر"></div>
            <div class="field"><label for="a-car">تعداد و نوع خودرو</label><input id="a-car" name="vehicles" type="text" placeholder="مثلاً ۲ یخچال‌دار"></div>
            <div class="field full"><label for="a-cov">شهرهای تحت پوشش <span class="req">*</span></label><input id="a-cov" name="coverage" type="text" required placeholder="مثلاً آمل، بابل، قائم‌شهر"><span class="err">شهرهای تحت پوشش را بنویسید.</span></div>
            <div class="field full check-line"><input id="a-agree" name="agree" value="1" type="checkbox" required><label for="a-agree">{{ beryon_opt('agency.agree_text') }}</label></div>
            <div class="full"><button class="btn btn-primary btn-block" type="submit">ارسال درخواست نمایندگی</button></div>
          </div>
        </form>
        <div class="form-success" role="status">
          <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m5 13 4 4L19 7"/></svg></span>
          <h3>{{ beryon_opt('agency.success_title') }}</h3>
          <p>{{ beryon_opt('agency.success_text') }}</p>
          <span class="track"></span>
        </div>
      </div>
    </div>
  </section>
@endsection
