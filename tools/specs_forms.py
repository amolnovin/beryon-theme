#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ساخت نماهای Blade صفحات فرم‌دار (تماس، نظرات و شکایات، اعطای نمایندگی)."""
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from make_pages import build  # noqa: E402
from specs_about_group import swap_breadcrumb, swap_block, save  # noqa: E402

def sub_once(text, old, new):
    """جایگزینی دقیقاً یک‌باره با کنترل خطا."""
    if text.count(old) != 1:
        print('FAIL sub_once (%d): %s' % (text.count(old), old[:90]))
        sys.exit(1)
    return text.replace(old, new, 1)


HONEYPOT = '''              <div class="hp-field" aria-hidden="true"><label for="HPID">لطفاً این فیلد را خالی بگذارید</label><input id="HPID" type="text" name="{{ beryon_opt('forms.honeypot_field', 'website_url') }}" tabindex="-1" autocomplete="off" value=""></div>
'''


def honeypot(hp_id):
    return HONEYPOT.replace('HPID', hp_id)


def info_cards(option, indent='        '):
    return (indent + '<div class="info-cards reveal">\n'
            + "@foreach (beryon_rows('" + option + "') as $card)\n"
            + indent + '  <div class="info-card">\n'
            + indent + '    <span class="ic">@php(beryon_svg($card[\'icon\'] ?? \'chat\'))</span>\n'
            + indent + '    <div><b>{{ $card[\'title\'] ?? \'\' }}</b><p>{!! beryon_tokens(beryon_amp($card[\'text\'] ?? \'\')) !!}</p></div>\n'
            + indent + '  </div>\n'
            + '@endforeach\n'
            + indent + '</div>')


def contact():
    spec = {
        'src': 'contact.html',
        'dst': 'page-contact-us.blade.php',
        'template_name': 'تماس با بریون',
        'header': '''{{--
  تماس با ما — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق contact.html است؛ فقط «name» فیلدها برای ارسال به
  REST (beryon/v1/form) و یک فیلد هانی‌پات ضدربات اضافه شده که با CSS
  پنهان می‌شود و هیچ اثری روی ظاهر صفحه ندارد.

  هوک‌های JS: form[data-form] ، .form-success.show ، .track
--}}
''',
        'pairs': [
            ('      <h1>تماس با بریون</h1>',
             "      <h1>{{ beryon_opt('contact.title') }}</h1>"),
            ('      <p>طعم شیرین ارتباط با شما برای ما از اهمیت بالایی برخوردار است؛ آماده پاسخگویی به پیشنهادات، انتقادات و نظرات شما از همه راه‌های ارتباطی هستیم.</p>',
             "      <p>{{ beryon_opt('contact.lead') }}</p>"),
            ('          <form data-form novalidate>',
             '          <form data-form="contact" method="post" action="{{ esc_url(beryon_form_url()) }}" novalidate>'),
            ('            <h2 style="font-size:1.2rem;margin-bottom:20px">فرم تماس سریع</h2>',
             "            <h2 style=\"font-size:1.2rem;margin-bottom:20px\">{{ beryon_opt('contact.form_title') }}</h2>"),
            ('''              <div class="field"><label for="c-name">نام <span class="req">*</span></label><input id="c-name" type="text" required placeholder="نام و نام خانوادگی"><span class="err">لطفاً نام خود را وارد کنید.</span></div>''',
             '''              <div class="field"><label for="c-name">نام <span class="req">*</span></label><input id="c-name" name="name" type="text" required placeholder="نام و نام خانوادگی" autocomplete="name"><span class="err">لطفاً نام خود را وارد کنید.</span></div>'''),
            ('''              <div class="field"><label for="c-tel">شماره همراه <span class="req">*</span></label><input id="c-tel" type="tel" inputmode="tel" required placeholder="09xxxxxxxxx"><span class="err">شماره همراه معتبر وارد کنید.</span></div>''',
             '''              <div class="field"><label for="c-tel">شماره همراه <span class="req">*</span></label><input id="c-tel" name="tel" type="tel" inputmode="tel" required placeholder="09xxxxxxxxx" autocomplete="tel"><span class="err">شماره همراه معتبر وارد کنید.</span></div>'''),
            ('''                <select id="c-dep"><option>واحد فروش</option><option>مدیریت بازرگانی</option><option>واحد رسیدگی به شکایات</option><option>روابط عمومی</option></select>''',
             '''                <select id="c-dep" name="department">
@foreach (beryon_rows('contact.departments') as $department)
                  <option>{{ $department }}</option>
@endforeach
                </select>'''),
            ('''              <div class="field full"><label for="c-msg">پیغام <span class="req">*</span></label><textarea id="c-msg" required placeholder="متن پیام شما…"></textarea><span class="err">لطفاً متن پیام را بنویسید.</span></div>''',
             '''              <div class="field full"><label for="c-msg">پیغام <span class="req">*</span></label><textarea id="c-msg" name="message" required placeholder="متن پیام شما…"></textarea><span class="err">لطفاً متن پیام را بنویسید.</span></div>
''' + honeypot('c-hp')),
            ('            <h3>پیام شما ارسال شد</h3>',
             "            <h3>{{ beryon_opt('contact.success_title') }}</h3>"),
            ('            <p>کارشناسان ما در اولین فرصت کاری با شما تماس خواهند گرفت. کد پیگیری:</p>',
             "            <p>{{ beryon_opt('contact.success_text') }}</p>"),
            ('            <span class="track">BRY-۱۴۰-۰۰۰۰</span>',
             '            <span class="track"></span>'),
        ],
    }

    out = build(spec)
    out = swap_breadcrumb(out, [('خانه', "home_url('/')"), ("beryon_opt('contact.crumb', 'تماس با ما')", None)])

    cards = re.search(r'        <div class="info-cards reveal">\n.*?\n        </div>', out, re.S)
    assert cards, 'info-cards not found'
    replacement = """        <div class="info-cards reveal">
@foreach (beryon_rows('contact.cards') as $card)
@php
  $card_href = beryon_card_href((string) ($card['href'] ?? ''));
@endphp
@if ($card_href !== '')
          <a class="info-card" href="{{ esc_url($card_href) }}">
            <span class="ic">@php(beryon_svg($card['icon'] ?? 'phone'))</span>
            <div><b>{{ $card['title'] ?? '' }}</b><p>{{ beryon_tokens((string) ($card['text'] ?? '')) }}</p></div>
          </a>
@else
          <div class="info-card">
            <span class="ic">@php(beryon_svg($card['icon'] ?? 'pin'))</span>
            <div><b>{{ $card['title'] ?? '' }}</b><p>{{ beryon_tokens((string) ($card['text'] ?? '')) }}</p></div>
          </div>
@endif
@endforeach
          <a class="map-card" href="{{ esc_url(beryon_opt('general.map_url')) }}" target="_blank" rel="noopener">
            <div>
              <span class="pin">@php(beryon_svg('pin'))</span>
              <p>{{ beryon_opt('contact.map_text') }}</p>
            </div>
          </a>
        </div>"""
    out = out[:cards.start()] + replacement + out[cards.end():]
    save('page-contact-us.blade.php', out)


def feedback():
    spec = {
        'src': 'feedback.html',
        'dst': 'page-feedback.blade.php',
        'template_name': 'نظرات و شکایات بریون',
        'header': '''{{--
  نظرات و شکایات — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق feedback.html؛ فیلدها از پیش name دارند و فقط
  action فرم (REST) و هانی‌پات اضافه شده است. پیام‌ها در جدول اختصاصی
  قالب ذخیره و در «بریون ← پیام‌های دریافتی» مدیریت می‌شوند.

  هوک‌های JS: form[data-form] ، .acc-item ، .form-success.show ، .track
--}}
''',
        'pairs': [
            ('      <h1>نظرات و شکایات شما، قطب‌نمای ماست</h1>',
             "      <h1>{{ beryon_opt('feedback.title') }}</h1>"),
            ('      <p>تقدیر، پیشنهاد، انتقاد یا شکایت؛ هر پیام شما در بریون ثبت، پیگیری و پاسخ داده می‌شود. صدای شما کیفیت فردای ما را می‌سازد.</p>',
             "      <p>{{ beryon_opt('feedback.lead') }}</p>"),
            ('          <form data-form novalidate>',
             '          <form data-form="feedback" method="post" action="{{ esc_url(beryon_form_url()) }}" novalidate>'),
            ('            <h2 style="font-size:1.2rem;margin-bottom:20px">فرم ثبت نظر، پیشنهاد یا شکایت</h2>',
             "            <h2 style=\"font-size:1.2rem;margin-bottom:20px\">{{ beryon_opt('feedback.form_title') }}</h2>"),
            ('''                <select id="f-subj" name="subject"><option>سفارش‌ها</option><option>محصولات</option><option>نماینده فروش</option><option>پنل کاربری و سایت</option><option>سایر موارد</option></select>''',
             '''                <select id="f-subj" name="subject">
@foreach (beryon_rows('feedback.subjects') as $subject)
                  <option>{{ $subject }}</option>
@endforeach
                </select>'''),
            ('''              <div class="field full"><label for="f-msg">متن پیام <span class="req">*</span></label><textarea id="f-msg" name="message" required placeholder="شرح پیام خود را بنویسید؛ در صورت مرتبط بودن با سفارش، شماره سفارش را هم ذکر کنید تا سریع‌تر بررسی شود."></textarea><span class="err">لطفاً متن پیام را بنویسید.</span></div>''',
             '''              <div class="field full"><label for="f-msg">متن پیام <span class="req">*</span></label><textarea id="f-msg" name="message" required placeholder="شرح پیام خود را بنویسید؛ در صورت مرتبط بودن با سفارش، شماره سفارش را هم ذکر کنید تا سریع‌تر بررسی شود."></textarea><span class="err">لطفاً متن پیام را بنویسید.</span></div>
''' + honeypot('f-hp')),
            ('            <h3>پیام شما با موفقیت ثبت شد</h3>',
             "            <h3>{{ beryon_opt('feedback.success_title') }}</h3>"),
            ('            <p>از این‌که دیدگاه خود را با ما در میان گذاشتید سپاسگزاریم. کارشناسان ما پیام شما را بررسی کرده و در صورت نیاز با شما تماس خواهند گرفت. لطفاً کد پیگیری زیر را نزد خود نگه دارید.</p>',
             "            <p>{{ beryon_opt('feedback.success_text') }}</p>"),
            ('            <span class="track">BRY-۱۴۰۴-۰۰۰۰</span>',
             '            <span class="track"></span>'),
            ('        <span class="ribbon-tag">پرسش‌های پرتکرار</span>\n        <h2>پیش از ثبت پیام، شاید پاسخ شما اینجاست</h2>',
             "        <span class=\"ribbon-tag\">{{ beryon_opt('feedback.faq_tag') }}</span>\n        <h2>{{ beryon_opt('feedback.faq_title') }}</h2>"),
            ('''            <div><b>تماس مستقیم</b><p>واحد رسیدگی به مشتریان: <a href="tel:+981144448072">۰۱۱-۴۴۴۴۸۰۷۲</a><br>ایمیل: <a href="mailto:info@beryon.com">info@beryon.com</a></p></div>''',
             '''            <div><b>تماس مستقیم</b><p>واحد رسیدگی به مشتریان: <a href="tel:{{ beryon_opt('general.phone_admin') }}">{{ beryon_opt('general.phone_admin_label') }}</a><br>ایمیل: <a href="mailto:{{ beryon_opt('general.email') }}">{{ beryon_opt('general.email') }}</a></p></div>'''),
        ],
    }

    out = build(spec)
    out = swap_breadcrumb(out, [('خانه', "home_url('/')"), ("beryon_opt('feedback.crumb', 'نظرات و شکایات')", None)])

    cards = re.search(r'        <div class="info-cards reveal">\n.*?\n        </div>', out, re.S)
    assert cards
    out = out[:cards.start()] + info_cards('feedback.cards') + out[cards.end():]

    radios = re.search(r'                <div class="radio-pills">\n.*?\n                </div>', out, re.S)
    assert radios
    out = out[:radios.start()] + """                <div class="radio-pills">
@foreach (beryon_rows('feedback.types') as $index => $type)
                  <input type="radio" id="t{{ $index + 1 }}" name="ptype" value="{{ $type['value'] ?? '' }}"{{ $index === 0 ? ' checked' : '' }}><label for="t{{ $index + 1 }}">{{ $type['label'] ?? '' }}</label>
@endforeach
                </div>""" + out[radios.end():]

    acc = re.search(r'      <div class="accordion reveal">\n.*?\n      </div>', out, re.S)
    assert acc
    out = out[:acc.start()] + """      <div class="accordion reveal">
@foreach (beryon_rows('feedback.faqs') as $faq)
        <div class="acc-item"><button aria-expanded="false">{{ beryon_tokens((string) ($faq['q'] ?? '')) }} <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span></button><div class="panel"><div class="panel-in">{{ beryon_tokens((string) ($faq['a'] ?? '')) }}</div></div></div>
@endforeach
      </div>""" + out[acc.end():]
    save('page-feedback.blade.php', out)


def agency():
    spec = {
        'src': 'agency.html',
        'dst': 'page-agency.blade.php',
        'template_name': 'اعطای نمایندگی بریون',
        'header': '''{{--
  اعطای نمایندگی — قالب وردپرس بریون
  ------------------------------------------------------------------
  مارک‌آپ دقیقاً مطابق agency.html؛ فهرست استان‌ها از config/provinces.json
  (یا forms.provinces در تنظیمات) و گزینه‌های نوع فروش/مالکیت از ردیف‌های
  تنظیم‌پذیر می‌آید. «name» فیلدها مطابق App\\Forms::collect() است.

  هوک‌های JS: form[data-form] ، .radio-pills ، .check-line ، .form-success.show
--}}
''',
        'pairs': [
            ('      <h1>درخواست نمایندگی فروش بریون</h1>',
             "      <h1>{{ beryon_opt('agency.title') }}</h1>"),
            ('      <p>به خانواده بزرگ بریان گوشت آمل بپیوندید؛ با شبکه پخش مویرگی، زنجیره سرد یکپارچه و سبد ۶۱ محصولی، کسب‌وکارتان را توسعه دهید.</p>',
             "      <p>{{ beryon_tokens((string) beryon_opt('agency.lead')) }}</p>"),
            ('        <form data-form novalidate>',
             '        <form data-form="agency" method="post" action="{{ esc_url(beryon_form_url()) }}" novalidate>'),
            ('          <h2 style="font-size:1.25rem;margin-bottom:8px">فرم درخواست نمایندگی</h2>',
             "          <h2 style=\"font-size:1.25rem;margin-bottom:8px\">{{ beryon_opt('agency.form_title') }}</h2>"),
            ('          <p style="color:var(--muted);font-size:.88rem;margin-bottom:24px">فیلدهای ستاره‌دار الزامی هستند؛ شماره همراه شما نام کاربری پنل نمایندگی خواهد بود.</p>',
             "          <p style=\"color:var(--muted);font-size:.88rem;margin-bottom:24px\">{{ beryon_opt('agency.form_note') }}</p>"),
            ('''            <div class="field"><label for="a-fn">نام <span class="req">*</span></label><input id="a-fn" type="text" required><span class="err">نام الزامی است.</span></div>''',
             '''            <div class="field"><label for="a-fn">نام <span class="req">*</span></label><input id="a-fn" name="first_name" type="text" required autocomplete="given-name"><span class="err">نام الزامی است.</span></div>'''),
            ('''            <div class="field"><label for="a-ln">نام خانوادگی <span class="req">*</span></label><input id="a-ln" type="text" required><span class="err">نام خانوادگی الزامی است.</span></div>''',
             '''            <div class="field"><label for="a-ln">نام خانوادگی <span class="req">*</span></label><input id="a-ln" name="last_name" type="text" required autocomplete="family-name"><span class="err">نام خانوادگی الزامی است.</span></div>'''),
            ('''            <div class="field"><label for="a-tel">شماره همراه <span class="req">*</span></label><input id="a-tel" type="tel" inputmode="tel" required placeholder="09xxxxxxxxx"><span class="hint">نام کاربری نماینده همین شماره خواهد بود.</span><span class="err">شماره همراه معتبر وارد کنید.</span></div>''',
             '''            <div class="field"><label for="a-tel">شماره همراه <span class="req">*</span></label><input id="a-tel" name="tel" type="tel" inputmode="tel" required placeholder="09xxxxxxxxx" autocomplete="tel"><span class="hint">نام کاربری نماینده همین شماره خواهد بود.</span><span class="err">شماره همراه معتبر وارد کنید.</span></div>'''),
            ('''            <div class="field"><label for="a-em">ایمیل</label><input id="a-em" type="email" placeholder="you@example.com"><span class="err">ایمیل معتبر وارد کنید.</span></div>''',
             '''            <div class="field"><label for="a-em">ایمیل</label><input id="a-em" name="email" type="email" placeholder="you@example.com" autocomplete="email"><span class="err">ایمیل معتبر وارد کنید.</span></div>'''),
            ('''            <div class="field"><label for="a-co">نام شرکت</label><input id="a-co" type="text"></div>''',
             '''            <div class="field"><label for="a-co">نام شرکت</label><input id="a-co" name="company" type="text" autocomplete="organization"></div>'''),
            ('''            <div class="field"><label for="a-city">شهر <span class="req">*</span></label><input id="a-city" type="text" required><span class="err">شهر الزامی است.</span></div>''',
             '''            <div class="field"><label for="a-city">شهر <span class="req">*</span></label><input id="a-city" name="city" type="text" required autocomplete="address-level2"><span class="err">شهر الزامی است.</span></div>'''),
            ('''            <div class="field"><label for="a-wtel">تلفن محل کار</label><input id="a-wtel" type="tel" placeholder="0xx-xxxxxxxx"></div>''',
             '''            <div class="field"><label for="a-wtel">تلفن محل کار</label><input id="a-wtel" name="work_tel" type="tel" placeholder="0xx-xxxxxxxx"></div>'''),
            ('''            <div class="field full"><label for="a-addr">آدرس محل کار <span class="req">*</span></label><input id="a-addr" type="text" required><span class="err">آدرس الزامی است.</span></div>''',
             '''            <div class="field full"><label for="a-addr">آدرس محل کار <span class="req">*</span></label><input id="a-addr" name="address" type="text" required autocomplete="street-address"><span class="err">آدرس الزامی است.</span></div>
''' + honeypot('a-hp').replace('              ', '            ', 1)),
            ('''            <div class="field"><label for="a-warea">متراژ محل کار</label><input id="a-warea" type="number" min="0" placeholder="متر مربع"></div>''',
             '''            <div class="field"><label for="a-warea">متراژ محل کار</label><input id="a-warea" name="work_area" type="number" min="0" placeholder="متر مربع"></div>'''),
            ('''            <div class="field full"><label for="a-waddr">آدرس انبار</label><input id="a-waddr" type="text"></div>''',
             '''            <div class="field full"><label for="a-waddr">آدرس انبار</label><input id="a-waddr" name="warehouse_address" type="text"></div>'''),
            ('''            <div class="field"><label for="a-wtel2">تلفن انبار</label><input id="a-wtel2" type="tel"></div>''',
             '''            <div class="field"><label for="a-wtel2">تلفن انبار</label><input id="a-wtel2" name="warehouse_tel" type="tel"></div>'''),
            ('''            <div class="field"><label for="a-warea2">متراژ انبار</label><input id="a-warea2" type="number" min="0" placeholder="متر مربع"></div>''',
             '''            <div class="field"><label for="a-warea2">متراژ انبار</label><input id="a-warea2" name="warehouse_area" type="number" min="0" placeholder="متر مربع"></div>'''),
            ('''            <div class="field"><label for="a-vis">تعداد ویزیتور</label><input id="a-vis" type="number" min="0" placeholder="نفر"></div>''',
             '''            <div class="field"><label for="a-vis">تعداد ویزیتور</label><input id="a-vis" name="visitors" type="number" min="0" placeholder="نفر"></div>'''),
            ('''            <div class="field"><label for="a-car">تعداد و نوع خودرو</label><input id="a-car" type="text" placeholder="مثلاً ۲ یخچال‌دار"></div>''',
             '''            <div class="field"><label for="a-car">تعداد و نوع خودرو</label><input id="a-car" name="vehicles" type="text" placeholder="مثلاً ۲ یخچال‌دار"></div>'''),
            ('''            <div class="field full"><label for="a-cov">شهرهای تحت پوشش <span class="req">*</span></label><input id="a-cov" type="text" required placeholder="مثلاً آمل، بابل، قائم‌شهر"><span class="err">شهرهای تحت پوشش را بنویسید.</span></div>''',
             '''            <div class="field full"><label for="a-cov">شهرهای تحت پوشش <span class="req">*</span></label><input id="a-cov" name="coverage" type="text" required placeholder="مثلاً آمل، بابل، قائم‌شهر"><span class="err">شهرهای تحت پوشش را بنویسید.</span></div>'''),
            ('''            <div class="field full check-line"><input id="a-agree" type="checkbox" required><label for="a-agree">اینجانب موافقت خود را برای دریافت نمایندگی محصولات بریان گوشت آمل اعلام می‌دارم و صحت اطلاعات وارد شده را تأیید می‌کنم.</label></div>''',
             '''            <div class="field full check-line"><input id="a-agree" name="agree" value="1" type="checkbox" required><label for="a-agree">{{ beryon_opt('agency.agree_text') }}</label></div>'''),
            ('        <h3>درخواست شما با موفقیت ثبت شد</h3>',
             "        <h3>{{ beryon_opt('agency.success_title') }}</h3>"),
            ('        <p>کارشناسان ما در اسرع وقت درخواست شما را بررسی کرده و با شما تماس خواهند گرفت. لطفاً کد پیگیری زیر را نزد خود نگه دارید.</p>',
             "        <p>{{ beryon_opt('agency.success_text') }}</p>"),
            ('        <span class="track">BRY-۱۴۰۴-۰۰۰۰</span>',
             '        <span class="track"></span>'),
        ],
    }

    out = build(spec)
    out = swap_breadcrumb(out, [('خانه', "home_url('/')"), ("beryon_opt('agency.crumb', 'اعطای نمایندگی')", None)])

    # گام‌ها
    steps = re.search(r'      <div class="grid steps">\n.*?\n      </div>', out, re.S)
    assert steps
    out = out[:steps.start()] + """      <div class="grid steps">
@foreach (beryon_rows('agency.steps') as $index => $step)
        <article class="step reveal{{ $index === 1 ? ' d1' : ($index === 2 ? ' d2' : ($index === 3 ? ' d3' : '')) }}"><span class="ic">@php(beryon_svg($step['icon'] ?? 'file'))</span><h2>{{ $step['title'] ?? '' }}</h2><p>{{ $step['text'] ?? '' }}</p></article>
@endforeach
      </div>""" + out[steps.end():]

    # استان‌ها
    prov = re.search(r'              <select id="a-prov" required>.*?</select>', out, re.S)
    assert prov
    out = out[:prov.start()] + """              <select id="a-prov" name="province" required>
                <option value="">— انتخاب کنید —</option>
@foreach (beryon_provinces() as $province)
                <option>{{ $province }}</option>
@endforeach
              </select>""" + out[prov.end():]

    # نوع مالکیت دفتر فروش و انبار
    own_block = '''<select id="a-own%s" name="%s"><option value="">— انتخاب کنید —</option>
@foreach (beryon_rows('agency.ownership_types') as $ownership)
                  <option>{{ $ownership }}</option>
@endforeach
                </select>'''
    out = sub_once(out, '<select id="a-own"><option value="">— انتخاب کنید —</option><option>خصوصی</option><option>استیجاری</option><option>مشارکت</option></select>',
                   own_block % ('', 'work_ownership'))
    out = sub_once(out, '<select id="a-own2"><option value="">— انتخاب کنید —</option><option>خصوصی</option><option>استیجاری</option><option>مشارکت</option></select>',
                   own_block % ('2', 'warehouse_ownership'))

    # نوع فروش (چک‌باکس‌ها)
    sales = re.search(r'              <div class="radio-pills">\n.*?\n              </div>', out, re.S)
    assert sales
    out = out[:sales.start()] + """              <div class="radio-pills">
@foreach (beryon_rows('agency.sales_types') as $index => $sales_type)
                <input type="checkbox" id="s{{ $index + 1 }}" name="sales_types[]" value="{{ $sales_type }}"><label for="s{{ $index + 1 }}">{{ $sales_type }}</label>
@endforeach
              </div>""" + out[sales.end():]
    save('page-agency.blade.php', out)


if __name__ == '__main__':
    contact()
    feedback()
    agency()
