<?php

/**
 * دارایی‌های قالب بریون — صف استایل/اسکریپت، فونت، مسیر و بهینه‌سازی تصاویر
 * ------------------------------------------------------------------
 * ترتیب یافتن فایل خروجی:
 *   1) public/assets/manifest.json  (بیلد Vite)
 *   2) public/assets/<path>          (خروجی از پیش بیلدشده داخل بسته)
 *   3) resources/<source>            (فایل خام، فقط برای توسعه)
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * مدیریت دارایی‌ها.
 */
final class Assets
{
    /** هندل استایل اصلی */
    public const STYLE = 'beryon-app';

    /** هندل اسکریپت اصلی */
    public const SCRIPT = 'beryon-app';

    /**
     * حافظه نهان manifest.
     *
     * @var array<string,mixed>|null
     */
    private static $manifest = null;

    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        \add_action('wp_enqueue_scripts', [self::class, 'enqueue'], 20);
        \add_action('wp_head', [self::class, 'head_hints'], 1);
        \add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
        \add_action('enqueue_block_editor_assets', [self::class, 'editor_assets']);

        \add_filter('body_class', [self::class, 'body_class']);
        \add_filter('wp_resource_hints', [self::class, 'resource_hints'], 10, 2);
    }

    /**
     * بارگذاری استایل و اسکریپت سمت سایت.
     *
     * @return void
     */
    public static function enqueue(): void
    {
        // fallback خام برای اسکریپت عمداً main.js است: resources/js/app.js یک
        // ES module با import است و با تگ کلاسیک وردپرس اجرا نمی‌شود.
        $css = self::resolve('css/app.css', 'resources/css/app.css');
        $js  = self::resolve('js/app.js', 'resources/js/main.js');

        // نکته: طراحی قالب ذاتاً RTL است؛ wp_style_add_data(…, 'rtl', 'replace')
        // باعث می‌شد وردپرس به‌دنبال app-rtl.css (ناموجود) بگردد و کل استایل
        // سایت ۴۰۴ شود.
        if ($css['path'] !== '' && is_readable($css['path'])) {
            \wp_enqueue_style(self::STYLE, $css['url'], [], $css['version'], 'all');
        } else {
            self::registerInlineStyle(self::STYLE, ['resources/css/beryon.css', 'resources/css/wp.css']);
        }

        if ($js['path'] !== '' && is_readable($js['path'])) {
            \wp_enqueue_script(self::SCRIPT, $js['url'], [], $js['version'], true);
        } else {
            self::registerInlineScript(self::SCRIPT, ['resources/js/main.js']);
        }

        \wp_localize_script(
            self::SCRIPT,
            'beryonConfig',
            [
                'restUrl'    => esc_url_raw(\rest_url('beryon/v1')),
                'nonce'      => \wp_create_nonce('wp_rest'),
                'ajaxUrl'    => \admin_url('admin-ajax.php'),
                'homeUrl'    => \home_url('/'),
                'forms'      => [
                    'enabled'   => (bool) Settings::opt('forms.enabled', true),
                    'honeypot'  => (string) Settings::opt('forms.honeypot_field', 'website_url'),
                    'minLength' => (int) Settings::opt('forms.min_message_length', 10),
                ],
                'i18n'       => [
                    'sending' => \__('در حال ارسال…', BERYON_TD),
                    'error'   => \__('ارسال پیام ناموفق بود؛ لطفاً دوباره تلاش کنید.', BERYON_TD),
                    'track'   => \__('کد پیگیری', BERYON_TD),
                ],
                'transparentHeader' => self::is_transparent_header(),
            ]
        );

        // فونت خودمیمان وزیرمتن (فایل جدا، فقط در صورت فعال بودن self-host).
        if (self::font_is_local()) {
            $fonts = self::resolve('css/fonts.css', 'resources/css/fonts.css');

            if ($fonts['path'] !== '' && is_readable($fonts['path'])) {
                \wp_enqueue_style('beryon-fonts', $fonts['url'], [], $fonts['version'], 'all');
            } else {
                self::registerInlineStyle('beryon-fonts', ['resources/css/fonts.css']);
            }
        }
    }

    /**
     * نکات سرصفحه: theme-color، preload تصویر هیرو، preconnect فونت.
     *
     * @return void
     */
    public static function head_hints(): void
    {
        $color = (string) Settings::opt('general.theme_color', '#d5232c');

        echo '<meta name="theme-color" content="' . esc_attr($color) . '">' . "\n";

        if (! self::font_is_local() && Settings::opt('performance.fonts_google', true)) {
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";

            \wp_enqueue_style(
                'beryon-google-fonts',
                'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800;900&display=swap',
                [],
                null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- منبع خارجی
            );
        }

        if (! \is_admin() && \is_front_page() && Settings::opt('performance.preload_hero', true)) {
            $hero = self::image_url((string) Settings::opt('home.hero_image', 'hero.webp'));

            echo '<link rel="preload" as="image" href="' . esc_url($hero) . '" fetchpriority="high">' . "\n";
        }
    }

    /**
     * دارایی پنل تنظیمات قالب.
     *
     * @param string $hook هوک صفحه جاری
     * @return void
     */
    public static function admin_assets(string $hook): void
    {
        // صفحه‌های قالب با اسلاگ beryon-* ثبت می‌شوند؛ هم $hook و هم ?page= بررسی
        // می‌شود تا در هیچ چیدمان منویی استایل پنل از دست نرود.
        $page = isset($_GET['page']) ? (string) \sanitize_key(\wp_unslash($_GET['page'])) : '';

        if (strpos($hook, 'beryon') === false && strpos($page, 'beryon') === false) {
            return;
        }

        $css = self::resolve('css/admin.css', 'resources/css/admin.css');
        $js  = self::resolve('js/admin.js', 'resources/js/admin.js');

        if ($css['path'] !== '' && is_readable($css['path'])) {
            \wp_enqueue_style('beryon-admin', $css['url'], [], $css['version']);
        } else {
            self::registerInlineStyle('beryon-admin', ['resources/css/admin.css']);
        }

        if ($js['path'] !== '' && is_readable($js['path'])) {
            \wp_enqueue_script('beryon-admin', $js['url'], ['wp-color-picker'], $js['version'], true);
        } else {
            self::registerInlineScript('beryon-admin', ['resources/js/admin.js']);
        }

        \wp_localize_script(
            'beryon-admin',
            'beryonAdmin',
            [
                'ajaxUrl'     => \admin_url('admin-ajax.php'),
                'nonce'       => \wp_create_nonce('beryon_admin'),
                'restNonce'   => \wp_create_nonce('wp_rest'),
                'confirmApply' => \__('پس از اعمال Patch، فایل‌های قالب جایگزین می‌شوند. پشتیبان خودکار ساخته خواهد شد. ادامه می‌دهید؟', BERYON_TD),
                'confirmRollback' => \__('بازگردانی، قالب را به نسخه پشتیبان برمی‌گرداند. ادامه می‌دهید؟', BERYON_TD),
                'i18n'        => [
                    'check'   => \__('در حال بررسی…', BERYON_TD),
                    'apply'   => \__('در حال اعمال…', BERYON_TD),
                    'rowAdd'  => \__('افزودن ردیف', BERYON_TD),
                ],
            ]
        );

        \wp_enqueue_style('wp-color-picker');
    }

    /**
     * استایل ویرایشگر گوتنبرگ (هم‌خوان با ظاهر سایت).
     *
     * @return void
     */
    public static function editor_assets(): void
    {
        $css = self::resolve('css/editor.css', 'resources/css/editor.css');

        if ($css['path'] !== '' && is_readable($css['path'])) {
            \wp_enqueue_style('beryon-editor', $css['url'], [], $css['version']);
        } else {
            self::registerInlineStyle('beryon-editor', ['resources/css/editor.css']);
        }
    }

    /**
     * کلاس‌های body (home برای هدر شفاف + کلاس‌های استاندارد وردپرس).
     *
     * @param array<int,string> $classes کلاس‌ها
     * @return array<int,string>
     */
    public static function body_class(array $classes): array
    {
        if (\is_front_page() || \is_home()) {
            $classes[] = 'home';
        }

        if (self::is_transparent_header()) {
            $classes[] = 'has-transparent-header';
        }

        $classes[] = 'beryon';

        return array_values(array_unique($classes));
    }

    /**
     * آیا هدر شفاف باید فعال باشد؟ (فقط صفحه اصلی)
     *
     * @return bool
     */
    public static function is_transparent_header(): bool
    {
        return (bool) Settings::opt('home.transparent_header', true) && \is_front_page() && ! \is_paged();
    }

    /**
     * پیش‌اتصال به دامنه‌های مورد نیاز.
     *
     * @param array<int,string|array> $urls    آدرس‌ها
     * @param string                  $type    نوع (preconnect|dns-prefetch)
     * @return array<int,string|array>
     */
    public static function resource_hints(array $urls, string $type): array
    {
        if ($type === 'preconnect' && Settings::opt('performance.fonts_google', true) && ! self::font_is_local()) {
            $urls[] = ['href' => 'https://fonts.gstatic.com', 'crossorigin'];
        }

        if ($type === 'dns-prefetch') {
            $urls[] = '//maps.app.goo.gl';
        }

        return $urls;
    }

    /* ============================================================== *
     * مسیرها و نسخه‌گذاری
     * ============================================================== */

    /**
     * یافتن یک دارایی و برگرداندن url + version.
     *
     * @param string $buildPath مسیر در public/assets (مثال: css/app.css)
     * @param string $sourcePath مسیر خام (مثال: resources/css/app.css)
     * @return array{url:string,version:string,path:string}
     */
    public static function resolve(string $buildPath, string $sourcePath = ''): array
    {
        $manifest = self::manifest();

        // ۱) manifest بیلد Vite
        foreach ([$buildPath, 'resources/' . $buildPath] as $key) {
            if (isset($manifest[$key]['file'])) {
                $relative = ltrim((string) $manifest[$key]['file'], '/');
                $absolute = BERYON_PATH . '/public/assets/' . $relative;

                // اگر خروجی بیلد واقعاً روی دیسک نباشد، به مرحله بعد می‌رویم؛
                // در غیر این‌صورت یک ۴۰۴ خاموش کل استایل سایت را می‌گیرد.
                if (! is_readable($absolute)) {
                    continue;
                }

                return [
                    'url'     => self::public_url($relative),
                    'version' => self::file_version($absolute),
                    'path'    => $absolute,
                ];
            }
        }

        // ۲) خروجی از پیش بیلدشده
        $built = BERYON_PATH . '/public/assets/' . ltrim($buildPath, '/');

        if (is_readable($built)) {
            return [
                'url'     => self::public_url(ltrim($buildPath, '/')),
                'version' => self::file_version($built),
                'path'    => $built,
            ];
        }

        // ۳) فایل خام
        if ($sourcePath !== '' && is_readable(BERYON_PATH . '/' . $sourcePath)) {
            return [
                'url'     => self::theme_url($sourcePath),
                'version' => self::file_version(BERYON_PATH . '/' . $sourcePath),
                'path'    => BERYON_PATH . '/' . $sourcePath,
            ];
        }

        // ۴) در هر حالت یک URL معتبر برگردان (از خطای ۴۰۴ پرسر و صدا جلوگیری می‌کند)
        return [
            'url'     => self::public_url(ltrim($buildPath, '/')),
            'version' => BERYON_VERSION,
            'path'    => '',
        ];
    }

    /**
     * ثبت استایل درون‌خطی اضطراری وقتی هیچ فایل CSS قابل خواندن نیست.
     *
     * @param string        $handle  هندل
     * @param array<int,string> $sources فایل‌های خام (مسیر نسبت به ریشه قالب)
     * @return void
     */
    private static function registerInlineStyle(string $handle, array $sources): void
    {
        $css = self::readSources($sources);

        \wp_register_style($handle, false, [], BERYON_VERSION); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
        \wp_enqueue_style($handle);

        if ($css !== '') {
            \wp_add_inline_style($handle, $css);
        }

        error_log('[beryon] فایل استایل ' . $handle . ' پیدا نشد؛ CSS خام به‌صورت درون‌خطی چاپ شد.'); // phpcs:ignore
    }

    /**
     * ثبت اسکریپت درون‌خطی اضطراری وقتی هیچ فایل JS قابل خواندن نیست.
     *
     * @param string        $handle  هندل
     * @param array<int,string> $sources فایل‌های خام
     * @return void
     */
    private static function registerInlineScript(string $handle, array $sources): void
    {
        $js = self::readSources($sources);

        \wp_register_script($handle, false, [], BERYON_VERSION, true); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
        \wp_enqueue_script($handle);

        if ($js !== '') {
            \wp_add_inline_script($handle, $js);
        }

        error_log('[beryon] فایل اسکریپت ' . $handle . ' پیدا نشد؛ JS خام به‌صورت درون‌خطی چاپ شد.'); // phpcs:ignore
    }

    /**
     * خواندن و الحاق فایل‌های خام (بدون @import/@charset).
     *
     * @param array<int,string> $sources مسیرها نسبت به ریشه قالب
     * @return string
     */
    private static function readSources(array $sources): string
    {
        $out = '';

        foreach ($sources as $source) {
            $path = BERYON_PATH . '/' . ltrim((string) $source, '/');

            if (! is_readable($path)) {
                continue;
            }

            $body = (string) file_get_contents($path);
            $body = (string) preg_replace('/@import[^;]+;/u', '', $body);
            $body = str_replace('@charset "UTF-8";', '', $body);

            $out .= $body . "\n";
        }

        return trim($out);
    }

    /**
     * URL یک دارایی قالب (میان‌بر).
     *
     * @param string $path مسیر نسبی نسبت به public/assets یا ریشه قالب
     * @return string
     */
    public static function url(string $path): string
    {
        $path = ltrim($path, '/');

        // تصاویر و فونت‌ها از پوشه دارایی‌های عمومی سرو می‌شوند.
        if (preg_match('#^(images|fonts|css|js|media)/#', $path)) {
            return self::public_url($path);
        }

        return self::theme_url($path);
    }

    /**
     * نسخه یک دارایی (filemtime یا نسخه قالب).
     *
     * @param string $path مسیر نسبی
     * @return string
     */
    public static function version(string $path): string
    {
        $absolute = BERYON_PATH . '/public/assets/' . ltrim($path, '/');

        if (! is_readable($absolute)) {
            $absolute = BERYON_PATH . '/' . ltrim($path, '/');
        }

        return self::file_version($absolute);
    }

    /**
     * URL پوشه public/assets.
     *
     * @param string $path مسیر نسبی
     * @return string
     */
    public static function public_url(string $path): string
    {
        // عمداً مستقیم از get_template_directory_uri() ساخته می‌شود (نه توابع
        // لایه‌بالایی که ممکن است فیلتر/رفتار متفاوت داشته باشند) و همیشه با
        // slash انتهایی مطمئن: یک slash جاافتاده = ۴۰۴ خاموش = سایت بی‌استایل.
        return \trailingslashit(\get_template_directory_uri()) . 'public/assets/' . ltrim($path, '/');
    }

    /**
     * URL ریشه قالب.
     *
     * @param string $path مسیر نسبی
     * @return string
     */
    public static function theme_url(string $path = ''): string
    {
        $base = \trailingslashit(\get_template_directory_uri());

        return $path === '' ? $base : $base . ltrim($path, '/');
    }

    /**
     * @param string $absolute مسیر مطلق
     * @return string
     */
    private static function file_version(string $absolute): string
    {
        if ($absolute !== '' && is_readable($absolute)) {
            $time = (int) filemtime($absolute);

            if ($time > 0) {
                return (string) $time;
            }
        }

        return BERYON_VERSION;
    }

    /**
     * خواندن manifest بیلد Vite.
     *
     * @return array<string,mixed>
     */
    public static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $file = BERYON_PATH . '/public/assets/manifest.json';

        if (! is_readable($file)) {
            self::$manifest = [];

            return self::$manifest;
        }

        $data = json_decode((string) file_get_contents($file), true);

        self::$manifest = is_array($data) ? $data : [];

        return self::$manifest;
    }

    /* ============================================================== *
     * تصاویر
     * ============================================================== */

    /**
     * URL یک تصویر با اولویت: رسانه → آپلودها → دارایی قالب.
     *
     * @param string $name نام فایل (hero.webp)
     * @return string
     */
    public static function image_url(string $name): string
    {
        $name = ltrim($name, '/');

        if ($name === '') {
            return '';
        }

        // حافظه نهان در سطح درخواست
        static $seen = [];

        if (isset($seen[$name])) {
            return $seen[$name];
        }

        $url = '';

        // ۱) کتابخانه رسانه (با کلید متا که توسط درون‌ریز ثبت می‌شود)
        $attachment = self::attachment_by_name($name);

        if ($attachment > 0) {
            $src = \wp_get_attachment_image_url($attachment, 'large');

            if (! $src) {
                $src = \wp_get_attachment_url($attachment);
            }

            $url = (string) $src;
        }

        // ۲) پوشه beryon در آپلودها (برای جایگزینی دستی بدون دست‌زدن به قالب)
        if ($url === '') {
            $uploads = \wp_get_upload_dir();
            $candidate = \trailingslashit($uploads['basedir'] ?? '') . 'beryon/' . $name;

            if (is_readable($candidate)) {
                $url = \trailingslashit($uploads['baseurl'] ?? '') . 'beryon/' . $name;
            }
        }

        // ۳) دارایی قالب
        if ($url === '') {
            $candidate = BERYON_PATH . '/public/assets/images/' . $name;

            if (! is_readable($candidate)) {
                $candidate = BERYON_PATH . '/resources/images/' . $name;

                if (is_readable($candidate)) {
                    $url = self::theme_url('resources/images/' . $name);
                }
            } else {
                $url = self::public_url('images/' . $name);
            }
        }

        $seen[$name] = $url !== '' ? $url : self::public_url('images/' . $name);

        return $seen[$name];
    }

    /**
     * تگ <img> بهینه.
     *
     * @param string              $name نام تصویر
     * @param array<string,mixed> $args گزینه‌ها
     * @return string
     */
    public static function image_tag(string $name, array $args = []): string
    {
        $url = self::image_url($name);

        if ($url === '') {
            return '';
        }

        $attachment = self::attachment_by_name($name);
        $dimensions = self::dimensions($name, $attachment);

        $lazy     = ! isset($args['lazy']) ? true : (bool) $args['lazy'];
        $loading  = $lazy ? 'lazy' : 'eager';
        $priority = $lazy ? '' : ' fetchpriority="high"';

        // ترتیب صفت‌ها دقیقاً مطابق قالب HTML نهایی است (بدون تغییر مارک‌آپ).
        $attributes = [
            'src'      => esc_url($url),
            'alt'      => esc_attr((string) ($args['alt'] ?? '')),
            'loading'  => $loading,
            'width'    => (int) ($args['width'] ?? $dimensions[0]),
            'height'   => (int) ($args['height'] ?? $dimensions[1]),
            'decoding' => 'async',
        ];

        if (! empty($args['class'])) {
            $attributes['class'] = esc_attr((string) $args['class']);
        }

        if (! empty($args['id'])) {
            $attributes['id'] = esc_attr((string) $args['id']);
        }

        if (! empty($args['style'])) {
            $attributes['style'] = esc_attr((string) $args['style']);
        }

        if (! empty($args['sizes'])) {
            $attributes['sizes'] = esc_attr((string) $args['sizes']);
        }

        // srcset فقط برای تصاویر کتابخانه رسانه (تا WebP بودن خروجی تضمین شود)
        if ($attachment > 0 && empty($args['srcset']) && Settings::opt('performance.srcset', true)) {
            $srcset = self::srcset($attachment, (int) $attributes['width']);

            if ($srcset !== '') {
                $attributes['srcset'] = esc_attr($srcset);
                $attributes['sizes']  = $attributes['sizes'] ?? esc_attr('(max-width: 760px) 92vw, (max-width: 1180px) 46vw, 33vw');
            }
        } elseif (! empty($args['srcset'])) {
            $attributes['srcset'] = esc_attr((string) $args['srcset']);
        }

        foreach ((array) ($args['attr'] ?? []) as $key => $value) {
            $attributes[$key] = esc_attr((string) $value);
        }

        $html = '<img';

        foreach ($attributes as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $html .= ' ' . $key . '="' . $value . '"';
        }

        return $html . $priority . '>';
    }

    /**
     * ساخت srcset از اندازه‌های ثبت‌شده وردپرس.
     *
     * @param int $attachment شناسه پیوست
     * @param int $maxWidth   حداکثر عرض مورد نیاز
     * @return string
     */
    public static function srcset(int $attachment, int $maxWidth = 0): string
    {
        $meta = \wp_get_attachment_metadata($attachment);

        if (! is_array($meta) || empty($meta['sizes'])) {
            return '';
        }

        $base  = \trailingslashit((string) dirname(\wp_get_attachment_url($attachment)));
        $items = [];

        foreach ((array) $meta['sizes'] as $size) {
            $width = (int) ($size['width'] ?? 0);
            $file  = (string) ($size['file'] ?? '');

            if ($width < 1 || $file === '' || ($maxWidth > 0 && $width > $maxWidth * 2)) {
                continue;
            }

            $items[$width] = $base . $file . ' ' . $width . 'w';
        }

        if (count($items) < 2) {
            return '';
        }

        ksort($items);

        return implode(', ', $items);
    }

    /**
     * ابعاد یک تصویر (از پیوست یا فایل قالب).
     *
     * @param string $name       نام فایل
     * @param int    $attachment شناسه پیوست
     * @return array{0:int,1:int}
     */
    public static function dimensions(string $name, int $attachment = 0): array
    {
        if ($attachment > 0) {
            $meta = \wp_get_attachment_metadata($attachment);

            if (is_array($meta) && ! empty($meta['width']) && ! empty($meta['height'])) {
                return [(int) $meta['width'], (int) $meta['height']];
            }
        }

        foreach ([BERYON_PATH . '/public/assets/images/', BERYON_PATH . '/resources/images/'] as $dir) {
            $file = $dir . $name;

            if (is_readable($file)) {
                $size = @\getimagesize($file); // phpcs:ignore WordPress.PHP.NoSilencedErrors

                if (is_array($size) && ! empty($size[0])) {
                    return [(int) $size[0], (int) $size[1]];
                }
            }
        }

        return [0, 0];
    }

    /**
     * یافتن شناسه پیوست از روی نام فایل (با حافظه نهان Transient).
     *
     * @param string $name نام فایل
     * @return int
     */
    public static function attachment_by_name(string $name): int
    {
        static $found = [];

        if (isset($found[$name])) {
            return $found[$name];
        }

        $transient = 'beryon_img_' . substr(md5($name), 0, 16);
        $cached    = \get_transient($transient);

        if ($cached !== false) {
            $found[$name] = (int) $cached;

            return $found[$name];
        }

        global $wpdb;

        $like = '%' . $wpdb->esc_like($name) . '%';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_beryon_asset' AND meta_value = %s
                 LIMIT 1",
                $name
            )
        );

        if ($id < 1) {
            $id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts}
                     WHERE post_type = 'attachment' AND guid LIKE %s
                     ORDER BY ID DESC LIMIT 1",
                    $like
                )
            );
        }
        // phpcs:enable

        \set_transient($transient, $id, 6 * HOUR_IN_SECONDS);
        $found[$name] = $id;

        return $id;
    }

    /**
     * پاک‌سازی حافظه نهان نام تصاویر (پس از افزودن/حذف رسانه).
     *
     * @return void
     */
    public static function flush_image_cache(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_beryon_img_%' OR option_name LIKE '_transient_timeout_beryon_img_%'");
    }

    /**
     * آیا فونت خودمیمان (woff2) در قالب موجود است؟
     *
     * @return bool
     */
    public static function font_is_local(): bool
    {
        if (! (bool) Settings::opt('performance.fonts_self_host', true)) {
            return false;
        }

        return (bool) Settings::opt('performance.fonts_self_host', true);
    }
}
