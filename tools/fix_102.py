#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
بسته اصلاحی ۱.۰.۲ — رفع خطای Parse گزارش‌شده توسط کاربر (PHP 8.4).

گزارش کاربر (تصویر + debug.log):
  PHP Parse error: syntax error, unexpected identifier "utf", expecting ";"
  in …/app/Template.php:148

ریشه: در صفحه اضطراری Template::emergency() یک رشته دوتایی‌گیومه PHP به شکل
    "<meta charset="utf-8">"
نوشته شده بود (گیومه‌های داخلی escape نشده بودند) → رشته پس از charset بسته
می‌شد و «utf» به‌عنوان شناسهUnexpected خطای نحوی می‌ساخت. کل بلوک return با
رشته‌های تک‌گیومه و "\\n" بازنویسی شد.

همچنین دو Warning موجود در لاگ کاربر:
  Undefined array key "name" in app/Admin/SettingsPage.php:190 و :830
علت: فیلدهای تشریفاتی_schema با type=heading کلید name ندارند ولی sanitize()
و field() آن را بدون guard می‌خواندند (و sanitize حتی یک کلید خالی '' در
options ذخیره می‌کرد). هر دو با «?? ''» و skip اصلاح شدند.
"""
import io
import os
import sys

ROOT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'beryon-wp')


def read(path):
    with io.open(path, encoding='utf-8') as handle:
        return handle.read()


def write(path, text):
    tmp = path + '.tmp'
    with io.open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
        handle.write(text)
    os.replace(tmp, path)


NEW_RETURN = '''        return '<!DOCTYPE html>' . "\\n"
            . '<html lang="fa" dir="rtl">' . "\\n"
            . '<head>' . "\\n"
            . '<meta charset="utf-8">' . "\\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\\n"
            . '<meta name="robots" content="noindex,nofollow">' . "\\n"
            . '<title>' . esc_html($title) . '</title>' . "\\n"
            . '<style>' . $css . '</style>' . "\\n"
            . $head
            . '</head>' . "\\n"
            . '<body class="beryon-error">' . "\\n"
            . '<main class="bx">' . "\\n"
            . '<h1>' . esc_html__('صفحه قابل نمایش نیست', 'beryon') . '</h1>' . "\\n"
            . '<p>' . esc_html__('رندر نمای قالب با خطا مواجه شد. اگر مدیر سایت هستید، جزئیات فنی در ادامه و در فایل error_log سرور ثبت شده است.', 'beryon') . '</p>' . "\\n"
            . '<p>' . esc_html__('نمای درخواستی:', 'beryon') . ' <code>' . esc_html($view) . '</code></p>' . "\\n"
            . '<a class="btn" href="' . esc_url(function_exists('home_url') ? \\home_url('/') : '/') . '">'
            . esc_html__('بازگشت به صفحه اصلی', 'beryon') . '</a>' . "\\n"
            . $details . "\\n"
            . '</main>' . "\\n"
            . $foot
            . '</body>' . "\\n"
            . '</html>' . "\\n";
'''


def patch_template():
    path = os.path.join(ROOT, 'app', 'Template.php')
    source = read(path)

    start_marker = "        return '<!DOCTYPE html>'"
    if source.count(start_marker) != 1:
        raise SystemExit('لنگر return صفحه اضطراری پیدا نشد')

    start = source.index(start_marker)
    tail_marker = "'</html>'"
    tail = source.index(tail_marker, start)
    end = source.index('";', tail) + 2

    source = source[:start] + NEW_RETURN + source[end:]
    write(path, source)
    print('A) Template.php: بلوک return صفحه اضطراری بازنویسی شد (گیومه‌های escape‌شده) ✔')


SANITIZE_OLD = """                $group = (string) $field['group'];
                $name  = (string) $field['name'];
                $type  = (string) ($field['type'] ?? 'text');
                $raw   = $input[$group][$name] ?? null;
"""

SANITIZE_NEW = """                $group = (string) ($field['group'] ?? '');
                $name  = (string) ($field['name'] ?? '');

                // فیلدهای تشریفاتی (مانند type=heading) کلید name ندارند و
                // نباید در ذخیره‌سازی شرکت کنند (پیش‌تر کلید خالی '' می‌ساختند).
                if ($group === '' || $name === '') {
                    continue;
                }

                $type  = (string) ($field['type'] ?? 'text');
                $raw   = $input[$group][$name] ?? null;
"""

FIELD_OLD = """        $group = (string) $field['group'];
        $name  = (string) $field['name'];
        $type  = (string) ($field['type'] ?? 'text');
        $label = (string) ($field['label'] ?? $name);
        $help  = (string) ($field['help'] ?? '');
        $value = $values[$group][$name] ?? Settings::default($group . '.' . $name);
"""

FIELD_NEW = """        $group = (string) ($field['group'] ?? '');
        $name  = (string) ($field['name'] ?? '');
        $type  = (string) ($field['type'] ?? 'text');
        $label = (string) ($field['label'] ?? $name);
        $help  = (string) ($field['help'] ?? '');
"""

VALUE_OLD = """            return;
        }

        $input_name = self::OPTION . '[' . $group . '][' . $name . ']';
"""

VALUE_NEW = """            return;
        }

        $value = $values[$group][$name] ?? Settings::default($group . '.' . $name);

        $input_name = self::OPTION . '[' . $group . '][' . $name . ']';
"""


def patch_settings_page():
    path = os.path.join(ROOT, 'app', 'Admin', 'SettingsPage.php')
    source = read(path)

    for old, new, label in [
        (SANITIZE_OLD, SANITIZE_NEW, 'sanitize'),
        (FIELD_OLD, FIELD_NEW, 'field'),
        (VALUE_OLD, VALUE_NEW, 'value-after-heading'),
    ]:
        if source.count(old) == 0 and new in source:
            print('   (skip [%s])' % label)
            continue
        if source.count(old) != 1:
            raise SystemExit('ANCHOR FAIL [%s] count=%d' % (label, source.count(old)))
        source = source.replace(old, new)

    write(path, source)
    print('B) SettingsPage.php: گارد name برای فیلدهای heading + حذف کلید خالی از options ✔')


def patch_version():
    path = os.path.join(ROOT, 'style.css')
    source = read(path)
    if 'Version:     1.0.2' not in source:
        source = source.replace('Version:     1.0.1', 'Version:     1.0.2', 1)
        write(path, source)
    print('C) style.css: Version 1.0.2 ✔')


def main():
    patch_template()
    patch_settings_page()
    patch_version()
    return 0


if __name__ == '__main__':
    sys.exit(main())
