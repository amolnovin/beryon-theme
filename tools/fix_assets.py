#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
اصلاح app/Assets.php — علت «بی‌استایل بودن» سایت و پنل تنظیمات.

۱) wp_style_add_data(…, 'rtl', 'replace') باعث می‌شد وردپرس در سایت فارسی (RTL)
   به‌جای app.css فایل app-rtl.css را بارگذاری کند که وجود ندارد → ۴۰۴ → کل
   استایل سایت از بین می‌رفت. طراحی قالب ذاتاً RTL است؛ این خط حذف شد.

۲) شاخه manifest وجود فایل را بررسی نمی‌کرد؛ اگر public/assets خالی بود
   (بیلد نشده/کپی ناقص) URL همان‌جا ۴۰۴ می‌شد. حالا is_readable لازم است و
   در غیر این‌صورت به فایل خام و سپس به CSS درون‌خطی اضطراری می‌رسیم.

۳) fallback اسکریپت: resources/js/app.js یک ES module است و با تگ کلاسیک کار
   نمی‌کند؛ fallback خام به resources/js/main.js (بدون import) تغییر کرد.

۴) هوک دارایی پنل علاوه بر $hook، اسلاگ صفحه (?page=) را هم می‌پذیرد.
"""
import io
import os
import sys

PATH = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'beryon-wp', 'app', 'Assets.php')


def read(path):
    with io.open(path, encoding='utf-8') as handle:
        return handle.read()


def write(path, text):
    tmp = path + '.tmp'
    with io.open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
        handle.write(text)
    os.replace(tmp, path)


def sub(source, old, new, label):
    if source.count(old) != 1:
        raise SystemExit('ANCHOR FAIL [%s] count=%d' % (label, source.count(old)))
    return source.replace(old, new)


def main():
    source = read(PATH)
    original = source

    # ---------------------------------------------------------- ۱) enqueue سمت سایت
    source = sub(
        source,
        "        $css = self::resolve('css/app.css', 'resources/css/app.css');\n"
        "        $js  = self::resolve('js/app.js', 'resources/js/app.js');\n"
        "\n"
        "        \\wp_enqueue_style(self::STYLE, $css['url'], [], $css['version'], 'all');\n"
        "        \\wp_style_add_data(self::STYLE, 'rtl', 'replace');\n"
        "\n"
        "        \\wp_enqueue_script(self::SCRIPT, $js['url'], [], $js['version'], true);\n",
        "        // fallback خام برای اسکریپت عمداً main.js است: resources/js/app.js یک\n"
        "        // ES module با import است و با تگ کلاسیک وردپرس اجرا نمی‌شود.\n"
        "        $css = self::resolve('css/app.css', 'resources/css/app.css');\n"
        "        $js  = self::resolve('js/app.js', 'resources/js/main.js');\n"
        "\n"
        "        // نکته: طراحی قالب ذاتاً RTL است؛ wp_style_add_data(…, 'rtl', 'replace')\n"
        "        // باعث می‌شد وردپرس به‌دنبال app-rtl.css (ناموجود) بگردد و کل استایل\n"
        "        // سایت ۴۰۴ شود.\n"
        "        if ($css['path'] !== '' && is_readable($css['path'])) {\n"
        "            \\wp_enqueue_style(self::STYLE, $css['url'], [], $css['version'], 'all');\n"
        "        } else {\n"
        "            self::registerInlineStyle(self::STYLE, ['resources/css/beryon.css', 'resources/css/wp.css']);\n"
        "        }\n"
        "\n"
        "        if ($js['path'] !== '' && is_readable($js['path'])) {\n"
        "            \\wp_enqueue_script(self::SCRIPT, $js['url'], [], $js['version'], true);\n"
        "        } else {\n"
        "            self::registerInlineScript(self::SCRIPT, ['resources/js/main.js']);\n"
        "        }\n",
        'enqueue',
    )

    # ------------------------------------------------------------ ۲) فونت خودمیمان
    source = sub(
        source,
        "        if (self::font_is_local()) {\n"
        "            \\wp_enqueue_style('beryon-fonts', self::url('css/fonts.css'), [], self::version('css/fonts.css'), 'all');\n"
        "        }\n",
        "        if (self::font_is_local()) {\n"
        "            $fonts = self::resolve('css/fonts.css', 'resources/css/fonts.css');\n"
        "\n"
        "            if ($fonts['path'] !== '' && is_readable($fonts['path'])) {\n"
        "                \\wp_enqueue_style('beryon-fonts', $fonts['url'], [], $fonts['version'], 'all');\n"
        "            } else {\n"
        "                self::registerInlineStyle('beryon-fonts', ['resources/css/fonts.css']);\n"
        "            }\n"
        "        }\n",
        'fonts',
    )

    # ------------------------------------------------------------- ۳) دارایی پنل
    source = sub(
        source,
        "        if (! str_contains($hook, 'beryon')) {\n"
        "            return;\n"
        "        }\n"
        "\n"
        "        $css = self::resolve('css/admin.css', 'resources/css/admin.css');\n"
        "        $js  = self::resolve('js/admin.js', 'resources/js/admin.js');\n"
        "\n"
        "        \\wp_enqueue_style('beryon-admin', $css['url'], [], $css['version']);\n"
        "        \\wp_enqueue_script('beryon-admin', $js['url'], ['wp-color-picker'], $js['version'], true);\n",
        "        // صفحه‌های قالب با اسلاگ beryon-* ثبت می‌شوند؛ هم $hook و هم ?page= بررسی\n"
        "        // می‌شود تا در هیچ چیدمان منویی استایل پنل از دست نرود.\n"
        "        $page = isset($_GET['page']) ? (string) \\sanitize_key(\\wp_unslash($_GET['page'])) : '';\n"
        "\n"
        "        if (strpos($hook, 'beryon') === false && strpos($page, 'beryon') === false) {\n"
        "            return;\n"
        "        }\n"
        "\n"
        "        $css = self::resolve('css/admin.css', 'resources/css/admin.css');\n"
        "        $js  = self::resolve('js/admin.js', 'resources/js/admin.js');\n"
        "\n"
        "        if ($css['path'] !== '' && is_readable($css['path'])) {\n"
        "            \\wp_enqueue_style('beryon-admin', $css['url'], [], $css['version']);\n"
        "        } else {\n"
        "            self::registerInlineStyle('beryon-admin', ['resources/css/admin.css']);\n"
        "        }\n"
        "\n"
        "        if ($js['path'] !== '' && is_readable($js['path'])) {\n"
        "            \\wp_enqueue_script('beryon-admin', $js['url'], ['wp-color-picker'], $js['version'], true);\n"
        "        } else {\n"
        "            self::registerInlineScript('beryon-admin', ['resources/js/admin.js']);\n"
        "        }\n",
        'admin',
    )

    # --------------------------------------------------------- ۴) استایل ویرایشگر
    source = sub(
        source,
        "        $css = self::resolve('css/editor.css', 'resources/css/editor.css');\n"
        "\n"
        "        \\wp_enqueue_style('beryon-editor', $css['url'], [], $css['version']);\n",
        "        $css = self::resolve('css/editor.css', 'resources/css/editor.css');\n"
        "\n"
        "        if ($css['path'] !== '' && is_readable($css['path'])) {\n"
        "            \\wp_enqueue_style('beryon-editor', $css['url'], [], $css['version']);\n"
        "        } else {\n"
        "            self::registerInlineStyle('beryon-editor', ['resources/css/editor.css']);\n"
        "        }\n",
        'editor',
    )

    # --------------------------------------------------- ۵) بررسی وجود فایل در manifest
    source = sub(
        source,
        "            if (isset($manifest[$key]['file'])) {\n"
        "                $relative = ltrim((string) $manifest[$key]['file'], '/');\n"
        "                $absolute = BERYON_PATH . '/public/assets/' . $relative;\n"
        "\n"
        "                return [\n",
        "            if (isset($manifest[$key]['file'])) {\n"
        "                $relative = ltrim((string) $manifest[$key]['file'], '/');\n"
        "                $absolute = BERYON_PATH . '/public/assets/' . $relative;\n"
        "\n"
        "                // اگر خروجی بیلد واقعاً روی دیسک نباشد، به مرحله بعد می‌رویم؛\n"
        "                // در غیر این‌صورت یک ۴۰۴ خاموش کل استایل سایت را می‌گیرد.\n"
        "                if (! is_readable($absolute)) {\n"
        "                    continue;\n"
        "                }\n"
        "\n"
        "                return [\n",
        'resolve',
    )

    # ------------------------------------------------------ ۶) متدهای fallback درون‌خطی
    source = sub(
        source,
        "    /**\n"
        "     * URL یک دارایی قالب (میان‌بر).\n",
        "    /**\n"
        "     * ثبت استایل درون‌خطی اضطراری وقتی هیچ فایل CSS قابل خواندن نیست.\n"
        "     *\n"
        "     * @param string        $handle  هندل\n"
        "     * @param array<int,string> $sources فایل‌های خام (مسیر نسبت به ریشه قالب)\n"
        "     * @return void\n"
        "     */\n"
        "    private static function registerInlineStyle(string $handle, array $sources): void\n"
        "    {\n"
        "        $css = self::readSources($sources);\n"
        "\n"
        "        \\wp_register_style($handle, false, [], BERYON_VERSION); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters\n"
        "        \\wp_enqueue_style($handle);\n"
        "\n"
        "        if ($css !== '') {\n"
        "            \\wp_add_inline_style($handle, $css);\n"
        "        }\n"
        "\n"
        "        error_log('[beryon] فایل استایل ' . $handle . ' پیدا نشد؛ CSS خام به‌صورت درون‌خطی چاپ شد.'); // phpcs:ignore\n"
        "    }\n"
        "\n"
        "    /**\n"
        "     * ثبت اسکریپت درون‌خطی اضطراری وقتی هیچ فایل JS قابل خواندن نیست.\n"
        "     *\n"
        "     * @param string        $handle  هندل\n"
        "     * @param array<int,string> $sources فایل‌های خام\n"
        "     * @return void\n"
        "     */\n"
        "    private static function registerInlineScript(string $handle, array $sources): void\n"
        "    {\n"
        "        $js = self::readSources($sources);\n"
        "\n"
        "        \\wp_register_script($handle, false, [], BERYON_VERSION, true); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters\n"
        "        \\wp_enqueue_script($handle);\n"
        "\n"
        "        if ($js !== '') {\n"
        "            \\wp_add_inline_script($handle, $js);\n"
        "        }\n"
        "\n"
        "        error_log('[beryon] فایل اسکریپت ' . $handle . ' پیدا نشد؛ JS خام به‌صورت درون‌خطی چاپ شد.'); // phpcs:ignore\n"
        "    }\n"
        "\n"
        "    /**\n"
        "     * خواندن و الحاق فایل‌های خام (بدون @import/@charset).\n"
        "     *\n"
        "     * @param array<int,string> $sources مسیرها نسبت به ریشه قالب\n"
        "     * @return string\n"
        "     */\n"
        "    private static function readSources(array $sources): string\n"
        "    {\n"
        "        $out = '';\n"
        "\n"
        "        foreach ($sources as $source) {\n"
        "            $path = BERYON_PATH . '/' . ltrim((string) $source, '/');\n"
        "\n"
        "            if (! is_readable($path)) {\n"
        "                continue;\n"
        "            }\n"
        "\n"
        "            $body = (string) file_get_contents($path);\n"
        "            $body = (string) preg_replace('/@import[^;]+;/u', '', $body);\n"
        "            $body = str_replace('@charset \"UTF-8\";', '', $body);\n"
        "\n"
        "            $out .= $body . \"\\n\";\n"
        "        }\n"
        "\n"
        "        return trim($out);\n"
        "    }\n"
        "\n"
        "    /**\n"
        "     * URL یک دارایی قالب (میان‌بر).\n",
        'inline-helpers',
    )

    if source == original:
        raise SystemExit('تغییری اعمال نشد')

    write(PATH, source)
    print('Assets.php patched: %d -> %d lines' % (original.count('\n') + 1, source.count('\n') + 1))
    for token in ['registerInlineStyle', 'registerInlineScript', 'readSources',
                  "self::resolve('js/app.js', 'resources/js/main.js')",
                  'if (! is_readable($absolute)) {']:
        print('  %-46s %s' % (token[:46], 'ok' if token in source else 'MISSING!'))
    for bad in ["'rtl', 'replace'", "resolve('js/app.js', 'resources/js/app.js')"]:
        print('  removed %-38s %s' % (bad[:38], 'ok' if bad not in source else 'STILL PRESENT!'))


if __name__ == '__main__':
    sys.exit(main())
