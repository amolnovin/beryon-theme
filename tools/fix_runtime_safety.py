#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
بسته اصلاحی دوم — حذف آخرین مسیرهای «صفحه سفید خاموش».

  A) helpers.php : beryon_view() تابع ناموجود App\\view() را صدا می‌زد
                   (فقط Acorn::view وجود دارد) → fatal در حلقه ووکامرس.
                   همچنین match(true) (فقط PHP 8) به if-chain تبدیل شد.
  B) Template.php: render() داخل try/catch و صفحه اضطراری معتبر HTML
                   (با wp_head/wp_footer و جزئیات برای مدیر) — دیگر هرگز
                   خروجی خالی برنمی‌گردد.
  C) functions.php: polyfill برای str_contains/str_starts_with/str_ends_with
                   (سازگاری PHP 7.4)، بوت مقاوم ماژول‌ها با try/catch،
                   و هشدار نسخه PHP.
  D) Acorn.php   : flush() حافظه نهان MiniBlade را هم پاک می‌کند.
  E) style.css   : Requires PHP: 7.4
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
    # اجرای دوباره بدون خطا: اگر لنگر نیست ولی نتیجه حاضر است، عبور می‌کنیم.
    if source.count(old) == 0:
        if new in source:
            print('   (skip [%s] — پیش‌تر اعمال شده)' % label)
            return source
        raise SystemExit('ANCHOR FAIL [%s] count=0' % label)
    if source.count(old) != 1:
        raise SystemExit('ANCHOR FAIL [%s] count=%d' % (label, source.count(old)))
    return source.replace(old, new)


# ------------------------------------------------------------------ A) helpers.php
def patch_helpers():
    path = os.path.join(ROOT, 'app', 'helpers.php')
    source = read(path)

    source = sub(
        source,
        "    return App\\view($view, $data);",
        "    return \\App\\Acorn::view($view, $data);",
        'beryon_view',
    )

    source = sub(
        source,
        "    return match (true) {\n"
        "        str_contains($template, 'contact')    => 'contact',\n"
        "        str_contains($template, 'feedback')   => 'feedback',\n"
        "        str_contains($template, 'catalog')    => 'catalog',\n"
        "        str_contains($template, 'about'),\n"
        "        str_contains($template, 'ceo'),\n"
        "        str_contains($template, 'org'),\n"
        "        str_contains($template, 'cert')       => 'about',\n"
        "        default                               => '',\n"
        "    };\n",
        "    // به‌جای match (فقط PHP 8) از زنجیره if استفاده می‌شود تا قالب روی\n"
        "    // PHP 7.4 هم کار کند.\n"
        "    if (str_contains($template, 'contact')) {\n"
        "        return 'contact';\n"
        "    }\n"
        "\n"
        "    if (str_contains($template, 'feedback')) {\n"
        "        return 'feedback';\n"
        "    }\n"
        "\n"
        "    if (str_contains($template, 'catalog')) {\n"
        "        return 'catalog';\n"
        "    }\n"
        "\n"
        "    foreach (['about', 'ceo', 'org', 'cert'] as $beryon_hint) {\n"
        "        if (str_contains($template, $beryon_hint)) {\n"
        "            return 'about';\n"
        "        }\n"
        "    }\n"
        "\n"
        "    return '';\n",
        'match',
    )

    write(path, source)
    print('A) helpers.php: beryon_view + match → if-chain ✔')


# ----------------------------------------------------------------- B) Template.php
RENDER_OLD = """    public static function render(): string
    {
        $view = self::resolve();

        if ($view === '') {
            $view = 'index';
        }

        /**
         * فیلتر نام نمای نهایی.
         *
         * @param string $view نام نما
         */
        $view = (string) \\apply_filters('beryon/template', $view);

        return Acorn::page($view, self::data($view));
    }
"""

RENDER_NEW = """    public static function render(): string
    {
        $view = 'index';

        try {
            $view = self::resolve();

            if ($view === '') {
                $view = 'index';
            }

            /**
             * فیلتر نام نمای نهایی.
             *
             * @param string $view نام نما
             */
            $view = (string) \\apply_filters('beryon/template', $view);

            $html = Acorn::page($view, self::data($view));

            if (trim($html) === '') {
                throw new \\RuntimeException('خروجی نما «' . $view . '» خالی بود.');
            }

            return $html;
        } catch (\\Throwable $error) {
            error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions
                sprintf('[beryon] رندر نما «%s» ناموفق: %s (%s:%d)', $view, $error->getMessage(), $error->getFile(), $error->getLine())
            );

            return self::emergency($error, $view);
        }
    }

    /**
     * صفحه اضطراری: به‌جای صفحه سفید، یک سند معتبر HTML با پیام خوانا.
     *
     * جزئیات فنی فقط برای مدیر سایت یا وقتی WP_DEBUG روشن است نمایش داده
     * می‌شود؛ برای بازدیدکننده پیام کوتاه و محترمانه است. هیچ‌وقت رشته خالی
     * برنمی‌گردد.
     *
     * @param \\Throwable $error خطا
     * @param string     $view  نمای درخواستی
     * @return string
     */
    private static function emergency(\\Throwable $error, string $view): string
    {
        $debug = (defined('WP_DEBUG') && WP_DEBUG)
            || (function_exists('current_user_can') && current_user_can('manage_options'));

        if (! headers_sent() && function_exists('status_header')) {
            \\status_header(500);
            \\nocache_headers();
        }

        $title = function_exists('get_bloginfo') ? (string) \\get_bloginfo('name') : 'Beryon';

        $head = '';
        $foot = '';

        if (! self::$emergency) {
            self::$emergency = true;

            try {
                if (function_exists('wp_head')) {
                    ob_start();
                    \\wp_head();
                    $head = (string) ob_get_clean();
                }
            } catch (\\Throwable $ignored) {
                unset($ignored);
            }

            try {
                if (function_exists('wp_footer')) {
                    ob_start();
                    \\wp_footer();
                    $foot = (string) ob_get_clean();
                }
            } catch (\\Throwable $ignored) {
                unset($ignored);
            }
        }

        $details = '';

        if ($debug) {
            $details = '<details open style="margin-top:18px"><summary style="cursor:pointer;font-weight:700">'
                . 'جزئیات فنی (فقط برای مدیر)</summary>'
                . '<pre dir="ltr" style="text-align:left;direction:ltr;background:#fff;border:1px solid #e6d9d2;'
                . 'border-radius:10px;padding:12px;overflow:auto;font:12px/1.8 monospace;white-space:pre-wrap">'
                . esc_html($error->getMessage() . "\\n" . $error->getFile() . ':' . $error->getLine()
                    . "\\nview: " . $view . "\\n\\n" . $error->getTraceAsString())
                . '</pre></details>';
        }

        $css = 'body{margin:0;background:#fffaf7;color:#181a1e;font-family:Vazirmatn,Tahoma,sans-serif;'
            . 'direction:rtl;text-align:center}.bx{max-width:640px;margin:12vh auto;padding:28px 24px;'
            . 'background:#fff;border:1px solid #f1e3dc;border-radius:20px;box-shadow:0 18px 44px rgba(24,26,30,.08)}'
            . 'h1{font-size:22px;margin:0 0 10px;color:#a4121b}p{margin:0 0 8px;line-height:2;color:#3f424a;font-size:15px}'
            . 'code{background:#fdeceb;color:#a4121b;padding:2px 8px;border-radius:8px;font-size:13px}'
            . 'a.btn{display:inline-block;margin-top:16px;padding:11px 22px;background:#d5232c;color:#fff;'
            . 'border-radius:12px;text-decoration:none;font-weight:700}';

        return '<!DOCTYPE html>' . "\\n"
            . '<html lang="fa" dir="rtl">' . "\\n"
            . '<head>' . "\n<meta charset=\"utf-8\">\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<meta name="robots" content="noindex,nofollow">' . "\n"
            . '<title>' . esc_html($title) . '</title>' . "\n"
            . '<style>' . $css . '</style>' . "\n"
            . $head
            . '</head>' . "\n"
            . '<body class="beryon-error">' . "\n"
            . '<main class="bx">' . "\n"
            . '<h1>' . esc_html__('صفحه قابل نمایش نیست', 'beryon') . '</h1>' . "\n"
            . '<p>' . esc_html__('رندر نمای قالب با خطا مواجه شد. اگر مدیر سایت هستید، جزئیات فنی در ادامه و در فایل error_log سرور ثبت شده است.', 'beryon') . '</p>' . "\n"
            . '<p>' . esc_html__('نمای درخواستی:', 'beryon') . ' <code>' . esc_html($view) . '</code></p>' . "\n"
            . '<a class="btn" href="' . esc_url(function_exists('home_url') ? \\home_url('/') : '/') . '">'
            . esc_html__('بازگشت به صفحه اصلی', 'beryon') . '</a>' . "\n"
            . $details . "\n"
            . '</main>' . "\n"
            . $foot
            . '</body>' . "\n"
            . '</html>' . "\n";
    }
"""


def patch_template():
    path = os.path.join(ROOT, 'app', 'Template.php')
    source = read(path)
    source = sub(source, RENDER_OLD, RENDER_NEW, 'render')

    source = sub(
        source,
        "    /**\n     * نام نمای انتخاب‌شده.\n",
        "    /**\n     * آیا صفحه اضطراری یک‌بار ساخته شده است؟ (جلوگیری از فراخوانی دوباره wp_head)\n     *\n     * @var bool\n     */\n    private static $emergency = false;\n\n    /**\n     * نام نمای انتخاب‌شده.\n",
        'prop',
    )

    write(path, source)
    print('B) Template.php: render مقاوم + صفحه اضطراری ✔')


# --------------------------------------------------------------- C) functions.php
POLYFILL = """/* ------------------------------------------------------------------ *
 * ۰) سازگاری PHP — چندتایی‌های (polyfill) توابع رشته‌ای PHP 8
 * ------------------------------------------------------------------ */

// قالب روی PHP 7.4 هم باید کار کند؛ این سه تابع در PHP 8 اضافه شده‌اند.
if (! function_exists('str_contains')) {
    /**
     * آیا رشته شامل زیررشته است؟ (polyfill)
     *
     * @param string $haystack رشته
     * @param string $needle   سوزن
     * @return bool
     */
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (! function_exists('str_starts_with')) {
    /**
     * آیا رشته با پیشوند شروع می‌شود؟ (polyfill)
     *
     * @param string $haystack رشته
     * @param string $needle   پیشوند
     * @return bool
     */
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (! function_exists('str_ends_with')) {
    /**
     * آیا رشته با پسوند تمام می‌شود؟ (polyfill)
     *
     * @param string $haystack رشته
     * @param string $needle   پسوند
     * @return bool
     */
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

// حداقل نسخه PHP قالب ۷.۴ است (fn() و typed properties). در نسخه‌های قدیمی‌تر
// به‌جای صفحه سفید، هشدار خوانا در پیشخوان نمایش داده می‌شود.
if (PHP_VERSION_ID < 70400) {
    add_action(
        'admin_notices',
        static function (): void {
            echo '<div class="notice notice-error"><p><strong>'
                . esc_html__('قالب بریون به PHP ۷.۴ یا بالاتر نیاز دارد.', 'beryon')
                . '</strong> '
                . esc_html__('نسخه فعلی: ', 'beryon')
                . '<code>' . esc_html(PHP_VERSION) . '</code>. '
                . esc_html__('لطفاً نسخه PHP سایت را از پیشخوان میزبانی ارتقا دهید.', 'beryon')
                . '</p></div>';
        }
    );
}

"""

BOOT_OLD = """foreach ($beryon_modules as $beryon_module) {
    if (class_exists($beryon_module) && method_exists($beryon_module, 'boot')) {
        $beryon_module::boot();
    }
}
"""

BOOT_NEW = """foreach ($beryon_modules as $beryon_module) {
    if (! class_exists($beryon_module) || ! method_exists($beryon_module, 'boot')) {
        continue;
    }

    // بوت مقاوم: خطای یک ماژول نباید کل سایت را سفید کند.
    try {
        $beryon_module::boot();
    } catch (Throwable $beryon_boot_error) {
        error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions
            '[beryon] boot() در ' . $beryon_module . ' ناموفق: ' . $beryon_boot_error->getMessage()
        );

        if (defined('WP_DEBUG') && WP_DEBUG && is_admin()) {
            add_action(
                'admin_notices',
                static function () use ($beryon_module, $beryon_boot_error): void {
                    echo '<div class="notice notice-error"><p>'
                        . esc_html__('خطا در راه‌اندازی ماژول قالب بریون:', 'beryon')
                        . ' <code>' . esc_html((string) $beryon_module) . '</code> — '
                        . esc_html($beryon_boot_error->getMessage())
                        . '</p></div>';
                }
            );
        }
    }
}
"""


def patch_functions():
    path = os.path.join(ROOT, 'functions.php')
    source = read(path)

    source = sub(
        source,
        "foreach ($beryon_modules as $beryon_module) {\n"
        "    if (class_exists($beryon_module) && method_exists($beryon_module, 'boot')) {\n"
        "        $beryon_module::boot();\n"
        "    }\n"
        "}\n",
        BOOT_NEW,
        'boot',
    )

    # polyfill دقیقاً بعد از گارد ABSPATH
    anchor = "defined('ABSPATH') || exit;\n"
    if source.count(anchor) != 1:
        raise SystemExit('لنگر ABSPATH در functions.php پیدا نشد')
    source = source.replace(anchor, anchor + '\n' + POLYFILL, 1)

    write(path, source)
    print('C) functions.php: polyfill + بوت مقاوم + هشدار PHP ✔')


# -------------------------------------------------------------------- D) Acorn.php
def patch_acorn():
    path = os.path.join(ROOT, 'app', 'Acorn.php')
    source = read(path)

    source = sub(
        source,
        "        MiniBlade::flushState();\n"
        "\n"
        "        $cache = BERYON_PATH . '/storage/framework/views';\n",
        "        MiniBlade::flushState();\n"
        "        MiniBlade::clearCache();\n"
        "\n"
        "        $cache = BERYON_PATH . '/storage/framework/views';\n",
        'flush',
    )

    write(path, source)
    print('D) Acorn.php: flush → MiniBlade::clearCache() ✔')


# ------------------------------------------------------------------- E) style.css
def patch_style():
    path = os.path.join(ROOT, 'style.css')
    source = read(path)
    source = sub(source, 'Requires PHP: 8.1', 'Requires PHP: 7.4', 'requires-php')
    write(path, source)
    print('E) style.css: Requires PHP 7.4 ✔')


def main():
    patch_helpers()
    patch_template()
    patch_functions()
    patch_acorn()
    patch_style()
    return 0


if __name__ == '__main__':
    sys.exit(main())
