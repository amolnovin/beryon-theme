#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ساخت نماهای Blade صفحات معرفی (درباره، مدیرعامل، چارت، گواهینامه‌ها)
از روی صفحات استاتیک _src/pages — با جایگزینی دقیق و کنترل‌شده.
"""
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from make_pages import build  # noqa: E402

VIEWS = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'beryon-wp/resources/views')

BREADCRUMB_RE = re.compile(r'[ ]*<nav class="breadcrumb" aria-label="مسیر صفحه">.*?</nav>', re.S)
CHEVRON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>'
CHECK_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m8.5 12.5 2.5 2.5 5-6"/></svg>'


def breadcrumb(items):
    rows = []

    def q(value):
        """رشته‌های ساده فارسی را داخل کوتیشن می‌گذارد؛ عبارت‌های PHP را دست نمی‌زند."""
        v = str(value)
        if v.startswith(('home_url', 'beryon_', 'esc_url', 'get_', '$', "'", '"')):
            return v
        return "'%s'" % v

    for label, url in items:
        if url is None:
            rows.append("        ['label' => %s]" % q(label))
        else:
            rows.append("        ['label' => %s, 'url' => %s]" % (q(label), q(url)))
    return ("      @include('components.breadcrumb', ['items' => [\n"
            + ',\n'.join(rows) + ",\n      ]])")


def swap_breadcrumb(text, items):
    return BREADCRUMB_RE.sub(lambda m: breadcrumb(items), text, count=1)


def swap_block(text, pattern, replacement, label):
    m = re.search(pattern, text, re.S)
    if not m:
        print('FAIL block: %s' % label)
        sys.exit(1)
    return text[:m.start()] + replacement + text[m.end():]


def save(name, text):
    path = os.path.join(VIEWS, name)
    open(path, 'w', encoding='utf-8').write(text)
    print('written %-28s %d B' % (name, len(text)))


# ====================================================================== ABOUT
spec = {
    'src': 'about.html',
    'dst': 'page-about.blade.php',
    'template_name': 'درباره بریون',
    'header': '''{{--
  صفحه درباره بریون — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق about.html قالب HTML نهایی است؛ متن‌ها از پنل
  «تنظیمات قالب بریون → درباره، مدیرعامل و چارت» (گروه about) می‌آیند.
--}}
''',
    'pairs': [
        ('      <h1>درباره بریان گوشت آمل | برند بریون</h1>',
         "      <h1>{{ beryon_tokens((string) beryon_opt('about.title')) }}</h1>"),
        ('      <p>از تأسیس در سال ۱۳۸۴ تا کسب استانداردهای بین‌المللی؛ روایت دو دهه تلاش برای تولید فرآورده‌های گوشتی سالم و باکیفیت.</p>',
         "      <p>{{ beryon_tokens((string) beryon_opt('about.lead')) }}</p>"),
        ('''          <img class="main" src="assets/img/factory.webp" alt="نمایی از کارخانه و خط تولید بریان گوشت آمل" loading="lazy">
          <div class="exp"><b>۱۳۸۴</b><small>سال تأسیس مجموعه</small></div>''',
         """          {!! beryon_image_tag((string) beryon_opt('about.image', 'factory.webp'), ['alt' => beryon_opt('about.image_alt'), 'class' => 'main']) !!}
          <div class="exp"><b>{{ beryon_opt('about.exp_value') }}</b><small>{{ beryon_opt('about.exp_label') }}</small></div>"""),
        ('''          <span class="ribbon-tag">تاریخچه شرکت</span>
          <h2>صنایع غذایی بریان گوشت آمل</h2>''',
         """          <span class="ribbon-tag">{{ beryon_opt('about.tag') }}</span>
          <h2>{{ beryon_opt('about.heading') }}</h2>"""),
        ('''        <span class="ribbon-tag">مسیر رشد</span>
        <h2>گاه‌شمار بریون</h2>
        <p>نقاط عطف دو دهه تولید فرآورده‌های گوشتی در آمل</p>''',
         """        <span class="ribbon-tag">{{ beryon_opt('about.timeline_tag') }}</span>
        <h2>{{ beryon_opt('about.timeline_title') }}</h2>
        <p>{{ beryon_opt('about.timeline_desc') }}</p>"""),
        ('''        <span class="ribbon-tag">آشنایی بیشتر</span>
        <h2>با بریون بیشتر آشنا شوید</h2>''',
         """        <span class="ribbon-tag">{{ beryon_opt('about.cards_tag') }}</span>
        <h2>{{ beryon_opt('about.cards_title') }}</h2>"""),
        ('        <a class="blog-card reveal" href="ceo.html">',
         "        <a class=\"blog-card reveal\" href=\"{{ esc_url(beryon_page_url('about-us/introduction-of-ceo', 'ceo')) }}\">"),
        ('        <a class="blog-card reveal d1" href="org-chart.html">',
         "        <a class=\"blog-card reveal d1\" href=\"{{ esc_url(beryon_page_url('about-us/organizational-chart', 'org')) }}\">"),
        ('        <a class="blog-card reveal d2" href="certificates.html">',
         "        <a class=\"blog-card reveal d2\" href=\"{{ esc_url(beryon_page_url('about-us/certificates', 'certificates')) }}\">"),
    ],
}

out = build(spec)
out = swap_breadcrumb(out, [('خانه', "home_url('/')"), ("beryon_opt('about.crumb', 'درباره ما')", None)])
out = swap_block(
    out,
    r'          <p>شرکت بریان گوشت در تیرماه ۱۳۸۴.*?</p>\n(?:          <p>.*?</p>\n)+',
    """@foreach (beryon_rows('about.paragraphs') as $paragraph)
          <p>{{ beryon_tokens((string) $paragraph) }}</p>
@endforeach
""",
    'about paragraphs',
)
out = swap_block(
    out,
    r'          <ul class="check-list">\n.*?\n          </ul>',
    """          <ul class="check-list">
@foreach (beryon_rows('about.checks') as $check)
            <li>%s{{ beryon_tokens((string) $check) }}</li>
@endforeach
          </ul>""" % CHECK_SVG,
    'about checks',
)
out = swap_block(
    out,
    r'      <div class="timeline">\n.*?\n      </div>',
    """      <div class="timeline">
@foreach (beryon_rows('about.timeline') as $event)
        <div class="tl-item reveal"><span class="card"><span class="year">{{ $event['year'] ?? '' }}</span><h3>{{ $event['title'] ?? '' }}</h3><p>{{ $event['text'] ?? '' }}</p></span></div>
@endforeach
      </div>""",
    'about timeline',
)
save('page-about.blade.php', out)


# ====================================================================== CEO
spec = {
    'src': 'ceo.html',
    'dst': 'page-ceo.blade.php',
    'template_name': 'معرفی مدیرعامل بریون',
    'header': '''{{--
  معرفی مدیرعامل — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق ceo.html است. تصویر ceo-photo.png بدون هیچ تغییری
  (کپی بایت‌به‌بایت) در public/assets/images/ قرار دارد و فقط با CSS
  نمایش داده می‌شود.
--}}
''',
    'pairs': [
        ('      <h1>معرفی مدیرعامل بریان گوشت آمل</h1>',
         "      <h1>{{ beryon_tokens((string) beryon_opt('ceo.title')) }}</h1>"),
        ('      <p>دکتر بهرام شهره؛ بنیان‌گذار و مدیرعامل شرکت بریان گوشت آمل، ترکیبی از دانش دامپزشکی، فیزیولوژی و مدیریت صنعتی.</p>',
         "      <p>{{ beryon_tokens((string) beryon_opt('ceo.lead')) }}</p>"),
        ('''          <img class="main" src="assets/img/ceo-photo.png" alt="پرتره دکتر بهرام شهره، مدیرعامل شرکت بریان گوشت آمل" loading="lazy" style="height:520px;object-fit:cover;object-position:top center">
          <div class="exp"><b>۱۳۸۲</b><small>آغاز رهبری بریان گوشت</small></div>''',
         """          {!! beryon_image_tag((string) beryon_opt('ceo.photo', 'ceo-photo.png'), ['alt' => beryon_opt('ceo.photo_alt'), 'class' => 'main', 'attr' => ['style' => 'height:520px;object-fit:cover;object-position:top center']]) !!}
          <div class="exp"><b>{{ beryon_opt('ceo.exp_value') }}</b><small>{{ beryon_opt('ceo.exp_label') }}</small></div>"""),
        ('''          <span class="ribbon-tag">سکان‌دار بریون</span>
          <h2>دکتر بهرام شهره</h2>''',
         """          <span class="ribbon-tag">{{ beryon_opt('ceo.tag') }}</span>
          <h2>{{ beryon_opt('ceo.name') }}</h2>"""),
        ('''        <span class="ribbon-tag">کارنامه علمی و اجرایی</span>
        <h2>دستاوردها و سوابق</h2>''',
         """        <span class="ribbon-tag">{{ beryon_opt('ceo.achievements_tag') }}</span>
        <h2>{{ beryon_opt('ceo.achievements_title') }}</h2>"""),
        ('''          <h2 style="font-size:clamp(1.1rem,2.4vw,1.5rem);line-height:2">«باور ما این است که کیفیت، تصادفی نیست؛ حاصل علم، تعهد و احترام به مصرف‌کننده است. هر بسته بریون که از کارخانه خارج می‌شود، امضای خانواده ما پای سفره شماست.»</h2>
          <p style="margin-top:10px">دکتر بهرام شهره — رئیس هیئت مدیره و مدیرعامل بریان گوشت آمل</p>''',
         """          <h2 style="font-size:clamp(1.1rem,2.4vw,1.5rem);line-height:2">{{ beryon_opt('ceo.quote') }}</h2>
          <p style="margin-top:10px">{{ beryon_opt('ceo.quote_by') }}</p>"""),
    ],
}

out = build(spec)
out = swap_breadcrumb(out, [
    ('خانه', "home_url('/')"),
    ("beryon_opt('about.crumb', 'درباره ما')", "beryon_page_url('about-us', 'about')"),
    ("beryon_opt('ceo.crumb', 'معرفی مدیرعامل')", None),
])
out = swap_block(
    out,
    r'          <p>متولد سال ۱۳۴۷ در آمل.*?</p>\n(?:          <p>.*?</p>\n)+',
    """@foreach (beryon_rows('ceo.paragraphs') as $paragraph)
          <p>{{ beryon_tokens((string) $paragraph) }}</p>
@endforeach
""",
    'ceo paragraphs',
)
out = swap_block(
    out,
    r'          <ul class="check-list">\n.*?\n          </ul>',
    """          <ul class="check-list">
@foreach (beryon_rows('ceo.checks') as $check)
            <li>%s{{ beryon_tokens((string) $check) }}</li>
@endforeach
          </ul>""" % CHECK_SVG,
    'ceo checks',
)
out = swap_block(
    out,
    r'      <div class="grid features">\n.*?\n      </div>',
    """      <div class="grid features">
@foreach (beryon_rows('ceo.achievements') as $index => $item)
        <article class="feature reveal{{ $index % 3 === 1 ? ' d1' : ($index % 3 === 2 ? ' d2' : '') }}"><span class="ic">@php(beryon_svg($item['icon'] ?? 'book'))</span><h3>{{ $item['title'] ?? '' }}</h3><p>{{ $item['text'] ?? '' }}</p></article>
@endforeach
      </div>""",
    'ceo features',
)
save('page-ceo.blade.php', out)


# ================================================================== ORG-CHART
spec = {
    'src': 'org-chart.html',
    'dst': 'page-org-chart.blade.php',
    'template_name': 'چارت سازمانی بریون',
    'header': '''{{--
  چارت سازمانی — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق org-chart.html؛ درخت سازمانی از ردیف‌های تنظیم‌پذیر
  (org.units) ساخته می‌شود تا مدیر بتواند واحدها و زیرمجموعه‌ها را ویرایش کند.
--}}
''',
    'pairs': [
        ('      <h1>چارت سازمانی بریان گوشت آمل</h1>',
         "      <h1>{{ beryon_tokens((string) beryon_opt('org.title')) }}</h1>"),
        ('      <p>ساختار سازمانی چابک و تخصصی بریون؛ از مدیریت تولید و کنترل کیفیت تا فروش و بازرگانی، با تفکیک شفاف مسئولیت‌ها.</p>',
         "      <p>{{ beryon_tokens((string) beryon_opt('org.lead')) }}</p>"),
        ('        <span class="ribbon-tag">شرح وظایف</span>\n        <h2>واحدهای کلیدی مجموعه</h2>',
         "        <span class=\"ribbon-tag\">{{ beryon_opt('org.duties_tag') }}</span>\n        <h2>{{ beryon_opt('org.duties_title') }}</h2>"),
    ],
}

out = build(spec)
out = swap_breadcrumb(out, [
    ('خانه', "home_url('/')"),
    ("beryon_opt('about.crumb', 'درباره ما')", "beryon_page_url('about-us', 'about')"),
    ("beryon_opt('org.crumb', 'چارت سازمانی')", None),
])
out = swap_block(
    out,
    r'      <div class="org-tree reveal" role="img" aria-label="چارت سازمانی شرکت بریان گوشت آمل">\n.*?\n      </div>',
    """      <div class="org-tree reveal" role="img" aria-label="چارت سازمانی {{ beryon_amp(beryon_opt('general.company_name')) }}">
        <ul>
          <li>
            <span class="org-node root">{{ beryon_opt('org.root') }}<small>{{ beryon_opt('org.root_name') }}</small></span>
            <ul>
@foreach (beryon_rows('org.units') as $unit)
              <li><span class="org-node mgr">{{ beryon_amp($unit['title'] ?? '') }}</span>
@if (! empty($unit['children']))
                <ul>
@foreach ((array) $unit['children'] as $child)
                  <li><span class="org-node">{{ beryon_amp($child['title'] ?? '') }}{{ ! empty($child['sub']) ? '<small>' . esc_html($child['sub']) . '</small>' : '' }}</span></li>
@endforeach
                </ul>
@endif
              </li>
@endforeach
            </ul>
          </li>
        </ul>
      </div>""",
    'org tree',
)
out = swap_block(
    out,
    r'      <div class="grid features">\n.*?\n      </div>',
    """      <div class="grid features">
@foreach (beryon_rows('org.duties') as $index => $item)
        <article class="feature reveal{{ $index % 3 === 1 ? ' d1' : ($index % 3 === 2 ? ' d2' : '') }}"><span class="ic">@php(beryon_svg($item['icon'] ?? 'gear'))</span><h3>{{ beryon_amp($item['title'] ?? '') }}</h3><p>{{ $item['text'] ?? '' }}</p></article>
@endforeach
      </div>""",
    'org duties',
)
save('page-org-chart.blade.php', out)


# =============================================================== CERTIFICATES
spec = {
    'src': 'certificates.html',
    'dst': 'page-certificates.blade.php',
    'template_name': 'گواهینامه‌ها و افتخارات بریون',
    'header': '''{{--
  گواهینامه‌ها و افتخارات — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق certificates.html؛ کارت‌ها از ردیف‌های تنظیم‌پذیر
  certificates.intl و certificates.national ساخته می‌شوند و کلاس‌های
  تأخیر انیمیشن (d1/d2/d3) مطابق قالب HTML با پیمانه ۴ تکرار می‌شوند.
--}}
''',
    'pairs': [
        ('      <h1>گواهینامه‌ها و افتخارات بریون</h1>',
         "      <h1>{{ beryon_tokens((string) beryon_opt('certificates.title')) }}</h1>"),
        ('      <p>استانداردهای مدیریت کیفیت، ایمنی غذا و نشان‌های ملی و بین‌المللی که تضمین‌کننده کیفیت هر بسته بریون است.</p>',
         "      <p>{{ beryon_tokens((string) beryon_opt('certificates.lead')) }}</p>"),
        ('''        <span class="ribbon-tag">استانداردهای سیستمی</span>
        <h2>گواهینامه‌های بین‌المللی</h2>
        <p>ممیزی‌شده توسط شرکت‌های معتبر نظارت و ارزیابی داخلی و بین‌المللی</p>''',
         """        <span class="ribbon-tag">{{ beryon_opt('certificates.intl_tag') }}</span>
        <h2>{{ beryon_opt('certificates.intl_title') }}</h2>
        <p>{{ beryon_opt('certificates.intl_desc') }}</p>"""),
        ('''        <span class="ribbon-tag">تندیس‌ها و لوح‌های سپاس</span>
        <h2>افتخارات ملی بریون</h2>''',
         """        <span class="ribbon-tag">{{ beryon_opt('certificates.national_tag') }}</span>
        <h2>{{ beryon_opt('certificates.national_title') }}</h2>"""),
        ('          <h2 style="font-size:1.35rem">کیفیت بریون، قابل استعلام و قابل اعتماد</h2>',
         "          <h2 style=\"font-size:1.35rem\">{{ beryon_opt('certificates.cta_title') }}</h2>"),
        ('          <p>تصاویر اصل گواهینامه‌ها و تندیس‌ها در دفتر مرکزی شرکت قابل رویت است؛ برای استعلام اعتبار هر نشان با واحد تضمین کیفیت بریون تماس بگیرید.</p>',
         "          <p>{{ beryon_opt('certificates.cta_text') }}</p>"),
        ('''          <div class="actions"><a class="btn btn-light" href="contact.html">تماس با واحد تضمین کیفیت</a><a class="btn btn-outline-light" href="about.html">درباره بریون</a></div>''',
         """          <div class="actions"><a class="btn btn-light" href="{{ esc_url(beryon_page_url('contact-us', 'contact')) }}">{{ beryon_opt('certificates.cta_btn_1') }}</a><a class="btn btn-outline-light" href="{{ esc_url(beryon_page_url('about-us', 'about')) }}">{{ beryon_opt('certificates.cta_btn_2') }}</a></div>"""),
    ],
}

out = build(spec)
out = swap_breadcrumb(out, [
    ('خانه', "home_url('/')"),
    ("beryon_opt('about.crumb', 'درباره ما')", "beryon_page_url('about-us', 'about')"),
    ("beryon_opt('certificates.crumb', 'گواهینامه‌ها')", None),
])

grids = list(re.finditer(r'      <div class="grid certs-grid">\n.*?\n      </div>', out, re.S))
assert len(grids) == 2, len(grids)

# کارت‌های بین‌المللی (آیکون medal، بدون استایل)
intl = """      <div class="grid certs-grid">
@foreach (beryon_rows('certificates.intl') as $index => $cert)
        <article class="cert-card reveal{{ $index % 4 === 1 ? ' d1' : ($index % 4 === 2 ? ' d2' : ($index % 4 === 3 ? ' d3' : '')) }}"><span class="ic">@php(beryon_svg('medal'))</span><h3>{{ beryon_amp($cert['title'] ?? '') }}</h3><small>{{ $cert['sub'] ?? '' }}</small></article>
@endforeach
      </div>"""
national = """      <div class="grid certs-grid">
@foreach (beryon_rows('certificates.national') as $index => $cert)
        <article class="cert-card reveal{{ $index % 4 === 1 ? ' d1' : ($index % 4 === 2 ? ' d2' : ($index % 4 === 3 ? ' d3' : '')) }}"><span class="ic" style="background:linear-gradient(135deg,var(--brand-soft),#ffd9d5);color:var(--brand)">@php(beryon_svg('trophy'))</span><h3>{{ $cert['title'] ?? '' }}</h3><small>{{ $cert['sub'] ?? '' }}</small></article>
@endforeach
      </div>"""

out = out[:grids[0].start()] + intl + out[grids[0].end():]
grids = list(re.finditer(r'      <div class="grid certs-grid">\n.*?\n      </div>', out, re.S))
out = out[:grids[1].start()] + national + out[grids[1].end():]
save('page-certificates.blade.php', out)
