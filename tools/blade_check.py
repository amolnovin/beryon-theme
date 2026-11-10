#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
blade_check.py — راستی‌آزمایی ایستای کامپایلر Blade قالب بریون.

چرا این ابزار؟ در محیط ساخت PHP/composer در دسترس نیست؛ بنابراین مراحل کامپایل
app/MiniBlade.php به‌دقت به پایتون منتقل شده و خروجی PHP هر نما بررسی می‌شود:

  1) توازن <?php و ?> (و نبود <?php تودرتو = همان باگ @endphp)
  2) توازن ساختارهای کنترلی با پشته، با تفکیک نحو جایگزین (if…: … endif;) از
     نحو آکولادی (if (…) { … }) که داخل بلوک‌های @php استفاده می‌شود
  3) نبود هیچ @دستور کامپایل‌نشده در خروجی
  4) نام کلاس نسبی (App\\X یا WP_X بدون \\ پیشرو) → هشدار
  5) جفت بودن @php بلوکی با @endphp، نبود {{ }} داخل بلوک PHP، توازن ob_start

کاربرد:  python3 tools/blade_check.py [--strict] [مسیر قالب]
"""
import io
import os
import re
import sys

THEME = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'beryon-wp')

# --------------------------------------------------------------- مراحل کامپایل

RE_COMMENT = re.compile(r'\{\{--.*?--\}\}', re.S)
RE_ECHO_RAW = re.compile(r'\{!!\s*(.+?)\s*!!\}', re.S)
RE_ECHO = re.compile(r'\{\{\s*(.+?)\s*\}\}', re.S)
RE_VERBATIM = re.compile(r'@verbatim(.*?)@endverbatim', re.S)
RE_IDENT = re.compile(r'@([A-Za-z][A-Za-z0-9_]*)')

ARG_DIRECTIVES = {
    'if', 'elseif', 'unless', 'foreach', 'forelse', 'for', 'while', 'isset', 'empty',
    'error', 'endphp', 'php', 'switch', 'case', 'default', 'include', 'includeIf',
    'includeWhen', 'json', 'yield', 'stack', 'hasSection', 'sectionMissing', 'section',
    'endsection', 'extends', 'push', 'endpush', 'prepend', 'endprepend', 'once',
    'endonce', 'auth', 'endauth', 'guest', 'endguest', 'production', 'endproduction',
    'aware', 'props',
}

MAP = {
    'endswitch': '<?php endswitch; ?>',
    'endif': '<?php endif; ?>',
    'else': '<?php else: ?>',
    'endunless': '<?php endif; ?>',
    'endforeach': '<?php endforeach; ?>',
    'endfor': '<?php endfor; ?>',
    'endwhile': '<?php endwhile; ?>',
    'endforelse': '<?php endif; ?>',
    'endisset': '<?php endif; ?>',
    'endempty': '<?php endif; ?>',
    'enderror': '<?php endif; ?>',
    'endphp': '?>',
    'break': '<?php break; ?>',
    'continue': '<?php continue; ?>',
}

forelse_depth = [0]


def split_args(args):
    """جداسازی آرگومان‌ها با احترام به پرانتز/کروت (همان MiniBlade::splitArgs)."""
    parts, depth, buf, quote = [], 0, '', ''
    for char in args:
        if quote:
            buf += char
            if char == quote:
                quote = ''
            continue
        if char in '\'"':
            quote = char
            buf += char
            continue
        if char in '([':
            depth += 1
        elif char in ')]':
            depth -= 1
        if char == ',' and depth == 0:
            parts.append(buf.strip())
            buf = ''
            continue
        buf += char
    parts.append(buf.strip())
    return parts


RE_AS = re.compile(r'^(.+?)\s+as\s+(.+)$', re.S | re.I)


def render_directive(name, args):
    """همان MiniBlade::compileDirectives — خروجی PHP یک دستور."""
    if name == 'endphp':
        return '?>'
    if name == 'if':
        return '<?php if (%s): ?>' % args
    if name == 'elseif':
        return '<?php elseif (%s): ?>' % args
    if name == 'unless':
        return '<?php if (! (%s)): ?>' % args
    if name == 'foreach':
        return '<?php foreach (%s): ?>' % args
    if name == 'forelse':
        match = RE_AS.match(args)
        if match:
            listing, loop = match.group(1).strip(), match.group(2).strip()
        else:
            parts = split_args(args)
            if len(parts) < 3:
                return '<?php foreach (%s): ?>' % args
            listing, loop = parts[2], '%s => %s' % (parts[0], parts[1])
        forelse_depth[0] += 1
        tmp = '$__beryonFe%d' % forelse_depth[0]
        return ('<?php %s = %s; if (is_array(%s) && %s !== []): foreach (%s as %s): ?>'
                % (tmp, listing, tmp, tmp, tmp, loop))
    if name == 'empty':
        return '<?php if (empty(%s)): ?>' % args if args else '<?php endforeach; else: ?>'
    if name == 'error':
        return '<?php if (false): ?>'
    if name == 'for':
        return '<?php for (%s): ?>' % args
    if name == 'while':
        return '<?php while (%s): ?>' % args
    if name == 'isset':
        return '<?php if (isset(%s)): ?>' % args
    if name == 'php':
        return '<?php ' if not args else '<?php %s; ?>' % args
    if name in ('include', 'includeIf'):
        parts = split_args(args)
        view = (parts[0] if parts else '').strip('\'"')
        data = parts[1] if len(parts) > 1 else '[]'
        return '<?php echo \\App\\MiniBlade::render(%s, (array) (%s)); ?>' % (repr(view), data)
    if name == 'includeWhen':
        parts = split_args(args)
        cond = parts[0] if parts else 'false'
        view = (parts[1] if len(parts) > 1 else '').strip('\'"')
        data = parts[2] if len(parts) > 2 else '[]'
        return '<?php if (%s) { echo \\App\\MiniBlade::render(%s, (array) (%s)); } ?>' % (cond, repr(view), data)
    if name == 'json':
        return '<?php echo wp_json_encode(%s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>' % args
    if name in ('yield', 'stack'):
        parts = split_args(args)
        return "<?php echo \\App\\MiniBlade::section((string) (%s ?? '')); ?>" % (parts[0] if parts else "''")
    if name in ('hasSection', 'sectionMissing'):
        parts = split_args(args)
        neg = '! ' if name == 'sectionMissing' else ''
        return "<?php if (%s\\App\\MiniBlade::has((string) (%s ?? ''))): ?>" % (neg, parts[0] if parts else "''")
    if name == 'section':
        parts = split_args(args)
        if len(parts) > 1:
            return '<?php \\App\\MiniBlade::setSection(%s, %s); ?>' % (parts[0], parts[1])
        return '<?php ob_start(); \\App\\MiniBlade::startSection(%s); ?>' % (parts[0] if parts else "''")
    if name == 'endsection':
        return '<?php \\App\\MiniBlade::stopSection((string) ob_get_clean()); ?>'
    if name == 'extends':
        parts = split_args(args)
        return '<?php \\App\\MiniBlade::extend(%s); ?>' % (parts[0] if parts else "''")
    if name == 'push':
        return '<?php ob_start(); \\App\\MiniBlade::startPush(%s); ?>' % args
    if name == 'prepend':
        return '<?php ob_start(); \\App\\MiniBlade::startPush(%s, true); ?>' % args
    if name in ('endpush', 'endprepend'):
        return '<?php \\App\\MiniBlade::stopPush((string) ob_get_clean()); ?>'
    if name == 'once':
        return '<?php $__once = md5(__FILE__ . __LINE__); if (! \\App\\MiniBlade::once($__once)): ?>'
    if name == 'endonce':
        return '<?php \\App\\MiniBlade::endOnce(); endif; ?>'
    if name == 'auth':
        return '<?php if (is_user_logged_in()): ?>'
    if name == 'guest':
        return '<?php if (! is_user_logged_in()): ?>'
    if name in ('endauth', 'endguest', 'endproduction'):
        return '<?php endif; ?>'
    if name in ('production', 'aware'):
        return '<?php if (true): ?>'
    if name == 'switch':
        return '<?php switch (%s): ?>' % args
    if name == 'case':
        return '<?php case %s: ?>' % args
    if name == 'default':
        return '<?php default: ?>'
    if name == 'props':
        return ('<?php foreach ((array) (%s) as $__k => $__v) { if (! isset($$__k)) { $$__k = $__v; } } '
                'unset($__k, $__v); ?>' % (args or '[]'))
    return '@' + name + ('(' + args + ')' if args else '')


def scan_directives(source):
    """اسکن خطی دستورهای Blade با شمارش عمق پرانتز (معادل regex بازگشتی PHP)."""
    out, i, n = [], 0, len(source)
    while i < n:
        if source[i] == '@':
            match = RE_IDENT.match(source, i)
            if match and match.group(1) in ARG_DIRECTIVES:
                name = match.group(1)
                j = k = match.end()
                while k < n and source[k] in ' \t':
                    k += 1
                args = ''
                if k < n and source[k] == '(':
                    depth, quote, p = 0, '', k
                    while p < n:
                        char = source[p]
                        if quote:
                            if char == '\\':
                                p += 2
                                continue
                            if char == quote:
                                quote = ''
                        elif char in '\'"':
                            quote = char
                        elif char == '(':
                            depth += 1
                        elif char == ')':
                            depth -= 1
                            if depth == 0:
                                p += 1
                                break
                        p += 1
                    args = source[k + 1:p - 1].strip()
                    j = p
                out.append(render_directive(name, args))
                i = j
                continue
        out.append(source[i])
        i += 1
    return ''.join(out)


def compile_components(source):
    """<x-name … /> → فراخوانی MiniBlade::component()."""
    pattern = re.compile(r'<x-([a-z0-9\-_.]+)((?:\s+[^>]*?)?)(/?)>', re.S)

    def repl(match):
        name = match.group(1).replace('-', '.').replace('_', '.')
        pairs = []
        for attr in re.finditer(r'(?::|x-bind:)?([@\w:.\-]+)\s*(?:=\s*"([^"]*)")?', match.group(2) or ''):
            key = re.sub(r'[^A-Za-z0-9_]', '_', attr.group(1).lstrip(':'))
            value = attr.group(2)
            pairs.append("'%s' => %s" % (key, 'true' if value is None else value))
        return '<?php echo \\App\\MiniBlade::component(%s, [%s]); ?>' % (repr(name), ', '.join(pairs))

    return pattern.sub(repl, source)


def compile_source(source):
    """بازتولید MiniBlade::compile()."""
    forelse_depth[0] = 0
    verbatim = {}

    def keep(match):
        key = '@@BERYON_VERBATIM_%d@@' % len(verbatim)
        verbatim[key] = match.group(1)
        return key

    source = RE_VERBATIM.sub(keep, source)
    source = RE_COMMENT.sub('', source)
    source = compile_components(source)
    source = scan_directives(source)
    for directive, php in MAP.items():
        source = re.sub(r'@' + directive + r'\b', php, source)
    source = RE_ECHO_RAW.sub(lambda m: '<?php echo %s; ?>' % m.group(1), source)
    source = RE_ECHO.sub(lambda m: '<?php echo esc_html((string) (%s)); ?>' % m.group(1), source)
    for key, raw in verbatim.items():
        source = source.replace(key, raw)
    return source


# --------------------------------------------------------------- تحلیل خروجی PHP

def php_blocks(source):
    """استخراج بلوک‌های PHP + خطاهای توازن برچسب."""
    blocks, errors = [], []
    i, n, in_php, start = 0, len(source), False, 0
    while i < n:
        if not in_php:
            open_at = source.find('<?php', i)
            stray = source.find('?>', i)
            if stray >= 0 and (open_at < 0 or stray < open_at):
                errors.append('» ?> بدون <?php متناظر')
                i = stray + 2
                continue
            if open_at < 0:
                break
            in_php, start, i = True, open_at + 5, open_at + 5
        else:
            close_at = source.find('?>', i)
            nested = source.find('<?php', i)
            if nested >= 0 and (close_at < 0 or nested < close_at):
                errors.append('» <?php تودرتو داخل بلوک PHP (نشانه @endphp کامپایل‌نشده)')
                i = start = nested + 5
                continue
            if close_at < 0:
                blocks.append(source[start:])
                errors.append('» بلوک PHP بدون ?> پایانی تا انتهای فایل')
                break
            blocks.append(source[start:close_at])
            in_php, i = False, close_at + 2
    return blocks, errors


def strip_literals(code):
    """حذف رشته‌ها و کامنت‌ها تا شمارش ساختارها دقیق باشد."""
    out, i, n = [], 0, len(code)
    while i < n:
        char = code[i]
        if char in '\'"':
            quote, i = char, i + 1
            while i < n:
                if code[i] == '\\':
                    i += 2
                    continue
                if code[i] == quote:
                    i += 1
                    break
                i += 1
            out.append('""')
            continue
        if char == '#':
            while i < n and code[i] != '\n':
                i += 1
            continue
        if char == '/' and i + 1 < n and code[i + 1] == '/':
            while i < n and code[i] != '\n':
                i += 1
            continue
        if char == '/' and i + 1 < n and code[i + 1] == '*':
            end = code.find('*/', i + 2)
            i = n if end < 0 else end + 2
            continue
        out.append(char)
        i += 1
    return ''.join(out)


CLOSERS = {'endif': 'if', 'endforeach': 'foreach', 'endfor': 'for', 'endwhile': 'while', 'endswitch': 'switch'}
RE_TOKEN = re.compile(
    r'\b(if|foreach|for|while|switch)\s*\('                     # 1 opener
    r'|\b(endif|endforeach|endfor|endwhile|endswitch)\s*;'      # 2 closer
    r'|\b(else|elseif)\b'                                       # 3 branch
    r'|\b(case|default)\b'                                      # 4 switch branch
    r'|([{}])'                                                  # 5 brace
    r'|\b(ob_start|ob_get_clean|ob_end_clean)\s*\('             # 6 buffer
)


def match_paren(code, start):
    """موقعیت ) متناظر با ( در start."""
    depth, i = 0, start
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
    while i < len(code) and code[i] in ' \t\r\n':
        i += 1
    return code[i] if i < len(code) else ''


def check_structures(blocks):
    """توازن ساختارهای کنترلی با پشته — تفکیک نحو جایگزین (:) از آکولادی ({})."""
    errors, stack, ob = [], [], 0

    for block in blocks:
        code = strip_literals(block)
        consumed = set()   # آکولادهایی که بخشی از «if (…) {» شمرده شده‌اند
        for match in RE_TOKEN.finditer(code):
            opener, closer, branch, case, brace, buffer = match.groups()

            if buffer:
                ob += 1 if buffer == 'ob_start' else -1
                continue
            if opener:
                close = match_paren(code, code.find('(', match.start()))
                if close < 0:
                    errors.append('پرانتز نابسته در %s' % opener)
                    continue
                following = next_meaningful(code, close + 1)
                if following == ':':
                    stack.append(('alt', opener))
                elif following == '{':
                    stack.append(('brace', opener))
                    consumed.add(code.find('{', close + 1))
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
                if match.start() in consumed:
                    continue
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


RE_RELATIVE_CLASS = re.compile(r'(?<![\w\\$\'"])(App|WP|WC|Roots)\\([A-Za-z_][\w\\]*)')
RE_BARE_WP_CLASS = re.compile(r'(?<![\w\\$\'"])\b(WP_[A-Z][A-Za-z_]*|WC_[A-Z][A-Za-z_]*)\b')
RE_LEFTOVER = re.compile(r'(?<![\w.@\-])@([A-Za-z][A-Za-z0-9_\-]*)')
CSS_AT = {'media', 'import', 'font-face', 'keyframes', 'supports', 'charset', 'page', 'property', 'container'}


HTML_HELPERS = set()
RE_ESCAPED_TAG = re.compile(r"'<[a-zA-Z!/]")


def load_html_helpers(theme):
    """تابع‌هایی که HTML تولید می‌کنند (از روی بدنه‌شان در app/helpers.php)."""
    path = os.path.join(theme, 'app', 'helpers.php')
    names = set()

    if not os.path.isfile(path):
        return names

    with io.open(path, encoding='utf-8') as handle:
        source = handle.read()

    for match in re.finditer(r'\nfunction (beryon_\w+)\([^)]*\)[^{]*\{(.*?)\n\}', source, re.S):
        name, body = match.group(1), match.group(2)
        if (re.search(r"return\s+(?:\([^)]*\)\s*)?'<[a-zA-Z!/]", body)
                or re.search(r'return\s+\$html\b', body)
                or re.search(r"echo\s+'<[a-zA-Z!/]", body)
                or re.search(r'echo\s+\$html\b', body)):
            names.add(name)

    return names


def check_escaping(source):
    """HTML/کاراکترهای فرار‌شده که اشتباهاً داخل {{ }} چاپ می‌شوند."""
    errors = []
    body = RE_COMMENT.sub('', source)

    for match in re.finditer(r'\{\{(.*?)\}\}', body, re.S):
        expr = match.group(1)
        line = body[:match.start()].count('\n') + 1

        for name in sorted(HTML_HELPERS):
            if re.search(r'\b%s\s*\(' % name, expr):
                errors.append('» خط %d: تابع HTML‌ساز %s() داخل {{ }} — باید {!! !!} باشد' % (line, name))

        if re.search(r'\bberyon_amp\s*\(', expr):
            errors.append('» خط %d: beryon_amp() داخل {{ }} دو بار فرار می‌شود (& → &amp;amp;) — باید {!! !!} باشد' % line)

        if RE_ESCAPED_TAG.search(expr):
            errors.append('» خط %d: رشته HTML داخل {{ }} به‌صورت متن چاپ می‌شود — باید {!! !!} باشد' % line)

    return errors


def check_view(source):
    errors, warnings = [], []
    compiled = compile_source(source)

    errors += check_escaping(source)

    blocks, tag_errors = php_blocks(compiled)
    errors += tag_errors
    errors += check_structures(blocks)

    html = re.sub(r'<\?php.*?\?>', '\n', compiled, flags=re.S)
    for match in RE_LEFTOVER.finditer(html):
        name = match.group(1)
        if name.lower() in CSS_AT or name.startswith('BERYON_VERBATIM'):
            continue
        errors.append('» دستور کامپایل‌نشده: @%s' % name)

    for block in blocks:
        code = strip_literals(block)
        for match in RE_RELATIVE_CLASS.finditer(code):
            warnings.append('نام کلاس نسبی «%s\\%s» (در مسیر eval → %s\\%s\\%s)'
                            % (match.group(1), match.group(2), match.group(1), match.group(1), match.group(2)))
        for match in RE_BARE_WP_CLASS.finditer(code):
            warnings.append('«%s» بدون \\ پیشرو' % match.group(1))
        if re.search(r'\{\{|\}\}', code):
            errors.append('» «{{ }}» داخل بلوک PHP')

    opens = len(re.findall(r'@php\b(?!\s*\()', source))
    ends = len(re.findall(r'@endphp\b', source))
    if opens != ends:
        errors.append('» @php بلوکی (%d) با @endphp (%d) جفت نیست' % (opens, ends))

    return errors, warnings, compiled


def main():
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    strict = '--strict' in sys.argv
    theme = args[0] if args else THEME
    views_dir = os.path.join(theme, 'resources', 'views')
    if not os.path.isdir(views_dir):
        raise SystemExit('پوشه نماها پیدا نشد: %s' % views_dir)

    global HTML_HELPERS
    HTML_HELPERS = load_html_helpers(theme)

    files = []
    for root, dirs, names in os.walk(views_dir):
        dirs.sort()
        for name in sorted(names):
            if name.endswith('.blade.php'):
                files.append(os.path.join(root, name))

    total_errors = total_warnings = 0
    for path in files:
        with io.open(path, encoding='utf-8') as handle:
            source = handle.read()
        errors, warnings, compiled = check_view(source)
        relative = os.path.relpath(path, views_dir)
        status = 'FAIL' if errors else ('WARN' if warnings else 'OK  ')
        print('%s %-46s %6d → %6d B' % (status, relative, len(source.encode('utf-8')), len(compiled.encode('utf-8'))))
        for item in errors:
            print('        ✗ %s' % item)
        for item in sorted(set(warnings)):
            print('        ! %s' % item)
        total_errors += len(errors)
        total_warnings += len(set(warnings))

    print('-' * 76)
    print('نماها: %d | خطا: %d | هشدار: %d' % (len(files), total_errors, total_warnings))
    if total_errors or (strict and total_warnings):
        return 1
    print('همه نماها بدون خطا کامپایل می‌شوند ✔')
    return 0


if __name__ == '__main__':
    sys.exit(main())
