#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
اصلاح باگ‌های فرار (escaping) در نماها.

Blade هر دو موتور (واقعی و MiniBlade) در {{ }} خروجی را با htmlspecialchars
فرار می‌کنند؛ بنابراین:
  • توابعی که HTML تولید می‌کنند (beryon_pagination، beryon_image_tag، …)
  • beryon_amp() که «&» را به «&amp;» تبدیل می‌کند
  • رشته‌های HTML داخل عبارت (مثل '<small>')
باید با {!! !!} چاپ شوند، وگرنه برچسب‌ها به‌صورت متن دیده می‌شوند یا «&»
دو بار فرار می‌شود (R&amp;D → R&amp;amp;D).

مورد واقعی: «مدیریت R&D» در چارت سازمانی و صفحه‌بندی مجله.
"""
import io
import os
import sys

VIEWS = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                     'beryon-wp', 'resources', 'views')

FIXES = [
    ('partials/blog-list.blade.php',
     '{{ beryon_pagination() }}',
     '{!! beryon_pagination() !!}'),

    ('page-org-chart.blade.php',
     'aria-label="چارت سازمانی {{ beryon_amp(beryon_opt(\'general.company_name\')) }}"',
     'aria-label="چارت سازمانی {!! beryon_amp(beryon_opt(\'general.company_name\')) !!}"'),

    ('page-org-chart.blade.php',
     '<span class="org-node mgr">{{ beryon_amp($unit[\'title\'] ?? \'\') }}</span>',
     '<span class="org-node mgr">{!! beryon_amp($unit[\'title\'] ?? \'\') !!}</span>'),

    ('page-org-chart.blade.php',
     '<span class="org-node">{{ beryon_amp($child[\'title\'] ?? \'\') }}'
     '{{ ! empty($child[\'sub\']) ? \'<small>\' . esc_html($child[\'sub\']) . \'</small>\' : \'\' }}</span>',
     '<span class="org-node">{!! beryon_amp($child[\'title\'] ?? \'\') !!}'
     '{!! ! empty($child[\'sub\']) ? \'<small>\' . esc_html($child[\'sub\']) . \'</small>\' : \'\' !!}</span>'),

    ('single.blade.php',
     '<small>{{ beryon_amp(beryon_opt(\'blog.author_note\')) }}</small>',
     '<small>{!! beryon_amp(beryon_opt(\'blog.author_note\')) !!}</small>'),
]


def main():
    applied = 0
    for relative, old, new in FIXES:
        path = os.path.join(VIEWS, relative)
        with io.open(path, encoding='utf-8') as handle:
            source = handle.read()

        if new in source and old not in source:
            print('  (skip) %-34s پیش‌تر اصلاح شده' % relative)
            continue

        if source.count(old) != 1:
            raise SystemExit('ANCHOR FAIL %s count=%d' % (relative, source.count(old)))

        with io.open(path + '.tmp', 'w', encoding='utf-8', newline='\n') as handle:
            handle.write(source.replace(old, new))
        os.replace(path + '.tmp', path)
        applied += 1
        print('  ✔ %-34s %s' % (relative, old[:52] + '…'))

    print('اصلاحات اعمال‌شده: %d از %d' % (applied, len(FIXES)))
    return 0


if __name__ == '__main__':
    sys.exit(main())
