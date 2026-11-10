{{--
  بایگانی وبلاگ (دسته/برچسب/تاریخ) — قالب وردپرس بریون
  ------------------------------------------------------------------
  از همان بدنه مشترک فهرست وبلاگ استفاده می‌کند (partials/blog-list) تا
  مارک‌آپ کارت‌ها با blog.html یکسان بماند؛ فقط عنوان هیرو به نام
  دسته/برچسب/بایگانی تغییر می‌کند.
--}}
@extends('layouts.app')

@section('content')
@php
  $queried   = get_queried_object();
  $is_term   = $queried instanceof \WP_Term;
  $term_name = $is_term ? (string) $queried->name : '';
  $archive_title = $term_name !== ''
      ? $term_name
      : (string) get_the_archive_title();
  $archive_title = (string) preg_replace('/^[^:]{1,40}:\s*/u', '', $archive_title);

  if (trim($archive_title) === '') {
      $archive_title = (string) beryon_opt('blog_archive.title', 'مجله بریان گوشت');
  }

  $archive_lead = $is_term && trim((string) term_description($queried)) !== ''
      ? wp_strip_all_tags((string) term_description($queried))
      : (string) beryon_opt('blog_archive.lead');
@endphp
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => beryon_opt('blog.crumb', 'وبلاگ'), 'url' => beryon_blog_url()],
        ['label' => $archive_title],
      ]])
      <h1>{{ $archive_title }}</h1>
      <p>{{ beryon_excerpt_text($archive_lead, 160) }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

@include('partials.blog-list', ['opt_group' => 'blog_archive'])
@endsection
