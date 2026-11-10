#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
بسته اصلاحی سوم — سخت‌سازی در برابر «صفحه سفید».

  A) functions.php : نگهبان خطای مرگبار (shutdown). اگر خطای E_ERROR/E_PARSE رخ
     دهد، به‌جای صفحه کاملاً سفید، یک بلوک تشخیصی خوانا برای مدیر (یا وقتی
     WP_DEBUG روشن است) چاپ و در error_log ثبت می‌شود.
  B) Security.php  : دستور CSP «upgrade-insecure-requests» فقط روی HTTPS ارسال
     می‌شود (روی سایت HTTP-only تمام دارایی‌ها را از کار می‌انداخت) و
     block_bad_agents به‌جای exit خام، پاسخ ۴۰۳ خوانا می‌دهد.
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


def sub(source, old, new, label):
    if source.count(old) == 0:
        if new in source:
            print('   (skip [%s] — پیش‌تر اعمال شده)' % label)
            return source
        raise SystemExit('ANCHOR FAIL [%s] count=0' % label)
    if source.count(old) != 1:
        raise SystemExit('ANCHOR FAIL [%s] count=%d' % (label, source.count(old)))
    return source.replace(old, new)


GUARD = '''
/* ------------------------------------------------------------------ *
 * ۰-۲) نگهبان خطای مرگبار — هیچ‌وقت صفحه سفید تحویل نده
 * ------------------------------------------------------------------ */

/**
 * اگر PHP با خطای مرگبار متوقف شود، به‌جای صفحه سفید یک بلوک تشخیصی چاپ می‌شود.
 *
 * جزئیات فقط برای مدیر سایت یا وقتی WP_DEBUG روشن است نمایش داده می‌شود و
 * همیشه در error_log ثبت می‌گردد.
 *
 * @return void
 */
function beryon_fatal_guard(): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $done  = true;
    $error = error_get_last();

    if (! is_array($error)) {
        return;
    }

    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if (! in_array((int) $error['type'], $fatal, true)) {
        return;
    }

    $message = sprintf('[beryon][fatal] %s in %s:%d', $error['message'], $error['file'], $error['line']);

    error_log($message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

    $show = (defined('WP_DEBUG') && WP_DEBUG)
        || (function_exists('current_user_can') && current_user_can('manage_options'));

    if (! $show) {
        return;
    }

    if (! headers_sent()) {
        status_header(500);
    }

    echo '<div dir="rtl" style="max-width:820px;margin:32px auto;padding:20px 22px;background:#fff3f3;'
        . 'border:2px solid #d5232c;border-radius:14px;font:14px/2 Tahoma,Vazirmatn,sans-serif;color:#7a1116">'
        . '<strong style="font-size:16px">خطای مرگبار در قالب بریون</strong>'
        . '<pre dir="ltr" style="text-align:left;direction:ltr;background:#fff;border:1px solid #e6d9d2;'
        . 'border-radius:10px;padding:12px;margin:12px 0 0;overflow:auto;font:12px/1.8 monospace;'
        . 'white-space:pre-wrap">' . esc_html($message) . '</pre>'
        . '<p style="margin:12px 0 0">اگر این خطا پس از نصب قالب روی داد، نسخه PHP را در پیشخوان میزبانی بررسی کنید '
        . '(حداقل ۷.۴) و در صورت نیاز <code>composer install</code> را اجرا کنید.</p>'
        . '</div>';
}

add_action('shutdown', 'beryon_fatal_guard', 9999);

'''

CSP_OLD = '''            "connect-src 'self'",
            "upgrade-insecure-requests",
        ];
'''

CSP_NEW = '''            "connect-src 'self'",
        ];

        // فقط روی HTTPS: در سایت HTTP-only این دستور همه دارایی‌ها را به HTTPS
        // ارتقا می‌دهد و اگر گواهی نصب نباشد، سایت کاملاً سفید می‌شود.
        if (\\is_ssl()) {
            $directives[] = 'upgrade-insecure-requests';
        }
'''

AGENT_OLD = '''            if ($needle !== '' && str_contains($lower, strtolower($needle))) {
                self::log_attempt($agent);

                \\status_header(403);
                exit;
            }
'''

AGENT_NEW = '''            if ($needle !== '' && str_contains($lower, strtolower($needle))) {
                self::log_attempt($agent);

                \\status_header(403);
                \\nocache_headers();

                // exit خام = صفحه سفید؛ پاسخ خوانا با کد ۴۰۳ برمی‌گردد.
                \\wp_die(
                    \\esc_html__('دسترسی غیرمجاز.', BERYON_TD),
                    403,
                    ['response' => 403, 'back_link' => false]
                );
            }
'''


def main():
    # A) functions.php
    path = os.path.join(ROOT, 'functions.php')
    source = read(path)
    anchor = "add_action('shutdown', 'beryon_fatal_guard', 9999);"

    if anchor not in source:
        marker = "/* ------------------------------------------------------------------ *\n * ۱) "
        if source.count(marker) != 1:
            # درج پیش از نخستین بخش شماره‌دار بعد از polyfill
            marker = "$beryon_modules = ["
            if source.count(marker) != 1:
                raise SystemExit('لنگر درج نگهبان در functions.php پیدا نشد')
            source = source.replace(marker, GUARD.lstrip('\n') + '\n' + marker, 1)
        else:
            source = source.replace(marker, GUARD.lstrip('\n') + '\n' + marker, 1)
        write(path, source)
    print('A) functions.php: نگهبان خطای مرگبار ✔')

    # B) Security.php
    path = os.path.join(ROOT, 'app', 'Security.php')
    source = read(path)
    source = sub(source, CSP_OLD, CSP_NEW, 'csp')
    source = sub(source, AGENT_OLD, AGENT_NEW, 'agent')
    write(path, source)
    print('B) Security.php: CSP فقط روی HTTPS + پاسخ ۴۰۳ خوانا ✔')
    return 0


if __name__ == '__main__':
    sys.exit(main())
