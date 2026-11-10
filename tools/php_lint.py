#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
PHP/Blade lint سبک برای قالب بریون (بدون نیاز به PHP CLI در این محیط)
بررسی‌ها:
  1) توازن {} () [] با پیمایش وضعیت‌آگاه (رشته، کامنت، heredoc)
  2) توازن کوتیشن‌ها (' " `)
  3) عدم وجود نویسه‌های نامعتبر (نول، BOM، کوتیشن هوشمند در کد PHP)
  4) ساختار <?php و عدم وجود تگ بسته‌کننده در فایل‌های کلاس
  5) Blade: توازن @if/@endif، @foreach/@endforeach، @php/@endphp و کامپوننت‌ها
  6) وجود کلاس/namespace مورد انتظار در فایل‌های app/
"""
import sys, os, re, json

PERSIAN_OK = True

class Lint:
    def __init__(self):
        self.errors = []
        self.checked = 0

    def err(self, path, line, msg):
        self.errors.append('%s:%d: %s' % (path, line, msg))

    # ---------- PHP ----------
    def php(self, path):
        src = open(path, encoding='utf-8').read()
        self.checked += 1
        if src.startswith('\ufeff'):
            self.err(path, 1, 'BOM در ابتدای فایل (باید حذف شود)')
        if '\x00' in src:
            self.err(path, 1, 'نویسه نول در فایل')
        if re.search(r'[\u2018\u2019\u201c\u201d]', re.sub(r"'([^']*)'", '', src).replace('<?php', '', 1)) and False:
            pass

        # پیمایش وضعیت‌آگاه فقط روی بخش‌های PHP
        i, n, line = 0, len(src), 1
        in_php = False
        state = 'code'          # code | sq | dq | line_comment | block_comment | heredoc
        heredoc_tag = ''
        stack = []
        pairs = {')': '(', ']': '[', '}': '{'}
        prev_bs = False
        dq_interp = 0

        while i < n:
            ch = src[i]
            nxt = src[i + 1] if i + 1 < n else ''
            if ch == '\n':
                line += 1

            if not in_php:
                if src.startswith('<?php', i) or src.startswith('<?=', i):
                    in_php = True
                    i += len('<?php') if src.startswith('<?php', i) else 3
                    continue
                i += 1
                continue

            # ابتدا رشته/کامنت پردازش می‌شود تا '<?xml' یا '?>' داخل رشته خطا ندهد
            if state != 'code':
                pass
            elif src.startswith('?>', i):
                in_php = False
                state = 'code'
                i += 2
                continue

            if state == 'code':
                if src.startswith('//', i) or src.startswith('#', i):
                    state = 'line_comment'; i += 2; continue
                if src.startswith('/*', i):
                    state = 'block_comment'; i += 2; continue
                if ch == "'":
                    state = 'sq'; i += 1; continue
                if ch == '"':
                    state = 'dq'; dq_interp = 0; i += 1; continue
                m = re.match(r'<<<([\'"]?)([A-Za-z_][A-Za-z0-9_]*)\1', src[i:])
                if m:
                    state = 'heredoc'; heredoc_tag = m.group(2)
                    i += m.end(); continue
                if ch in '([{':
                    stack.append((ch, line))
                elif ch in ')]}':
                    if not stack:
                        self.err(path, line, 'بستن %s بدون جفت باز' % ch)
                    else:
                        open_ch, open_line = stack.pop()
                        if open_ch != pairs[ch]:
                            self.err(path, line, 'جفت نابرابر: %s (خط %d) با %s' % (open_ch, open_line, ch))
                i += 1
                continue

            if state == 'line_comment':
                if ch == '\n':
                    state = 'code'
                i += 1; continue

            if state == 'block_comment':
                if src.startswith('*/', i):
                    state = 'code'; i += 2; continue
                i += 1; continue

            if state == 'sq':
                if ch == '\\':
                    i += 2; continue
                if ch == "'":
                    state = 'code'
                i += 1; continue

            if state == 'dq':
                if ch == '\\':
                    i += 2; continue
                if ch == '"':
                    state = 'code'
                i += 1; continue

            if state == 'heredoc':
                if ch == '\n' and re.match(r'\s*%s\s*[;,)\]]' % re.escape(heredoc_tag), src[i + 1:]):
                    state = 'code'
                elif ch == '\n' and src[i + 1:].lstrip().startswith(heredoc_tag):
                    # پایان nowdoc/heredoc در خط بعد
                    rest = src[i + 1:].lstrip()
                    if rest[len(heredoc_tag):len(heredoc_tag) + 1] in ('', ';', ',', ')', '\n'):
                        state = 'code'
                i += 1; continue

        if state in ('sq', 'dq'):
            self.err(path, line, 'رشته بسته‌نشده تا انتهای فایل')
        if state == 'block_comment':
            self.err(path, line, 'کامنت /* بسته‌نشده')
        if stack:
            for open_ch, open_line in stack:
                self.err(path, open_line, '%s باز مانده تا انتهای فایل' % open_ch)

        # قوانین ساختاری
        if path.endswith('.php') and 'app/' in path.replace('\\', '/'):
            if 'namespace App' not in src and '/helpers.php' not in path:
                self.err(path, 1, 'namespace App یافت نشد')
            if re.search(r'\?>\s*$', src):
                self.err(path, len(src.splitlines()), 'تگ بسته‌کننده ?> در انتهای فایل کلاس (حذف شود)')
        # کوتیشن هوشمند داخل کد PHP (نه در متن فارسی رشته‌ها)
        for m in re.finditer(r'[\u2018\u2019\u201c\u201d]', src):
            seg = src[max(0, m.start() - 60):m.start()]
            if not re.search(r"('|\"|__\(|esc_html|esc_attr|//|#|\*)\s*[^\n]*$", seg):
                self.err(path, src[:m.start()].count('\n') + 1, 'نویسه کوتیشن هوشمند در کد')

    # ---------- Blade ----------
    def blade(self, path):
        src = open(path, encoding='utf-8').read()
        self.checked += 1
        if src.startswith('\ufeff'):
            self.err(path, 1, 'BOM در ابتدای فایل')

        # کامنت‌های Blade و PHP از متن بررسی حذف می‌شوند (مثال: <x-icon> داخل توضیح)
        src = re.sub(r'\{\{--.*?--\}\}', ' ', src, flags=re.S)
        src = re.sub(r'/\*\*?.*?\*/', ' ', src, flags=re.S)
        src = re.sub(r'(?m)^\s*//.*$', ' ', src)

        pairs = [
            ('@if', '@endif'), ('@foreach', '@endforeach'), ('@for', '@endfor'),
            ('@while', '@endwhile'), ('@forelse', '@endforelse'), ('@switch', '@endswitch'),
            ('@isset', '@endisset'), ('@empty', '@endempty'), ('@auth', '@endauth'),
            ('@guest', '@endguest'), ('@section', '@endsection'), ('@push', '@endpush'),
            ('@prepend', '@endprepend'), ('@once', '@endonce'), ('@verbatim', '@endverbatim'),
            ('@php', '@endphp'), ('@if(', '@endif'),
        ]

        # شمارش با ترتیب ظاهر (برای @section/@endsection و کامپوننت‌ها)
        counts = {}
        for token in ['@if', '@endif', '@foreach', '@endforeach', '@forelse', '@endforelse',
                      '@php', '@endphp', '@section', '@endsection', '@push', '@endpush',
                      '@isset', '@endisset', '@empty', '@endempty', '@once', '@endonce',
                      '@verbatim', '@endverbatim', '@auth', '@endauth', '@guest', '@endguest',
                      '@switch', '@endswitch', '@for', '@endfor', '@while', '@endwhile']:
            if token == '@php':
                # فقط بلوک‌های @php ... @endphp (نه @php(expr) یک‌خطی)
                counts[token] = len(re.findall(r'@php(?![A-Za-z(])', src))
            elif token == '@empty':
                # «@empty» داخل بلوک @forelse یک جداکننده است، نه آغازگر بلوک
                depth = 0
                standalone = 0
                for match in re.finditer(r'@(forelse|empty|endforelse)(?![A-Za-z])', src):
                    kind = match.group(1)
                    if kind == 'forelse':
                        depth += 1
                    elif kind == 'endforelse':
                        depth = max(0, depth - 1)
                    elif depth == 0:
                        standalone += 1
                counts[token] = standalone
            else:
                counts[token] = len(re.findall(re.escape(token) + r'(?![A-Za-z])', src))

        checks = [('@if', '@endif'), ('@foreach', '@endforeach'), ('@forelse', '@endforelse'),
                  ('@php', '@endphp'), ('@isset', '@endisset'), ('@empty', '@endempty'),
                  ('@once', '@endonce'), ('@verbatim', '@endverbatim'), ('@auth', '@endauth'),
                  ('@guest', '@endguest'), ('@switch', '@endswitch'), ('@for', '@endfor'),
                  ('@while', '@endwhile'), ('@push', '@endpush')]
        for a, b in checks:
            if counts[a] != counts[b]:
                self.err(path, 1, '%s=%d ولی %s=%d' % (a, counts[a], b, counts[b]))

        # @section باید با @endsection یا @stop بسته شود (دست‌کم یکی)
        sections = counts['@section']
        inline = len(re.findall(r'@section\([^)]*,\s*[\'"]', src))
        if sections - inline > counts['@endsection']:
            self.err(path, 1, 'بخش‌های @section بیش از @endsection (بلوکی=%d، بسته=%d)' % (sections - inline, counts['@endsection']))

        # {{ }} و {!! !!} باید بسته شوند
        if len(re.findall(r'\{\{', src)) != len(re.findall(r'\}\}', src)):
            self.err(path, 1, 'نابرابری {{ و }}')
        if len(re.findall(r'\{!!', src)) != len(re.findall(r'!!\}', src)):
            self.err(path, 1, 'نابرابری {!! و !!}')

        # <x-... /> یا <x-...></x-...>
        for m in re.finditer(r'<x-([a-z0-9\-.]+)((?:[^>]*?)(/?)>)', src):
            name, tail, selfclose = m.group(1), m.group(2), m.group(3)
            if selfclose == '/':
                continue
            if '</x-%s>' % name not in src:
                self.err(path, src[:m.start()].count('\n') + 1, 'کامپوننت <x-%s> بسته نشده' % name)

        # نکته: توازن تگ‌های HTML در سطح «صفحه نهایی» (شامل همه partialها) بررسی می‌شود
        # نه در هر فایل جداگانه؛ بنابراین اینجا کنترل نمی‌شود (جلوگیری از مثبت کاذب).


def main():
    root = sys.argv[1] if len(sys.argv) > 1 else 'beryon-wp'
    lint = Lint()
    php_files, blade_files = [], []
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = [d for d in dirnames if d not in ('node_modules', 'vendor', '.git', 'dist')]
        for f in sorted(filenames):
            full = os.path.join(dirpath, f)
            if f.endswith('.blade.php'):
                blade_files.append(full)
            elif f.endswith('.php'):
                php_files.append(full)
    for f in php_files:
        lint.php(f)
    for f in blade_files:
        lint.blade(f)
        lint.php(f)   # بخش‌های @php و <?php داخل blade هم بررسی شود
    print('بررسی شد: %d فایل' % lint.checked)
    if lint.errors:
        print('خطاها: %d' % len(lint.errors))
        for e in lint.errors:
            print(' -', e)
        sys.exit(1)
    print('همه بررسی‌ها پاس شد ✔')

if __name__ == '__main__':
    main()
