#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
اصلاح دوم MiniBlade + نماها.

۱) @forelse در Blade استاندارد به فرم «@forelse($items as $k => $v)» نوشته می‌شود
   (بدون کاما). MiniBlade فقط فرم سه‌بخشی کامایی را می‌فهمید و در نتیجه
   @forelse به foreach ساده تبدیل می‌شد ولی @empty هنوز «endforeach; else:»
   تولید می‌کرد → خطای نحوی «else بدون if» (partials/blog-list.blade.php).
   حالا هر دو فرم پشتیبانی می‌شود.

۲) نام کلاس‌های نسبی در نماها (App\\Products، WP_Term) با \\ پیشرو اصلاح می‌شوند
   تا هم در Blade واقعی و هم در مسیر eval کامپایلر درست حل شوند.
"""
import io
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MINIBLADE = os.path.join(ROOT, 'beryon-wp', 'app', 'MiniBlade.php')
VIEWS = os.path.join(ROOT, 'beryon-wp', 'resources', 'views')

OLD_FORELSE = """                    case 'forelse':
                        $parts = self::splitArgs($args);

                        if (count($parts) < 3) {
                            return '<?php foreach (' . $args . '): ?>';
                        }

                        self::$forelseDepth++;

                        $tmp = '$__beryonFe' . self::$forelseDepth;

                        // if … : foreach … : → @empty → endforeach; else: → @endforelse → endif;
                        return '<?php ' . $tmp . ' = ' . $parts[2] . ';'
                            . ' if (is_array(' . $tmp . ') && ' . $tmp . ' !== []):'
                            . ' foreach (' . $tmp . ' as ' . $parts[0] . ' => ' . $parts[1] . '): ?>';
"""

NEW_FORELSE = """                    case 'forelse':
                        // فرم استاندارد Blade:  @forelse($items as $key => $value)
                        if (preg_match('/^(.+?)\\s+as\\s+(.+)$/is', $args, $as) === 1) {
                            $list = trim($as[1]);
                            $loop = trim($as[2]);
                        } else {
                            // فرم سه‌بخشی:  @forelse($items, $key, $value)
                            $parts = self::splitArgs($args);

                            if (count($parts) < 3) {
                                return '<?php foreach (' . $args . '): ?>';
                            }

                            $list = $parts[2];
                            $loop = $parts[0] . ' => ' . $parts[1];
                        }

                        self::$forelseDepth++;

                        $tmp = '$__beryonFe' . self::$forelseDepth;

                        // ساختار: if … : foreach … : → @empty → endforeach; else: → @endforelse → endif;
                        return '<?php ' . $tmp . ' = ' . $list . ';'
                            . ' if (is_array(' . $tmp . ') && ' . $tmp . ' !== []):'
                            . ' foreach (' . $tmp . ' as ' . $loop . '): ?>';
"""


def read(path):
    with io.open(path, encoding='utf-8') as handle:
        return handle.read()


def write(path, text):
    tmp = path + '.tmp'
    with io.open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
        handle.write(text)
    os.replace(tmp, path)


def patch_miniblade():
    source = read(MINIBLADE)
    if source.count(OLD_FORELSE) != 1:
        raise SystemExit('لنگر @forelse پیدا نشد (count=%d)' % source.count(OLD_FORELSE))
    write(MINIBLADE, source.replace(OLD_FORELSE, NEW_FORELSE))
    print('MiniBlade.php: @forelse حالا فرم «as» استاندارد Blade را می‌فهمد ✔')


BS = chr(92)  # بک‌اسلش — بدون ابهام escape

CLASS_FIXES = [
    (re.compile(r'(?<![\w\\])App\\(Products|Assets|Settings|Seo|Nav|Jalali|Forms|MiniBlade|Template|Security|Updater|Admin)\\?'),
     lambda m: BS + 'App' + BS + m.group(1)),
    (re.compile(r'(?<![\w\\])WP_(Term|Post|Query|Error|User|HTTP|Object)(?![\w])'),
     lambda m: BS + 'WP_' + m.group(1)),
]


def patch_views():
    changed = 0
    for root, dirs, names in os.walk(VIEWS):
        dirs.sort()
        for name in sorted(names):
            if not name.endswith('.blade.php'):
                continue
            path = os.path.join(root, name)
            source = read(path)
            updated = source
            for pattern, repl in CLASS_FIXES:
                updated = pattern.sub(repl, updated)
            updated = updated.replace(BS + BS + 'App' + BS, BS + 'App' + BS)  # محافظ اجرا دوباره
            if updated != source:
                write(path, updated)
                changed += 1
                print('  %-46s %d اصلاح' % (os.path.relpath(path, VIEWS),
                                            sum(1 for a, b in zip(source, updated) if a != b) or 1))
    print('نماهای اصلاح‌شده: %d' % changed)


if __name__ == '__main__':
    patch_miniblade()
    patch_views()
    sys.exit(0)
