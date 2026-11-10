{{--
  home.blade.php — صفحه وبلاگ (صفحه نوشته‌ها)
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق blog.html؛ بدنه فهرست از partial مشترک
  partials/blog-list می‌آید تا با بایگانی دسته/برچسب یکسان بماند.
--}}
@extends('layouts.app')

@section('content')
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => beryon_opt('blog.crumb', 'وبلاگ')],
      ]])
      <h1>{{ beryon_opt('blog.title') }}</h1>
      <p>{{ beryon_opt('blog.lead') }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

@include('partials.blog-list', ['opt_group' => 'blog'])
@endsection
