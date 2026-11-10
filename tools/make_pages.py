#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
تبدیل صفحات استاتیک _src/pages/*.html → نماهای Blade قالب وردپرس بریون

قانون: هر جایگزینی باید دقیقاً یک‌بار در منبع پیدا شود؛ در غیر این‌صورت اجرا
با خطا متوقف می‌شود تا هیچ بخش ثابتی ناخواسته در Blade باقی نماند.

هر صفحه = یک رکورد در SPECS با:
  src, dst, template_name (اختیاری), header (توضیح Blade), pairs [(old, new)]
  regex  [(pattern, new, flags)] برای بخش‌های چندخطی/تکرارشونده
"""
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC_DIR = os.path.join(ROOT, '_src/pages')
DST_DIR = os.path.join(ROOT, 'beryon-wp/resources/views')

BC = "@include('components.breadcrumb', ['items' => %s])"


def crumbs(*items):
    """ساخت فراخوانی breadcrumb."""
    rows = []
    for label, url in items:
        if url is None:
            rows.append("['label' => %s]" % label)
        else:
            rows.append("['label' => %s, 'url' => %s]" % (label, url))
    return BC % ('[\n        ' + ',\n        '.join(rows) + ',\n      ]')


NAV_BREADCRUMB = re.compile(
    r'[ \t]*<nav class="breadcrumb" aria-label="مسیر صفحه">.*?</nav>', re.S)


def replace_breadcrumb(body, php_items):
    """جایگزینی nav breadcrumb استاتیک با کامپوننت Blade."""
    new = '@include(\'components.breadcrumb\', [\'items\' => ' + php_items + '])'
    return NAV_BREADCRUMB.sub(lambda m: m.group(0)[:len(m.group(0)) - len(m.group(0).lstrip())] + new, body, count=1)


def build(spec):
    src = os.path.join(SRC_DIR, spec['src'])
    html = open(src, encoding='utf-8').read()
    assert html.startswith('<main id="main">'), spec['src']
    body = html[len('<main id="main">'):]
    body = body.rstrip()
    assert body.endswith('</main>')
    body = body[: -len('</main>')].rstrip('\n')

    for pattern, new, count in spec.get('regex', []):
        found = len(re.findall(pattern, body, re.S))
        if found != count:
            print('FAIL regex %s → %d matches (expected %d) in %s' % (pattern[:60], found, count, spec['src']))
            sys.exit(1)
        body = re.sub(pattern, new.replace('\\', '\\\\'), body, count=count, flags=re.S)

    for old, new in spec.get('pairs', []):
        found = body.count(old)
        if found != 1:
            print('FAIL pair (%d matches) in %s:\n%s' % (found, spec['src'], old[:200]))
            sys.exit(1)
        body = body.replace(old, new, 1)

    header = spec.get('header', '')
    tmpl = ''
    if spec.get('template_name'):
        tmpl = '{{--\n  Template Name: %s\n--}}\n' % spec['template_name']

    out = tmpl + header + "@extends('layouts.app')\n\n@section('content')\n" + spec.get('php', '') + body.strip('\n') + '\n@endsection\n'

    dst = os.path.join(DST_DIR, spec['dst'])
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    open(dst, 'w', encoding='utf-8').write(out)
    print('OK %-34s → %s (%d B)' % (spec['src'], spec['dst'], len(out)))
    return out
