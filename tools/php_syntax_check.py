#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
php_syntax_check.py — بررسی نحوی ایستای فایل‌های PHP بدون نیاز به مفسر PHP.

چرا؟ در محیط ساخت `php -l` در دسترس نیست و لینتر قبلی (php_lint.py) فقط توازن
آکولاد/برچسب را می‌سنجد؛ بنابراین یک رشته دوتایی‌گیومه شکسته (گیومه داخلی
escape‌نشده) که **خطای Parse مرگبار** می‌سازد از همه بررسی‌ها رد شد و تا سایت
کاربر رفت.

این ابزار یک lexer کوچک PHP پیاده‌سازی می‌کند:

  حالت‌ها: HTML بیرون از <?php ، CODE ، رشته 'تک' ، رشته "دو" ، کامنت // و # ،
  کامنت /* */ ، heredoc.

  بررسی‌ها:
   E1) پس از بسته‌شدن یک رشته، نویسه معنادار بعدی باید operador/جداکننده باشد؛
       اگر حرف/عدد/$/گیومه/( باشد یعنی رشته زودتر بسته شده = خطای نحوی.
       (دقیقاً همان باگ Template.php:148 — «utf» پس از "…charset=")
   E2) توازن ( [ { در حالت CODE.
   E3) رشته/کامنت بسته‌نشده تا پایان فایل.
   W1) رشته دوتایی‌گیومه چندخطی (نشانه escape اشتباه \\n).
   W2) «\\"» داخل رشته تک‌گیومه (در PHP یعنی خودِ backslash چاپ می‌شود).
   W3) پارامتر با نوع بدون ? و مقدار null (در PHP 8.4 deprecated).

کاربرد:  python3 tools/php_syntax_check.py [مسیر] [--strict]
"""
import io
import os
import sys

ALLOWED_AFTER_STRING = set('. , ; ) ] } ? : = + - * / % < > ! & | ^ ~ [ #'.split(' '))
KEYWORDS_AFTER_STRING = {'and', 'or', 'xor', 'instanceof', 'echo', 'print', 'return'}
WORD = set('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_0123456789')

IMPLICIT_NULLABLE = None  # پر شود در main


def scan(path, source):
    """اسکن یک فایل PHP؛ بازگشت (errors, warnings)."""
    errors, warnings = [], []
    state = 'HTML'
    stack = []
    i, n = 0, len(source)
    line = 1
    string_start_line = 0
    string_start_i = [0]
    multiline_dq = [False]
    heredoc_id = ''

    def err(msg, at):
        errors.append('خطا  خط %-5d %s' % (at, msg))

    def warn(msg, at):
        warnings.append('هشدار خط %-5d %s' % (at, msg))

    while i < n:
        char = source[i]

        if char == '\n':
            line += 1

        # ---------------------------------------------------------- HTML
        if state == 'HTML':
            if source.startswith('<?php', i) or source.startswith('<?=', i):
                state = 'CODE'
                i += 5 if source[i + 2] == 'p' else 3
                continue
            i += 1
            continue

        # ---------------------------------------------------------- کامنت‌ها
        if state == 'LC':
            if char == '\n':
                state = 'CODE'
            i += 1
            continue

        if state == 'BC':
            if source.startswith('*/', i):
                state = 'CODE'
                i += 2
                continue
            i += 1
            continue

        if state == 'HD':
            if char == '\n':
                j = i + 1
                k = j
                while k < n and source[k] in ' \t':
                    k += 1
                if source.startswith(heredoc_id, k):
                    after = k + len(heredoc_id)
                    if after >= n or source[after] in ';\n\r':
                        state = 'CODE'
                        i = after
                        continue
            i += 1
            continue

        # ---------------------------------------------------------- رشته تک
        if state == 'SQ':
            if char == '\\':
                if i + 1 < n and source[i + 1] == '"':
                    warn('«\\"» داخل رشته تک‌گیومه (backslash چاپ می‌شود)', line)
                i += 2
                continue
            if char == "'":
                state = 'CODE'
                pending = i + 1
                i += 1
                if multiline_dq[0] and source[string_start_i[0]:i - 1].strip() == '':
                    warn('رشته دوتایی‌گیومه فقط شامل newline واقعی است (باید escape شود)', string_start_line)
                nxt = after_space(source, pending)
                check_after_string(nxt, errors, warnings, line)
                continue
            i += 1
            continue

        # ---------------------------------------------------------- رشته دو
        if state == 'DQ':
            if char == '\\':
                i += 2
                continue
            if char == '\n':
                multiline_dq[0] = True
            if char == '"':
                state = 'CODE'
                pending = i + 1
                i += 1
                nxt = after_space(source, pending)
                check_after_string(nxt, errors, warnings, line)
                continue
            i += 1
            continue

        # ---------------------------------------------------------- CODE
        if source.startswith('//', i) or char == '#':
            state = 'LC'
            i += 2 if char == '/' else 1
            continue

        if source.startswith('/*', i):
            state = 'BC'
            i += 2
            continue

        if source.startswith('<<<', i):
            j = i + 3
            quote = ''
            if j < n and source[j] in "'\"":
                quote = source[j]
                j += 1
            k = j
            while k < n and (source[k].isalnum() or source[k] == '_'):
                k += 1
            heredoc_id = source[j:k]
            state = 'HD'
            i = k
            continue

        if char == "'":
            state = 'SQ'
            string_start_line = line
            i += 1
            continue

        if char == '"':
            state = 'DQ'
            string_start_line = line
            string_start_i[0] = i + 1
            multiline_dq[0] = False
            i += 1
            continue

        if char in '([{':
            stack.append((char, line))
            i += 1
            continue

        if char in ')]}':
            pair = {'(': ')', '[': ']', '{': '}'}
            if not stack:
                err('«%s» بدون بازکننده' % char, line)
            elif pair[stack[-1][0]] != char:
                err('«%s» بسته‌نشده (بازشده در خط %d با %s)' % (char, stack[-1][1], stack[-1][0]), line)
                stack.pop()
            else:
                stack.pop()
            i += 1
            continue

        if source.startswith('?>', i):
            state = 'HTML'
            stack = []
            i += 2
            continue

        i += 1

    if state in ('SQ', 'DQ'):
        err('رشته بسته‌نشده تا پایان فایل (شروع خط %d)' % string_start_line, line)
    if state == 'BC':
        err('کامنت /* بسته‌نشده', line)
    if state == 'HD':
        err('heredoc «%s» بسته‌نشده' % heredoc_id, line)
    for open_char, open_line in stack:
        err('«%s» باز شده در خط %d بسته نشده' % (open_char, open_line), line)

    # ------------------------------------------------- پارامتر nullable ضمنی
    import re as _re
    for match in _re.finditer(
            r'function\s+\w+\s*\([^)]*?\b(string|int|float|bool|array|iterable|callable|object|self)'
            r'\s+\$\w+\s*=\s*null', source):
        warn('پارامتر «%s $… = null» در PHP 8.4 deprecated است (از ?%s استفاده کنید)'
             % (match.group(1), match.group(1)), source[:match.start()].count('\n') + 1)

    return errors, warnings


def after_space(source, i):
    n = len(source)
    while i < n and source[i] in ' \t\r\n':
        i += 1
    return source[i] if i < n else ''


def check_after_string(nxt, errors, warnings, line):
    if nxt == '' or nxt in ALLOWED_AFTER_STRING:
        return
    if nxt.isalpha() or nxt == '_':
        j = 0
        while j < len(nxt) and nxt[j] in WORD:
            j += 1
        word = nxt[:j]
        if word in KEYWORDS_AFTER_STRING:
            return
    if nxt == '$' or nxt in '("\'\\':
        errors.append('خطا  خط %-5d پس از بسته‌شدن رشته، «%s» آمده → رشته زودتر بسته شده '
                      '(گیومه داخلی escape نشده؟)' % (line, nxt))
    # بقیه نویسه‌ها (مثل حرف/عدد) هم خطا هستند
    elif nxt.isalnum():
        errors.append('خطا  خط %-5d پس از بسته‌شدن رشته، شناسه «%s» آمده → گیومه داخلی '
                      'escape نشده؟' % (line, nxt))


def main():
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    strict = '--strict' in sys.argv
    root = args[0] if args else os.path.join(
        os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'beryon-wp')

    files = []
    for dirpath, dirs, names in os.walk(root):
        dirs[:] = [d for d in dirs if d not in ('.git', 'node_modules', 'vendor')]
        for name in sorted(names):
            if name.endswith('.php'):
                files.append(os.path.join(dirpath, name))

    total_e = total_w = 0
    for path in files:
        with io.open(path, encoding='utf-8', errors='replace') as handle:
            source = handle.read()
        errors, warnings = scan(path, source)
        relative = os.path.relpath(path, root)
        if errors or warnings:
            print('%-58s %s' % (relative, 'FAIL' if errors else 'warn'))
            for item in errors:
                print('      ✗ %s' % item)
            for item in warnings:
                print('      ! %s' % item)
        total_e += len(errors)
        total_w += len(warnings)

    print('-' * 74)
    print('فایل‌های PHP: %d | خطای نحوی: %d | هشدار: %d' % (len(files), total_e, total_w))
    if total_e or (strict and total_w):
        return 1
    print('همه فایل‌ها از نظر نحوی سالم‌اند ✔')
    return 0


if __name__ == '__main__':
    sys.exit(main())
