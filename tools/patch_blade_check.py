#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""به‌روزرسانی tools/blade_check.py: تفکیک نحو جایگزین (:) از آکولادی ({}) و نادیده‌گرفتن ایمیل‌ها."""
import io
import os

PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'blade_check.py')

NEW_BLOCK = '''CLOSERS = {'endif': 'if', 'endforeach': 'foreach', 'endfor': 'for', 'endwhile': 'while', 'endswitch': 'switch'}
RE_TOKEN = re.compile(
    r'\\b(if|foreach|for|while|switch)\\s*\\('          # 1 opener
    r'|\\b(endif|endforeach|endfor|endwhile|endswitch)\\s*;'  # 2 closer
    r'|\\b(else|elseif)\\b'                              # 3 branch
    r'|\\b(case|default)\\b'                             # 4 switch branch
    r'|([{}])'                                           # 5 brace
    r'|\\b(ob_start|ob_get_clean|ob_end_clean)\\s*\\('    # 6 buffer
)


def match_paren(code, start):
    """موقعیت ) متناظر با ( در start (رشته‌ها/کامنت‌ها پیش‌تر پاک شده‌اند)."""
    depth = 0
    i = start
    while i < len(code):
        if code[i] == '(':
            depth += 1
        elif code[i] == ')':
            depth -= 1
            if depth == 0:
                return i
        i += 1
    return -1


def next_meaningful(code, i):
    while i < len(code) and code[i] in ' \\t\\r\\n':
        i += 1
    return code[i] if i < len(code) else ''


def check_structures(blocks):
    """توازن ساختارهای کنترلی با پشته — تفکیک نحو جایگزین (:) از آکولادی ({})."""
    errors, stack, ob = [], [], 0

    for block in blocks:
        code = strip_literals(block)
        for match in RE_TOKEN.finditer(code):
            opener, closer, branch, case, brace, buffer = match.groups()

            if buffer:
                ob += 1 if buffer == 'ob_start' else -1
                continue

            if opener:
                paren = code.find('(', match.start())
                close = match_paren(code, paren)
                if close < 0:
                    errors.append('پرانتز نابسته در %s' % opener)
                    continue
                following = next_meaningful(code, close + 1)
                if following == ':':
                    stack.append(('alt', opener))
                elif following == '{':
                    stack.append(('brace', opener))
                else:
                    stack.append(('expr', opener))
            elif closer:
                expected = ('alt', CLOSERS[closer])
                if not stack:
                    errors.append('%s بدون ساختار باز' % closer)
                elif stack[-1] != expected:
                    errors.append('%s اما بالای پشته %s:%s است' % ((closer,) + stack[-1]))
                    if stack[-1][0] == 'alt':
                        stack.pop()
                else:
                    stack.pop()
            elif branch:
                if not stack or stack[-1] != ('alt', 'if'):
                    errors.append('%s بیرون از if با نحو جایگزین' % branch)
            elif case:
                if not stack or stack[-1] != ('alt', 'switch'):
                    errors.append('%s بیرون از switch' % case)
            elif brace == '{':
                stack.append(('brace', 'block'))
            elif brace == '}':
                while stack and stack[-1][0] == 'expr':
                    stack.pop()
                if not stack or stack[-1][0] != 'brace':
                    errors.append('} بدون { متناظر')
                else:
                    stack.pop()

    left = [item for item in stack if item[0] != 'expr']
    if left:
        errors.append('ساختارهای بسته‌نشده: %s' % ', '.join('%s:%s' % item for item in left))
    if ob != 0:
        errors.append('ob_start/ob_get_clean نامتوازن (%+d)' % ob)
    return ['» ' + item for item in errors]


'''


def main():
    with io.open(PATH, encoding='utf-8') as handle:
        source = handle.read()

    start = source.index('CLOSERS = {')
    end = source.index('RE_RELATIVE_CLASS = re.compile')
    source = source[:start] + NEW_BLOCK + source[end:]

    old = "    for match in re.finditer(r'@([A-Za-z][A-Za-z0-9_\\-]*)', html):"
    new = "    for match in re.finditer(r'(?<![\\w.@\\-])@([A-Za-z][A-Za-z0-9_\\-]*)', html):"
    if source.count(old) != 1:
        raise SystemExit('لنگر ایمیل پیدا نشد: %d' % source.count(old))
    source = source.replace(old, new)

    with io.open(PATH, 'w', encoding='utf-8', newline='\n') as handle:
        handle.write(source)
    print('blade_check.py به‌روز شد')


if __name__ == '__main__':
    main()
