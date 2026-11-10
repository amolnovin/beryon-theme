{{--
  Template Name: چارت سازمانی بریون
--}}
{{--
  چارت سازمانی — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق org-chart.html؛ درخت سازمانی از ردیف‌های تنظیم‌پذیر
  (org.units) ساخته می‌شود تا مدیر بتواند واحدها و زیرمجموعه‌ها را ویرایش کند.
--}}
@extends('layouts.app')

@section('content')
  <section class="page-hero">
    <div class="container">
      @include('components.breadcrumb', ['items' => [
        ['label' => 'خانه', 'url' => home_url('/')],
        ['label' => beryon_opt('about.crumb', 'درباره ما'), 'url' => beryon_page_url('about-us', 'about')],
        ['label' => beryon_opt('org.crumb', 'چارت سازمانی')],
      ]])
      <h1>{{ beryon_tokens((string) beryon_opt('org.title')) }}</h1>
      <p>{{ beryon_tokens((string) beryon_opt('org.lead')) }}</p>
    </div>
    <span class="ribbon-deco" aria-hidden="true"></span>
  </section>

  <section class="section" style="padding-top:56px">
    <div class="container">
      <div class="org-tree reveal" role="img" aria-label="چارت سازمانی {!! beryon_amp(beryon_opt('general.company_name')) !!}">
        <ul>
          <li>
            <span class="org-node root">{{ beryon_opt('org.root') }}<small>{{ beryon_opt('org.root_name') }}</small></span>
            <ul>
@foreach (beryon_rows('org.units') as $unit)
              <li><span class="org-node mgr">{!! beryon_amp($unit['title'] ?? '') !!}</span>
@if (! empty($unit['children']))
                <ul>
@foreach ((array) $unit['children'] as $child)
                  <li><span class="org-node">{!! beryon_amp($child['title'] ?? '') !!}{!! ! empty($child['sub']) ? '<small>' . esc_html($child['sub']) . '</small>' : '' !!}</span></li>
@endforeach
                </ul>
@endif
              </li>
@endforeach
            </ul>
          </li>
        </ul>
      </div>

      <div class="sec-head reveal" style="margin-top:70px">
        <span class="ribbon-tag">{{ beryon_opt('org.duties_tag') }}</span>
        <h2>{{ beryon_opt('org.duties_title') }}</h2>
        <span class="underline"></span>
      </div>
      <div class="grid features">
@foreach (beryon_rows('org.duties') as $index => $item)
        <article class="feature reveal{{ $index % 3 === 1 ? ' d1' : ($index % 3 === 2 ? ' d2' : '') }}"><span class="ic">@php(beryon_svg($item['icon'] ?? 'gear'))</span><h3>{!! beryon_amp($item['title'] ?? '') !!}</h3><p>{{ $item['text'] ?? '' }}</p></article>
@endforeach
      </div>
    </div>
  </section>
@endsection
