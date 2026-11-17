{{--
  Template Name: چارت سازمانی بریون
--}}
{{--
  چارت سازمانی — قالب وردپرس بریون
  ------------------------------------------------------------------
  درخت سازمانی از ردیف‌های تنظیم‌پذیر (org.units) ساخته می‌شود تا مدیر بتواند
  واحدها و زیرمجموعه‌ها را ویرایش کند. از ۱.۰.۶ چارت سه سطح زیرِ ریشه دارد و با
  خط‌های رابط نازک رسم می‌شود (کلاس org-chart در wp.css)؛ کلاس‌های org-tree و
  org-node قالب HTML مرجع حفظ شده‌اند. چارت همیشه راست‌به‌چپ چیده می‌شود.
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
@php
  // سطح سوم چارت: نام زیرمجموعه‌های هر زیرواحد در فیلد «sub» با «،» از هم جدا می‌شوند
  // و هر کدام خانه جداگانه‌ای زیر همان زیرواحد می‌گیرند.
  $org_split = static function ($text): array {
      $parts = preg_split('/\s*[،,؛;]\s*/u', trim((string) $text)) ?: [];

      return array_values(array_filter(array_map('trim', $parts), 'strlen'));
  };
  $org_root_name = trim((string) beryon_opt_raw('org.root_name', ''));
@endphp
      <div class="org-tree org-chart reveal" dir="rtl" role="img" aria-label="چارت سازمانی {!! beryon_amp(beryon_opt('general.company_name')) !!}">
        <ul>
          <li>
            <span class="org-node root">{{ beryon_opt('org.root') }}{!! $org_root_name !== '' ? '<small>' . esc_html($org_root_name) . '</small>' : '' !!}</span>
            <ul>
@foreach (beryon_rows('org.units') as $unit)
              <li><span class="org-node mgr">{!! beryon_amp($unit['title'] ?? '') !!}</span>
@if (! empty($unit['children']))
                <ul>
@foreach ((array) $unit['children'] as $child)
@php($org_subs = $org_split($child['sub'] ?? ''))
                  <li><span class="org-node">{!! beryon_amp($child['title'] ?? '') !!}</span>
@if ($org_subs !== [])
                    <ul>
@foreach ($org_subs as $org_sub)
                      <li><span class="org-node">{!! beryon_amp($org_sub) !!}</span></li>
@endforeach
                    </ul>
@endif
                  </li>
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
