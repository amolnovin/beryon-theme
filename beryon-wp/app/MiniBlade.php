<?php

/**
 * کامپایلر Blade سبک (MiniBlade) — لایه پشتیبان قالب بریون
 * ------------------------------------------------------------------
 * اگر Acorn/Laravel View نصب نباشد (composer install اجرا نشده باشد)، این کلاس
 * زیرمجموعه‌ای از دستورات Blade را کامپایل و رندر می‌کند تا قالب بدون وابستگی
 * خارجی هم صفحه تولید کند. پشتیبانی می‌شود:
 *
 *   {{ $var }}  {!! $raw !!}  {{-- comment --}}
 *   @if / @elseif / @else / @endif     @unless / @endunless
 *   @foreach / @endforeach             @forelse / @empty / @endforelse
 *   @for / @endfor                     @while / @endwhile
 *   @php / @endphp                     @include('x', [...])
 *   @component('x', [...]) / @endcomponent
 *   <x-name attr="…" :prop="$v" />     و  <x-name>…</x-name>
 *   @json($v)  @empty/@isset  @break  @continue  @stack/@hasSection
 *   @verbatim … @endverbatim  @props  @includeWhen

 * کد کامپایل‌شده در فایل حافظه نهان نوشته و include می‌شود (سریع‌تر از eval،
 * با شماره خط واقعی در خطاها و در فضای نام سراسری — مانند Blade واقعی). اگر
 * هیچ پوشه‌ای قابل نوشتن نباشد، از eval استفاده می‌شود. هیچ مسیری اجازه ندارد
 * رشته خالی برگرداند: خطا ثبت و به صفحه اضطراری Template منتقل می‌شود.
 *
 * خروجی برای فایل‌های کامیت‌شده در public/views-compiled نیز قابل استفاده است.
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * کلاس کامپایلر Blade سبک.
 */
final class MiniBlade
{
    /**
     * مسیرهای جست‌وجوی نماها.
     *
     * @var array<int,string>
     */
    private static $paths = [];

    /**
     * حافظه نهان کامپایل.
     *
     * @var array<string,string>
     */
    private static $compiled = [];

    /**
     * داده مشترک همه نماها.
     *
     * @var array<string,mixed>
     */
    private static $shared = [];

    /**
     * عمق @forelse (برای نام‌گذاری متغیر موقت یکتا در حلقه‌های تودرتو).
     *
     * @var int
     */
    private static $forelseDepth = 0;

    /**
     * پوشه حافظه نهان نماهای کامپایل‌شده ('' یعنی قابل نوشتن نیست).
     *
     * @var string|null
     */
    private static $cacheDir = null;

    /**
     * افزودن مسیر نما.
     *
     * @param string $path مسیر
     * @return void
     */
    public static function addPath(string $path): void
    {
        $path = rtrim($path, '/');

        if (! in_array($path, self::$paths, true)) {
            array_unshift(self::$paths, $path);
        }
    }

    /**
     * داده مشترک.
     *
     * @param string $key   کلید
     * @param mixed  $value مقدار
     * @return void
     */
    public static function share(string $key, $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * پیدا کردن فایل نما.
     *
     * @param string $view نام نما (با نقطه)
     * @return string
     */
    public static function find(string $view): string
    {
        $relative = str_replace('.', '/', $view) . '.blade.php';

        foreach (self::searchPaths() as $path) {
            if (is_readable($path . '/' . $relative)) {
                return $path . '/' . $relative;
            }
        }

        return '';
    }

    /**
     * مسیرهای جست‌وجو به‌همراه مسیر پیش‌فرض نماهای قالب.
     *
     * پوشه resources/views قالب همیشه (با کمترین اولویت) جزو مسیرهاست، حتی
     * اگر هنوز addPath() صدا زده نشده باشد. پیش‌تر مسیرها فقط هنگام نخستین
     * رندر (Acorn::view/page) ثبت می‌شدند؛ در نتیجه Template::resolve() که
     * پیش از رندر find() را صدا می‌زند هیچ نمایی پیدا نمی‌کرد و همه نشانی‌ها
     * به نمای index (فهرست وبلاگ) برمی‌گشتند.
     *
     * @return array<int,string>
     */
    private static function searchPaths(): array
    {
        $paths = self::$paths;

        if (defined('BERYON_PATH')) {
            $default = rtrim((string) BERYON_PATH, '/') . '/resources/views';

            if (! in_array($default, $paths, true)) {
                $paths[] = $default;
            }
        }

        return $paths;
    }

    /**
     * رندر یک نما.
     *
     * @param string              $view نام
     * @param array<string,mixed> $data داده
     * @return string
     */
    public static function render(string $view, array $data = []): string
    {
        $file = self::find($view);

        if ($file === '') {
            error_log('[beryon] نما پیدا نشد: ' . $view); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

            if (function_exists('current_user_can') && current_user_can('manage_options')) {
                return '<!-- beryon: view not found: ' . esc_html($view) . ' -->';
            }

            return '';
        }

        $php = self::compile($file);

        return self::evaluate($php, array_merge(self::$shared, $data), $file);
    }

    /**
     * کامپایل فایل به PHP (با حافظه نهان).
     *
     * @param string $file مسیر فایل
     * @return string
     */
    public static function compile(string $file): string
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

        // گارد ABSPATH: فایل‌های حافظه نهان نباید مستقیم از وب قابل اجرا باشند.
        $body = "<?php\n// نمای کامپایل‌شده توسط MiniBlade قالب بریون — منبع: "
            . str_replace(['*/', "\n"], ['', ' '], $file)
            . "\nif (! defined('ABSPATH')) {\n    exit;\n}\n?>" . $source;

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

    /**
     * ارزیابی PHP کامپایل‌شده.
     *
     * @param string              $__php  کد
     * @param array<string,mixed> $__data داده
     * @return string
     */
    private static function evaluate(string $__php, array $__data, string $__source = ''): string
    {
        // کد کامپایل‌شده در متغیری با نام غیرقابل‌تصادم نگه داشته می‌شود؛ نسخه
        // پیشین همین متغیر را unset می‌کرد و در نتیجه همیشه خروجی خالی می‌داد.
        $__beryonCode = $__php;
        $__beryonVars = $__data;
        $__beryonFile = $__source !== '' ? self::cacheFile($__source) : '';

        unset($__data, $__php, $__source);

        // سطح بافرهای خروجی پیش از شروع؛ تا در خطا فقط بافرهای خودمان بسته شوند
        // (نه بافرهای وردپرس یا افزونه‌ها).
        $__beryonLevel = ob_get_level();

        ob_start();

        try {
            extract($__beryonVars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract

            if ($__beryonFile !== '' && is_readable($__beryonFile)) {
                // include در فضای نام سراسری اجرا می‌شود (مانند Blade واقعی)؛
                // در نتیجه نام‌های کلاس نسبی مثل App\Products درست حل می‌شوند.
                include $__beryonFile;
            } else {
                eval('?>' . $__beryonCode); // phpcs:ignore Squiz.PHP.Eval
            }
        } catch (\Throwable $e) {
            while (ob_get_level() > $__beryonLevel) {
                ob_end_clean();
            }

            self::reportError($e, $__beryonFile !== '' ? $__beryonFile : 'eval');

            throw new \RuntimeException('MiniBlade: ' . $e->getMessage(), 0, $e);
        }

        unset($__beryonCode, $__beryonVars, $__beryonFile);

        return (string) ob_get_clean();
    }

    /**
     * ثبت خطای رندر: همیشه در error_log، و نمایش آن برای مدیر/حالت دیباگ.
     *
     * @param \Throwable $e     خطا
     * @param string     $where محل اجرا
     * @return void
     */
    private static function reportError(\Throwable $e, string $where): void
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

    /* ------------------------------------------------------------------ *
     * مراحل کامپایل
     * ------------------------------------------------------------------ */

    /**
     * حذف کامنت‌های Blade.
     *
     * @param string $source منبع
     * @return string
     */
    private static function compileComments(string $source): string
    {
        return (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);
    }

    /**
     * تبدیل کامپوننت‌های <x-…> به فراخوانی رندر.
     *
     * @param string $source منبع
     * @return string
     */
    private static function compileComponents(string $source): string
    {
        // حالت خودبسته: <x-name attr="…" />
        $source = (string) preg_replace_callback(
            '/<x-([a-zA-Z0-9\.\-_]+)((?:\s+[^>]*?)?)\s*\/>/',
            /**
             * @param array<int,string> $m
             */
            static function (array $m): string {
                return '<?php echo \App\MiniBlade::component(' . var_export($m[1], true) . ', ' . self::parseAttrs($m[2]) . '); ?>';
            },
            $source
        );

        // حالت باز/بسته: <x-name …> … </x-name>
        $limit = 20;

        while ($limit-- > 0 && preg_match('/<x-([a-zA-Z0-9\.\-_]+)((?:\s+[^>]*?)?)>/', $source, $match, PREG_OFFSET_CAPTURE)) {
            $name  = $match[1][0];
            $start = $match[0][1];
            $len   = strlen($match[0][0]);
            $close = '</x-' . $name . '>';
            $end   = strpos($source, $close, $start + $len);

            if ($end === false) {
                break;
            }

            $slot = substr($source, $start + $len, $end - $start - $len);
            $php  = '<?php ob_start(); ?>' . $slot . '<?php $__slot = (string) ob_get_clean(); echo \App\MiniBlade::component('
                . var_export($name, true) . ', ' . self::parseAttrs($match[2][0]) . ', $__slot); ?>';

            $source = substr($source, 0, $start) . $php . substr($source, $end + strlen($close));
        }

        return $source;
    }

    /**
     * تبدیل attributeهای کامپوننت به آرایه PHP.
     *
     * @param string $attrs رشته attributeها
     * @return string کد PHP
     */
    private static function parseAttrs(string $attrs): string
    {
        $attrs = trim($attrs);

        if ($attrs === '') {
            return '[]';
        }

        $items = [];

        // :prop="$var" → مقدار PHP
        if (preg_match_all('/(?<![\w:])\s*:([a-zA-Z0-9_\-\.]+)\s*=\s*"([^"]*)"/', $attrs, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $items[] = var_export($hit[1], true) . ' => ' . self::phpValue($hit[2]);
                $attrs    = str_replace($hit[0], '', $attrs);
            }
        }

        // :prop (بدون مقدار) → true
        if (preg_match_all('/(?<![\w:])\s+:([a-zA-Z0-9_\-\.]+)(?=[\s>]|$)/', $attrs, $m2, PREG_SET_ORDER)) {
            foreach ($m2 as $hit) {
                $items[] = var_export($hit[1], true) . ' => true';
                $attrs    = str_replace($hit[0], '', $attrs);
            }
        }

        // attr="value" → رشته (با {{ }} درون‌خطی)
        if (preg_match_all('/\s([a-zA-Z0-9_\-\.:@#]+)\s*=\s*"([^"]*)"/', $attrs, $m3, PREG_SET_ORDER)) {
            foreach ($m3 as $hit) {
                $key   = ltrim($hit[1], '@#:');
                $value = $hit[2];

                if (strpos($value, '{{') !== false) {
                    $value = (string) preg_replace_callback(
                        '/\{\{\s*(.+?)\s*\}\}/',
                        /** @param array<int,string> $x */
                        static fn (array $x): string => '\' . ' . self::phpValue($x[1]) . ' . \'',
                        $value
                    );
                    $items[] = var_export($key, true) . " => '" . str_replace("'", "\\'", $value) . "'";

                    continue;
                }

                $items[] = var_export($key, true) . ' => ' . var_export($value, true);
            }
        }

        // attribute بدون مقدار
        $attrs = trim((string) preg_replace('/\s[a-zA-Z0-9_\-\.:@#]+\s*=\s*"[^"]*"/', '', $attrs));

        foreach (preg_split('/\s+/', $attrs) ?: [] as $bare) {
            $bare = trim($bare);

            if ($bare !== '' && $bare[0] !== '<') {
                $items[] = var_export(ltrim($bare, '@#:'), true) . ' => true';
            }
        }

        return '[' . implode(', ', $items) . ']';
    }

    /**
     * تبدیل مقدار به عبارت PHP.
     *
     * @param string $value مقدار
     * @return string
     */
    private static function phpValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return "''";
        }

        if (preg_match('/^\$[a-zA-Z_]/', $value) || preg_match('/^[\(\[]/', $value) || preg_match('/^(true|false|null|\d)/i', $value)
            || strpos($value, '->') !== false || strpos($value, '::') !== false || strpos($value, '(') !== false) {
            return $value;
        }

        return var_export($value, true);
    }

    /**
     * رندر یک کامپوننت.
     *
     * @param string              $name نام
     * @param array<string,mixed> $data داده
     * @param string              $slot محتوای داخلی
     * @return string
     */
    public static function component(string $name, array $data = [], string $slot = ''): string
    {
        $view = 'components.' . str_replace('/', '.', $name);

        if (self::find($view) === '') {
            return $slot;
        }

        $data['slot']    = $slot;
        $data['attributes'] = new class ($data) {
            /**
             * @var array<string,mixed>
             */
            private $items;

            /**
             * @param array<string,mixed> $items داده
             */
            public function __construct(array $items)
            {
                $this->items = $items;
            }

            /**
             * تبدیل به رشته attribute.
             *
             * @return string
             */
            public function __toString(): string
            {
                $out = [];

                foreach ($this->items as $key => $value) {
                    if (in_array($key, ['slot', 'attributes'], true) || $value === false || $value === null) {
                        continue;
                    }

                    $out[] = $value === true ? esc_attr((string) $key) : esc_attr((string) $key) . '="' . esc_attr((string) $value) . '"';
                }

                return implode(' ', $out);
            }
        };

        return self::render($view, $data);
    }

    /**
     * تبدیل دستورات Blade.
     *
     * @param string $source منبع
     * @return string
     */
    private static function compileDirectives(string $source): string
    {
        self::$forelseDepth = 0;

        $map = [
            'endswitch'    => '<?php endswitch; ?>',
            'endif'        => '<?php endif; ?>',
            'else'         => '<?php else: ?>',
            'endunless'    => '<?php endif; ?>',
            'endforeach'   => '<?php endforeach; ?>',
            'endfor'       => '<?php endfor; ?>',
            'endwhile'     => '<?php endwhile; ?>',
            'endforelse'   => '<?php endif; ?>',
            'endisset'     => '<?php endif; ?>',
            'endempty'     => '<?php endif; ?>',
            'enderror'      => '<?php endif; ?>',
            'endphp'       => '?>',
            'break'        => '<?php break; ?>',
            'continue'     => '<?php continue; ?>',
        ];

        $source = (string) preg_replace_callback(
            '/@(if|elseif|unless|foreach|forelse|for|while|isset|empty|error|endphp|php|switch|case|default|include|includeIf|includeWhen|json|yield|stack|hasSection|sectionMissing|section|endsection|extends|push|endpush|prepend|endprepend|once|endonce|auth|endauth|guest|endguest|production|endproduction|aware|props)\b(\s*\(((?:[^()]|(?2))*)\))?/',
            /** @param array<int,string|null> $m */
            static function (array $m) use ($map): string {
                $directive = $m[1];
                $args      = isset($m[3]) ? trim((string) $m[3]) : '';

                if ($directive === 'endphp') {
                    return '?>';
                }

                switch ($directive) {
                    case 'if':
                        return '<?php if (' . $args . '): ?>';
                    case 'elseif':
                        return '<?php elseif (' . $args . '): ?>';
                    case 'unless':
                        return '<?php if (! (' . $args . ')): ?>';
                    case 'foreach':
                        return '<?php foreach (' . $args . '): ?>';
                    case 'forelse':
                        // فرم استاندارد Blade:  @forelse($items as $key => $value)
                        if (preg_match('/^(.+?)\s+as\s+(.+)$/is', $args, $as) === 1) {
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

                        return '<?php echo \App\MiniBlade::section((string) (' . $parts[0] . " ?? '')); ?>";
                    case 'hasSection':
                        $parts = self::splitArgs($args);

                        return '<?php if (\App\MiniBlade::has((string) (' . $parts[0] . " ?? ''))): ?>";
                    case 'sectionMissing':
                        $parts = self::splitArgs($args);

                        return '<?php if (! \App\MiniBlade::has((string) (' . $parts[0] . " ?? ''))): ?>";
                    case 'props':
                        return '<?php foreach ((array) (' . ($args === '' ? '[]' : $args) . ') as $__k => $__v) {'
                            . ' if (! isset($$__k)) { $$__k = $__v; } } unset($__k, $__v); ?>';
                    case 'includeWhen':
                        $parts = self::splitArgs($args);
                        $view  = trim((string) ($parts[1] ?? ''), "'\"");
                        $data  = (string) ($parts[2] ?? '[]');

                        return '<?php if (' . (string) ($parts[0] ?? 'false') . ') { echo \App\MiniBlade::render('
                            . var_export($view, true) . ', (array) (' . $data . ')); } ?>';
                    case 'for':
                        return '<?php for (' . $args . '): ?>';
                    case 'while':
                        return '<?php while (' . $args . '): ?>';
                    case 'isset':
                        return '<?php if (isset(' . $args . ')): ?>';
                    case 'php':
                        return $args === '' ? '<?php ' : '<?php ' . $args . '; ?>';
                    case 'include':
                    case 'includeIf':
                        $parts = self::splitArgs($args);
                        $view  = trim((string) ($parts[0] ?? ''), "'\"");
                        $data  = (string) ($parts[1] ?? '[]');

                        return '<?php echo \App\MiniBlade::render(' . var_export($view, true) . ', (array) (' . $data . ')); ?>';
                    case 'json':
                        return '<?php echo wp_json_encode(' . $args . ', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>';
                    case 'yield':
                        $parts = self::splitArgs($args);

                        return '<?php echo \App\MiniBlade::section(' . $parts[0] . ' ?? \'\'); ?>';
                    case 'section':
                        $parts = self::splitArgs($args);

                        if (count($parts) > 1) {
                            return '<?php \App\MiniBlade::setSection(' . $parts[0] . ', ' . $parts[1] . '); ?>';
                        }

                        return '<?php ob_start(); \App\MiniBlade::startSection(' . $parts[0] . '); ?>';
                    case 'endsection':
                        return '<?php \App\MiniBlade::stopSection((string) ob_get_clean()); ?>';
                    case 'extends':
                        $parts = self::splitArgs($args);

                        return '<?php \App\MiniBlade::extend(' . $parts[0] . '); ?>';
                    case 'push':
                        return '<?php ob_start(); \App\MiniBlade::startPush(' . $args . '); ?>';
                    case 'endpush':
                        return '<?php \App\MiniBlade::stopPush((string) ob_get_clean()); ?>';
                    case 'prepend':
                        return '<?php ob_start(); \App\MiniBlade::startPush(' . $args . ', true); ?>';
                    case 'endprepend':
                        return '<?php \App\MiniBlade::stopPush((string) ob_get_clean()); ?>';
                    case 'once':
                        return '<?php $__once = md5(__FILE__ . __LINE__); if (! \App\MiniBlade::once($__once)): ?>';
                    case 'endonce':
                        return '<?php \App\MiniBlade::endOnce(); endif; ?>';
                    case 'auth':
                        return '<?php if (is_user_logged_in()): ?>';
                    case 'endauth':
                        return '<?php endif; ?>';
                    case 'guest':
                        return '<?php if (! is_user_logged_in()): ?>';
                    case 'endguest':
                        return '<?php endif; ?>';
                    case 'production':
                        return '<?php if (true): ?>';
                    case 'endproduction':
                        return '<?php endif; ?>';
                    case 'aware':
                        return '<?php if (true): ?>';
                    case 'switch':
                        return '<?php switch (' . $args . '): ?>';
                    case 'case':
                        return '<?php case ' . $args . ': ?>';
                    case 'default':
                        return '<?php default: ?>';
                    default:
                        return $m[0];
                }
            },
            $source
        );

        foreach ($map as $directive => $php) {
            // مقادیر $map هیچ‌کدام $ یا \ ندارند؛ str_replace فقط احتیاط است.
            $source = (string) preg_replace('/@' . $directive . '\b/', str_replace('$', '\$', $php), $source);
        }

        return $source;
    }

    /**
     * تبدیل {{ }} و {!! !!}.
     *
     * @param string $source منبع
     * @return string
     */
    private static function compileEcho(string $source): string
    {
        $source = (string) preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?php echo $1; ?>', $source);
        $source = (string) preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?php echo esc_html((string) ($1)); ?>', $source);

        return $source;
    }

    /**
     * جداسازی آرگومان‌های یک دستور با احترام به کاما داخل پرانتز/کروت.
     *
     * @param string $args رشته
     * @return array<int,string>
     */
    private static function splitArgs(string $args): array
    {
        $parts  = [];
        $depth  = 0;
        $buffer = '';
        $quote  = '';
        $length = strlen($args);

        for ($i = 0; $i < $length; $i++) {
            $char = $args[$i];

            if ($quote !== '') {
                $buffer .= $char;

                if ($char === $quote && ($i === 0 || $args[$i - 1] !== '\\')) {
                    $quote = '';
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote   = $char;
                $buffer .= $char;

                continue;
            }

            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = trim($buffer);
                $buffer  = '';

                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }

        return $parts;
    }

    /* ------------------------------------------------------------------ *
     * بخش‌ها و stackها
     * ------------------------------------------------------------------ */

    /**
     * بخش‌های ثبت‌شده.
     *
     * @var array<string,string>
     */
    private static $sections = [];

    /**
     * صف stackها.
     *
     * @var array<string,array<int,string>>
     */
    private static $stacks = [];

    /**
     * نام بخش در حال ضبط.
     *
     * @var array<int,string>
     */
    private static $sectionStack = [];

    /**
     * نام push در حال ضبط.
     *
     * @var array<int,array{name:string,prepend:bool}>
     */
    private static $pushStack = [];

    /**
     * شناسه‌های @once دیده‌شده.
     *
     * @var array<int,string>
     */
    private static $once = [];

    /**
     * شروع ضبط بخش.
     *
     * @param string $name نام
     * @return void
     */
    public static function startSection(string $name): void
    {
        self::$sectionStack[] = $name;
    }

    /**
     * پایان ضبط بخش.
     *
     * @param string $content محتوا
     * @return void
     */
    public static function stopSection(string $content): void
    {
        $name = (string) array_pop(self::$sectionStack);

        if ($name !== '') {
            self::$sections[$name] = $content;
        }
    }

    /**
     * ثبت مستقیم یک بخش.
     *
     * @param string $name    نام
     * @param string $content محتوا
     * @return void
     */
    public static function setSection(string $name, string $content): void
    {
        self::$sections[$name] = $content;
    }

    /**
     * خواندن یک بخش.
     *
     * @param string $name نام
     * @return string
     */
    public static function section(string $name): string
    {
        if (isset(self::$sections[$name])) {
            return self::$sections[$name];
        }

        return isset(self::$stacks[$name]) ? implode('', self::$stacks[$name]) : '';
    }

    /**
     * آیا یک stack/بخش محتوا دارد؟
     *
     * @param string $name نام
     * @return bool
     */
    public static function has(string $name): bool
    {
        return trim(self::section($name)) !== '';
    }

    /**
     * شروع push.
     *
     * @param string $name    نام
     * @param bool   $prepend الحاق از ابتدا
     * @return void
     */
    public static function startPush(string $name, bool $prepend = false): void
    {
        self::$pushStack[] = ['name' => $name, 'prepend' => $prepend];
    }

    /**
     * پایان push.
     *
     * @param string $content محتوا
     * @return void
     */
    public static function stopPush(string $content): void
    {
        $item = array_pop(self::$pushStack);

        if (! is_array($item)) {
            return;
        }

        $name = (string) $item['name'];

        if (! isset(self::$stacks[$name])) {
            self::$stacks[$name] = [];
        }

        if ($item['prepend']) {
            array_unshift(self::$stacks[$name], $content);

            return;
        }

        self::$stacks[$name][] = $content;
    }

    /**
     * کنترل @once.
     *
     * @param string $key کلید
     * @return bool
     */
    public static function once(string $key): bool
    {
        if (in_array($key, self::$once, true)) {
            return false;
        }

        self::$once[] = $key;

        return true;
    }

    /**
     * پایان @once.
     *
     * @return void
     */
    public static function endOnce(): void
    {
        // بدون عملیات؛ فقط برای خوانایی
    }

    /**
     * تنظیم قالب والد (@extends).
     *
     * @param string $view نام
     * @return void
     */
    public static function extend(string $view): void
    {
        self::$shared['__extends'] = $view;
    }

    /**
     * پاک‌سازی وضعیت (پیش از هر رندر مستقل).
     *
     * @return void
     */
    public static function flushState(): void
    {
        self::$sections     = [];
        self::$stacks       = [];
        self::$sectionStack = [];
        self::$pushStack    = [];
        self::$once         = [];
        self::$compiled     = [];
        self::$forelseDepth = 0;
    }

    /**
     * رندر کامل با پشتیبانی @extends.
     *
     * @param string              $view نام
     * @param array<string,mixed> $data داده
     * @return string
     */
    public static function renderWithLayout(string $view, array $data = []): string
    {
        self::$shared['__extends'] = '';

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
    }
}
