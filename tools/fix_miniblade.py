#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
اصلاح باگ‌های بحرانی app/MiniBlade.php (علت صفحه سفید).

  1) evaluate() پارامتر $__php را unset می‌کرد و بعد eval('?>' . $__php) را صدا
     می‌زد → همیشه رشته خالی → صفحه کاملاً سفید بدون هیچ خطایی.
  2) @endphp به '<?php ' کامپایل می‌شد (باید '?>' باشد) → ParseError.
  3) @endforelse به 'endforeach;' کامپایل می‌شد درحالی‌که @forelse ساختار
     if/foreach و @empty ساختار endforeach; else: می‌سازد → باید endif; باشد.
  4) خطاها بلعیده می‌شدند (return '' وقتی WP_DEBUG خاموش است).
  5) eval در فضای نام App اجرا می‌شد → نام کلاس نسبی App\\Products به
     App\\App\\Products تبدیل می‌شد. با حافظه نهان فایلی (include) حل می‌شود.
"""
import io
import os
import sys

PATH = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'beryon-wp', 'app', 'MiniBlade.php')


def read(path):
    with io.open(path, encoding='utf-8') as handle:
        return handle.read()


def write(path, text):
    tmp = path + '.tmp'
    with io.open(tmp, 'w', encoding='utf-8', newline='\n') as handle:
        handle.write(text)
    os.replace(tmp, path)


def replace_once(source, old, new, label):
    if source.count(old) != 1:
        raise SystemExit('ANCHOR FAIL [%s]: count=%d' % (label, source.count(old)))
    return source.replace(old, new)


def main():
    source = read(PATH)
    original = source

    # ---------------------------------------------------------------- 1) docblock
    source = replace_once(
        source,
        " *   @json($v)  @empty/@isset  @break  @continue\n",
        " *   @json($v)  @empty/@isset  @break  @continue  @stack/@hasSection\n"
        " *   @verbatim … @endverbatim  @props  @includeWhen\n"
        "\n"
        " * کد کامپایل‌شده در فایل حافظه نهان نوشته و include می‌شود (سریع‌تر از eval،\n"
        " * با شماره خط واقعی در خطاها و در فضای نام سراسری — مانند Blade واقعی). اگر\n"
        " * هیچ پوشه‌ای قابل نوشتن نباشد، از eval استفاده می‌شود. هیچ مسیری اجازه ندارد\n"
        " * رشته خالی برگرداند: خطا ثبت و به صفحه اضطراری Template منتقل می‌شود.\n",
        'docblock',
    )

    # ------------------------------------------------------- 2) خصوصیت‌های جدید کلاس
    source = replace_once(
        source,
        "    /**\n"
        "     * داده مشترک همه نماها.\n"
        "     *\n"
        "     * @var array<string,mixed>\n"
        "     */\n"
        "    private static $shared = [];\n",
        "    /**\n"
        "     * داده مشترک همه نماها.\n"
        "     *\n"
        "     * @var array<string,mixed>\n"
        "     */\n"
        "    private static $shared = [];\n"
        "\n"
        "    /**\n"
        "     * عمق @forelse (برای نام‌گذاری متغیر موقت یکتا در حلقه‌های تودرتو).\n"
        "     *\n"
        "     * @var int\n"
        "     */\n"
        "    private static $forelseDepth = 0;\n"
        "\n"
        "    /**\n"
        "     * پوشه حافظه نهان نماهای کامپایل‌شده ('' یعنی قابل نوشتن نیست).\n"
        "     *\n"
        "     * @var string|null\n"
        "     */\n"
        "    private static $cacheDir = null;\n",
        'props',
    )

    # ------------------------------------------------------------- 3) compile + cache
    source = replace_once(
        source,
        "    public static function compile(string $file): string\n"
        "    {\n"
        "        if (isset(self::$compiled[$file])) {\n"
        "            return self::$compiled[$file];\n"
        "        }\n"
        "\n"
        "        $source = (string) file_get_contents($file);\n"
        "        $source = self::compileComments($source);\n"
        "        $source = self::compileComponents($source);\n"
        "        $source = self::compileDirectives($source);\n"
        "        $source = self::compileEcho($source);\n"
        "\n"
        "        self::$compiled[$file] = $source;\n"
        "\n"
        "        return $source;\n"
        "    }\n",
        COMPILE_NEW,
        'compile',
    )

    # ------------------------------------------------------------- 4) evaluate (بحرانی)
    source = replace_once(
        source,
        "    private static function evaluate(string $__php, array $__data): string\n"
        "    {\n"
        "        $__vars = $__data;\n"
        "        unset($__data, $__php);\n"
        "\n"
        "        ob_start();\n"
        "\n"
        "        try {\n"
        "            extract($__vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract\n"
        "            eval('?>' . $__php); // phpcs:ignore Squiz.PHP.Eval\n"
        "        } catch (\\Throwable $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement\n"
        "            ob_end_clean();\n"
        "\n"
        "            if (defined('WP_DEBUG') && WP_DEBUG) {\n"
        "                return '<!-- beryon blade error: ' . esc_html($e->getMessage()) . ' -->';\n"
        "            }\n"
        "\n"
        "            return '';\n"
        "        }\n"
        "\n"
        "        return (string) ob_get_clean();\n"
        "    }\n",
        EVALUATE_NEW,
        'evaluate',
    )

    # ------------------------------------------------------------------ 5) render()
    source = replace_once(
        source,
        "        if ($file === '') {\n"
        "            return '<!-- beryon: view not found → ' . esc_html($view) . ' -->';\n"
        "        }\n"
        "\n"
        "        $php = self::compile($file);\n"
        "\n"
        "        return self::evaluate($php, array_merge(self::$shared, $data));\n",
        "        if ($file === '') {\n"
        "            error_log('[beryon] نما پیدا نشد: ' . $view); // phpcs:ignore WordPress.PHP.DevelopmentFunctions\n"
        "\n"
        "            if (function_exists('current_user_can') && current_user_can('manage_options')) {\n"
        "                return '<!-- beryon: view not found: ' . esc_html($view) . ' -->';\n"
        "            }\n"
        "\n"
        "            return '';\n"
        "        }\n"
        "\n"
        "        $php = self::compile($file);\n"
        "\n"
        "        return self::evaluate($php, array_merge(self::$shared, $data), $file);\n",
        'render',
    )

    # ------------------------------------------------- 6) نقشه دستورهای بدون آرگومان
    source = replace_once(
        source,
        "            'endforelse'   => '<?php endforeach; ?>',\n"
        "            'endisset'     => '<?php endif; ?>',\n"
        "            'endempty'     => '<?php endif; ?>',\n"
        "            'endphp'       => '<?php ',\n"
        "            'break'        => '<?php break; ?>',\n"
        "            'continue'     => '<?php continue; ?>',\n"
        "            'endphpx'      => '?>',\n"
        "        ];\n"
        "\n"
        "        $source = (string) preg_replace('/@endphp\\b/', '<?php ', $source);\n"
        "        $source = str_replace('<?php  ?>', '<?php ?>', $source);\n",
        "            'endforelse'   => '<?php endif; ?>',\n"
        "            'endisset'     => '<?php endif; ?>',\n"
        "            'endempty'     => '<?php endif; ?>',\n"
        "            'enderror'      => '<?php endif; ?>',\n"
        "            'endphp'       => '?>',\n"
        "            'break'        => '<?php break; ?>',\n"
        "            'continue'     => '<?php continue; ?>',\n"
        "        ];\n",
        'map',
    )

    source = replace_once(
        source,
        "        foreach ($map as $directive => $php) {\n"
        "            if ($directive === 'endphp' || $directive === 'endphpx') {\n"
        "                continue;\n"
        "            }\n"
        "\n"
        "            $source = (string) preg_replace('/@' . $directive . '\\b/', $php, $source);\n"
        "        }\n",
        "        foreach ($map as $directive => $php) {\n"
        "            // مقادیر $map هیچ‌کدام $ یا \\ ندارند؛ str_replace فقط احتیاط است.\n"
        "            $source = (string) preg_replace('/@' . $directive . '\\b/', str_replace('$', '\\$', $php), $source);\n"
        "        }\n",
        'map-loop',
    )

    # ---------------------------------------------- 7) فهرست دستورهای با آرگومان (regex)
    source = replace_once(
        source,
        "            '/@(if|elseif|unless|foreach|forelse|for|while|isset|empty|php|switch|case|default|include|includeIf|json|yield|section|endsection|extends|push|endpush|prepend|endprepend|once|endonce|auth|endauth|guest|endguest|production|endproduction|aware)\\b(\\s*\\(((?:[^()]|(?2))*)\\))?/',\n",
        "            '/@(if|elseif|unless|foreach|forelse|for|while|isset|empty|error|endphp|php|switch|case|default|include|includeIf|includeWhen|json|yield|stack|hasSection|sectionMissing|section|endsection|extends|push|endpush|prepend|endprepend|once|endonce|auth|endauth|guest|endguest|production|endproduction|aware|props)\\b(\\s*\\(((?:[^()]|(?2))*)\\))?/',\n",
        'regex-list',
    )

    # ------------------------------------------------------------ 8) بدنه callback
    source = replace_once(
        source,
        "                $directive = $m[1];\n"
        "                $args      = isset($m[3]) ? trim((string) $m[3]) : '';\n",
        "                $directive = $m[1];\n"
        "                $args      = isset($m[3]) ? trim((string) $m[3]) : '';\n"
        "\n"
        "                if ($directive === 'endphp') {\n"
        "                    return '?>';\n"
        "                }\n",
        'callback-head',
    )

    source = replace_once(
        source,
        "                    case 'forelse':\n"
        "                        $parts = self::splitArgs($args);\n"
        "\n"
        "                        if (count($parts) < 3) {\n"
        "                            return '<?php foreach (' . $args . '): ?>';\n"
        "                        }\n"
        "\n"
        "                        return '<?php $__empty_list = ' . $parts[2] . '; if (is_array($__empty_list) && $__empty_list !== []): foreach ($__empty_list as ' . $parts[0] . ' => ' . $parts[1] . '): ?>';\n"
        "                    case 'empty':\n"
        "                        return '<?php endforeach; else: ?>';\n",
        FORELSE_NEW,
        'callback-body',
    )

    # --------------------------------------------- 9) بازنشانی عمق forelse در ابتدای تابع
    source = replace_once(
        source,
        "    private static function compileDirectives(string $source): string\n"
        "    {\n"
        "        $map = [\n",
        "    private static function compileDirectives(string $source): string\n"
        "    {\n"
        "        self::$forelseDepth = 0;\n"
        "\n"
        "        $map = [\n",
        'directives-reset',
    )

    # ------------------------------------------------------- 10) renderWithLayout حلقه‌ای
    source = replace_once(
        source,
        "        $content = self::render($view, $data);\n"
        "        $layout  = (string) (self::$shared['__extends'] ?? '');\n"
        "\n"
        "        if ($layout === '') {\n"
        "            return $content;\n"
        "        }\n"
        "\n"
        "        unset(self::$shared['__extends']);\n"
        "\n"
        "        if (! isset(self::$sections['content']) || trim(self::$sections['content']) === '') {\n"
        "            self::$sections['content'] = $content;\n"
        "        }\n"
        "\n"
        "        return self::render($layout, $data);\n",
        LAYOUT_NEW,
        'layout-loop',
    )

    # ------------------------------------------------------------- 11) flushState کامل
    source = replace_once(
        source,
        "        self::$once         = [];\n"
        "        self::$compiled     = [];\n"
        "    }\n",
        "        self::$once         = [];\n"
        "        self::$compiled     = [];\n"
        "        self::$forelseDepth = 0;\n"
        "    }\n",
        'flush',
    )

    if source == original:
        raise SystemExit('هیچ تغییری اعمال نشد!')

    write(PATH, source)

    print('MiniBlade.php patched: %d -> %d lines' % (original.count('\n') + 1, source.count('\n') + 1))
    checks = [
        "'endphp'       => '?>'",
        "'endforelse'   => '<?php endif; ?>'",
        '$__beryonCode',
        'public static function clearCache',
        '@verbatim',
        'case \'hasSection\'',
        'self::$forelseDepth++;',
        'include $__beryonFile;',
    ]
    for token in checks:
        print('  %-42s %s' % (token[:42], 'ok' if token in source else 'MISSING!'))
    for bad in ['unset($__data, $__php);\n\n        ob_start();\n\n        try {\n            extract($__vars',
                "preg_replace('/@endphp\\b/', '<?php ', $source)",
                "'endphpx'"]:
        print('  removed %-40s %s' % (bad[:40], 'ok' if bad not in source else 'STILL PRESENT!'))


COMPILE_NEW = '''    public static function compile(string $file): string
    {
        if (isset(self::$compiled[$file])) {
            return self::$compiled[$file];
        }

        $source   = (string) file_get_contents($file);
        $verbatim = [];

        // بلوک‌های @verbatim پیش از هر کامپایلی بیرون کشیده و در پایان بازگردانده
        // می‌شوند تا {{ }} و @دستورهای داخل آن‌ها دست‌نخورده بمانند.
        $source = (string) preg_replace_callback(
            '/@verbatim(.*?)@endverbatim/s',
            /** @param array<int,string> $m */
            static function (array $m) use (&$verbatim): string {
                $key              = '@@BERYON_VERBATIM_' . count($verbatim) . '@@';
                $verbatim[$key]   = (string) $m[1];

                return $key;
            },
            $source
        );

        $source = self::compileComments($source);
        $source = self::compileComponents($source);
        $source = self::compileDirectives($source);
        $source = self::compileEcho($source);

        foreach ($verbatim as $key => $raw) {
            $source = str_replace($key, $raw, $source);
        }

        self::$compiled[$file] = $source;

        self::writeCache($file, $source);

        return $source;
    }

    /**
     * پوشه حافظه نهان نماهای کامپایل‌شده (نخستین مسیر قابل نوشتن).
     *
     * @return string مسیر مطلق یا '' وقتی هیچ‌جا قابل نوشتن نیست
     */
    public static function cacheDir(): string
    {
        if (self::$cacheDir !== null) {
            return self::$cacheDir;
        }

        $candidates = [];

        if (defined('WP_CONTENT_DIR') && is_string(WP_CONTENT_DIR) && WP_CONTENT_DIR !== '') {
            $candidates[] = WP_CONTENT_DIR . '/cache/beryon-views';
        }

        if (defined('BERYON_PATH')) {
            $candidates[] = BERYON_PATH . '/storage/framework/views';
        }

        $candidates[] = sys_get_temp_dir() . '/beryon-views';

        foreach ($candidates as $dir) {
            $dir = (string) $dir;

            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            }

            if (! is_dir($dir)) {
                continue;
            }

            $probe = $dir . '/.beryon-writable';

            if (@file_put_contents($probe, '1') !== false) {
                @unlink($probe);

                self::$cacheDir = $dir;

                return $dir;
            }
        }

        self::$cacheDir = '';

        return '';
    }

    /**
     * مسیر فایل حافظه نهان یک نما (کلید: مسیر + زمان ویرایش).
     *
     * @param string $file مسیر فایل Blade
     * @return string مسیر مطلق یا ''
     */
    public static function cacheFile(string $file): string
    {
        $dir = self::cacheDir();

        if ($dir === '') {
            return '';
        }

        return $dir . '/' . md5($file . '|' . (int) @filemtime($file)) . '.php';
    }

    /**
     * نوشتن کد کامپایل‌شده در حافظه نهان (اتمیک: tmp + rename).
     *
     * @param string $file   منبع Blade
     * @param string $source کد کامپایل‌شده
     * @return void
     */
    private static function writeCache(string $file, string $source): void
    {
        $cache = self::cacheFile($file);

        if ($cache === '' || is_readable($cache)) {
            return;
        }

        $body = "<?php\\n// نمای کامپایل‌شده توسط MiniBlade قالب بریون — منبع: "
            . str_replace(['*/', "\\n"], ['', ' '], $file)
            . "\\n?>" . $source;

        $tmp = $cache . '.' . getmypid() . '.tmp';

        if (@file_put_contents($tmp, $body, LOCK_EX) !== false) {
            @chmod($tmp, 0664);

            if (! @rename($tmp, $cache)) {
                @unlink($tmp);
            }
        }
    }

    /**
     * پاک‌سازی حافظه نهان نماهای کامپایل‌شده.
     *
     * @return int تعداد فایل‌های حذف‌شده
     */
    public static function clearCache(): int
    {
        self::$compiled = [];

        $dirs    = [self::cacheDir()];
        $removed = 0;

        if (defined('BERYON_PATH')) {
            $dirs[] = BERYON_PATH . '/storage/framework/views';
        }

        foreach ($dirs as $dir) {
            if ($dir === '' || ! is_dir($dir)) {
                continue;
            }

            foreach ((array) glob($dir . '/*.php') as $file) {
                if (@unlink((string) $file)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }
'''

EVALUATE_NEW = '''    private static function evaluate(string $__php, array $__data, string $__source = ''): string
    {
        // کد کامپایل‌شده در متغیری با نام غیرقابل‌تصادم نگه داشته می‌شود؛ نسخه
        // پیشین همین متغیر را unset می‌کرد و در نتیجه همیشه خروجی خالی می‌داد.
        $__beryonCode = $__php;
        $__beryonVars = $__data;
        $__beryonFile = $__source !== '' ? self::cacheFile($__source) : '';

        unset($__data, $__php, $__source);

        ob_start();

        try {
            extract($__beryonVars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract

            if ($__beryonFile !== '' && is_readable($__beryonFile)) {
                // include در فضای نام سراسری اجرا می‌شود (مانند Blade واقعی)؛
                // در نتیجه نام‌های کلاس نسبی مثل App\\Products درست حل می‌شوند.
                include $__beryonFile;
            } else {
                eval('?>' . $__beryonCode); // phpcs:ignore Squiz.PHP.Eval
            }
        } catch (\\Throwable $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            self::reportError($e, $__beryonFile !== '' ? $__beryonFile : 'eval');

            throw new \\RuntimeException('MiniBlade: ' . $e->getMessage(), 0, $e);
        }

        unset($__beryonCode, $__beryonVars, $__beryonFile);

        return (string) ob_get_clean();
    }

    /**
     * ثبت خطای رندر: همیشه در error_log، و نمایش آن برای مدیر/حالت دیباگ.
     *
     * @param \\Throwable $e     خطا
     * @param string     $where محل اجرا
     * @return void
     */
    private static function reportError(\\Throwable $e, string $where): void
    {
        $message = sprintf(
            '[beryon] خطای رندر نما در %s → %s (%s:%d)',
            $where,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        error_log($message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

        $debug = (defined('WP_DEBUG') && WP_DEBUG)
            || (function_exists('current_user_can') && current_user_can('manage_options'));

        if ($debug) {
            echo '<pre class="beryon-blade-error" dir="ltr" style="background:#fff3f3;border:2px solid #d5232c;'
                . 'color:#7a1116;padding:12px;margin:12px;font:12px/1.8 monospace;white-space:pre-wrap;'
                . 'text-align:left;direction:ltr">' . esc_html($message) . '</pre>';
        }
    }
'''

FORELSE_NEW = '''                    case 'forelse':
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
                    case 'empty':
                        // @empty داخل @forelse = شاخه «خالی»؛ @empty($x) = شرط مستقل Blade.
                        if ($args !== '') {
                            return '<?php if (empty(' . $args . ')): ?>';
                        }

                        return '<?php endforeach; else: ?>';
                    case 'error':
                        return '<?php if (false): ?>';
                    case 'stack':
                        $parts = self::splitArgs($args);

                        return '<?php echo \\App\\MiniBlade::section((string) (' . $parts[0] . " ?? '')); ?>";
                    case 'hasSection':
                        $parts = self::splitArgs($args);

                        return '<?php if (\\App\\MiniBlade::has((string) (' . $parts[0] . " ?? ''))): ?>";
                    case 'sectionMissing':
                        $parts = self::splitArgs($args);

                        return '<?php if (! \\App\\MiniBlade::has((string) (' . $parts[0] . " ?? ''))): ?>";
                    case 'props':
                        return '<?php foreach ((array) (' . ($args === '' ? '[]' : $args) . ') as $__k => $__v) {'
                            . ' if (! isset($$__k)) { $$__k = $__v; } } unset($__k, $__v); ?>';
                    case 'includeWhen':
                        $parts = self::splitArgs($args);
                        $view  = trim((string) ($parts[1] ?? ''), "'\\"");
                        $data  = (string) ($parts[2] ?? '[]');

                        return '<?php if (' . (string) ($parts[0] ?? 'false') . ') { echo \\App\\MiniBlade::render('
                            . var_export($view, true) . ', (array) (' . $data . ')); } ?>';
'''

LAYOUT_NEW = '''        self::$shared['__extends'] = '';

        $content = self::render($view, $data);
        $guard   = 0;

        // حلقه برای چیدمان‌های تودرتو (@extends داخل چیدمان).
        while ($guard++ < 5) {
            $layout = (string) (self::$shared['__extends'] ?? '');

            if ($layout === '') {
                break;
            }

            self::$shared['__extends'] = '';

            // اگر فرزندی بخش content را نساخته باشد، کل خروجی او content است.
            if (! isset(self::$sections['content']) || trim((string) self::$sections['content']) === '') {
                self::$sections['content'] = $content;
            }

            $content = self::render($layout, $data);
        }

        unset(self::$shared['__extends']);

        return $content;
'''


if __name__ == '__main__':
    sys.exit(main())
