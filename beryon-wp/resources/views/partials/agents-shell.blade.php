{{--
  بدنه مشترک برگه‌های پنل افزونه «سیستم نمایندگان فروش بریان» — قالب بریون
  ------------------------------------------------------------------
  $shell = agent (پنل نماینده) | admin (پنل مدیریت)

  خودِ پنل سربرگ و عنوان (h1) خودش را دارد؛ پس این‌جا فقط یک نوار باریک مسیر چاپ
  می‌شود و عنوان برگه فقط وقتی h1 است که پنل رندر نشده باشد (مهمان، بدون دسترسی،
  یا افزونه غیرفعال). مهمان پیش از رسیدن به این نما به صفحه ورود فرستاده می‌شود
  (App\Agents::guard_panels و خودِ افزونه).
--}}
@php
  $shell       = ($shell ?? 'agent') === 'admin' ? 'admin' : 'agent';
  $shell_title = trim((string) get_the_title());
  $shell_title = $shell_title !== '' ? $shell_title : ($shell === 'admin' ? 'پنل مدیریت نمایندگان' : 'پنل نمایندگان فروش');
  $shell_html  = \App\Agents::panel_html($shell);
  $shell_panel = strpos($shell_html, 'beryon-panel') !== false;
@endphp
  <section class="page-hero page-hero--slim">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => $shell_title],
      ]])
@if (! $shell_panel)
      <h1>{{ $shell_title }}</h1>
@endif
    </div>
  </section>

  <section class="section agents-shell agents-shell--{{ $shell }}">
    <div class="container">
@if ($shell_html !== '')
      {!! $shell_html !!}
@else
      <div class="agents-notice" role="status">
        <span class="ic">@php(beryon_svg('shield-check'))</span>
        <h2>این بخش در حال حاضر در دسترس نیست</h2>
        <p>{{ $shell === 'admin' ? 'پنل مدیریت نمایندگان' : 'پنل نمایندگان فروش' }} بریون موقتاً غیرفعال است. برای ثبت سفارش یا پیگیری با واحد فروش تماس بگیرید: <a href="tel:{{ beryon_opt('general.phone_sales') }}">{{ beryon_opt('general.phone_sales_label') }}</a></p>
@if (current_user_can('activate_plugins'))
        <p class="admin-hint" role="note">فقط مدیر سایت این را می‌بیند: شورت‌کد <code dir="ltr">[{{ $shell === 'admin' ? 'beryon_admin_panel' : 'beryon_agent_panel' }}]</code> ثبت نشده است. افزونه «سیستم نمایندگان فروش بریان» را فعال کنید؛ پنل‌های آن فقط با فعال بودن ووکامرس بار می‌شوند.</p>
@endif
      </div>
@endif
    </div>
  </section>
