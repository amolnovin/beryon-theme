{{--
  بدنه محتوای تکی عمومی — قالب وردپرس بریون
  ------------------------------------------------------------------
  بین page.blade.php (صفحه‌های بدون «قالب صفحه» اختصاصی مثل قوانین و حریم
  خصوصی) و singular.blade.php (پیوست‌ها و هر نوع محتوای تکی دیگر) مشترک است.

  فقط از کلاس‌های موجود قالب HTML نهایی استفاده می‌کند (page-hero ، breadcrumb ،
  post-card ، post-body) تا CSS جدیدی لازم نباشد. محتوا از ویرایشگر وردپرس
  می‌آید و از فیلتر the_content می‌گذرد؛ نوشته رمزدار به‌جای متن، فرم رمز را
  نشان می‌دهد (beryon_post_content).
--}}
@php
  $entry_id    = (int) get_queried_object_id();
  $entry_title = (string) get_the_title($entry_id);
  $entry_type  = get_post_type_object((string) get_post_type($entry_id));
  $entry_lead  = post_password_required($entry_id) ? '' : trim((string) get_post_field('post_excerpt', $entry_id));
  $crumbs      = [['label' => 'خانه', 'url' => home_url('/')]];

  // بایگانی نوع محتوا (برای انواع سفارشی دارای بایگانی)
  if ($entry_type && ! empty($entry_type->has_archive)) {
      $archive_link = (string) get_post_type_archive_link($entry_type->name);

      if ($archive_link !== '') {
          $crumbs[] = ['label' => (string) $entry_type->labels->name, 'url' => $archive_link];
      }
  }

  // والدها (صفحه‌های تودرتو)
  foreach (array_reverse((array) get_post_ancestors($entry_id)) as $ancestor_id) {
      $crumbs[] = [
          'label' => beryon_excerpt_text((string) get_the_title((int) $ancestor_id), 40),
          'url'   => (string) get_permalink((int) $ancestor_id),
      ];
  }

  $crumbs[] = ['label' => beryon_excerpt_text($entry_title, 40)];
@endphp
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => $crumbs])
      <h1>{{ $entry_title }}</h1>
@if ($entry_lead !== '')
      <p>{{ beryon_excerpt_text($entry_lead, 160) }}</p>
@endif
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:56px">
    <div class="container">
      <article class="post-card" style="max-width:920px;margin-inline:auto">
        <div class="post-body">
          {!! beryon_post_content($entry_id) !!}
        </div>
      </article>
    </div>
  </section>
