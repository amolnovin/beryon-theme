<?php

/**
 * امنیت قالب بریون — هدرها، سخت‌سازی و یکپارچگی فایل‌ها
 * ------------------------------------------------------------------
 * - هدرهای امنیتی: CSP، X-Frame-Options، X-Content-Type-Options، Referrer-Policy،
 *   Permissions-Policy، HSTS (اختیاری)
 * - پنهان‌سازی نسخه وردپرس/قالب، پیام خطای عمومی لاگین
 * - مسدودسازی User-Agentهای مخرب شناخته‌شده و تلاش اسکن مسیرهای حساس
 * - ثبت «اثر انگشت» فایل‌های هسته قالب و هشدار در صورت تغییر (File Integrity)
 * - نکته: فایل .htaccess هم در بسته وجود دارد؛ این کلاس برای میزبان‌های Nginx/LiteSpeed
 *   هم کار می‌کند چون هدرها از PHP ارسال می‌شوند.
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * کلاس امنیت.
 */
final class Security
{
    /** نام Option اثر انگشت فایل‌ها */
    public const FINGERPRINT = 'beryon_file_fingerprint';

    /** فایل‌های هسته‌ای که یکپارچگی آن‌ها پایش می‌شود */
    private const CORE_FILES = [
        'functions.php',
        'index.php',
        'style.css',
        'app/helpers.php',
        'app/Setup.php',
        'app/Assets.php',
        'app/Forms.php',
        'app/Security.php',
        'app/Updater.php',
        'app/Updater/PatchApplier.php',
        'app/Admin/Settings.php',
        'resources/views/layouts/app.blade.php',
        'resources/views/partials/header.blade.php',
        'resources/views/partials/footer.blade.php',
    ];

    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        if (! \is_admin()) {
            \add_action('send_headers', [self::class, 'headers'], 20);
            \add_action('template_redirect', [self::class, 'guard'], 1);
        }

        \add_action('init', [self::class, 'harden']);
        \add_action('admin_init', [self::class, 'integrity_check']);
        \add_filter('login_errors', [self::class, 'login_errors']);

        if (Settings::opt('security.block_bad_bots', true)) {
            \add_action('parse_request', [self::class, 'block_bad_agents'], 1);
        }
    }

    /**
     * ارسال هدرهای امنیتی.
     *
     * @return void
     */
    public static function headers(): void
    {
        if (! Settings::opt('security.headers', true) || \headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), interest-cohort=()');
        header_remove('X-Powered-By');

        if (Settings::opt('security.hsts', false) && \is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        if (Settings::opt('security.csp_enabled', true)) {
            $csp = self::content_security_policy();

            if (Settings::opt('security.csp_report_only', false)) {
                header('Content-Security-Policy-Report-Only: ' . $csp);
            } else {
                header('Content-Security-Policy: ' . $csp);
            }
        }

        /**
         * فیلتر هدرهای امنیتی اضافی.
         *
         * @param array $headers کلید => مقدار
         */
        foreach ((array) \apply_filters('beryon/security/headers', []) as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    /**
     * ساخت سیاست امنیت محتوا (CSP) متناسب با قالب.
     *
     * @return string
     */
    public static function content_security_policy(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "media-src 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-src https://www.google.com https://maps.google.com",
            "connect-src 'self'",
        ];

        // فقط روی HTTPS: در سایت HTTP-only این دستور همه دارایی‌ها را به HTTPS
        // ارتقا می‌دهد و اگر گواهی نصب نباشد، سایت کاملاً سفید می‌شود.
        if (\is_ssl()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        $extra = trim((string) Settings::opt('security.csp_extra', ''));

        if ($extra !== '') {
            $directives[] = $extra;
        }

        /**
         * فیلتر دستورهای CSP.
         *
         * @param array $directives
         */
        return implode('; ', array_filter((array) \apply_filters('beryon/security/csp', $directives)));
    }

    /**
     * سخت‌سازی عمومی.
     *
     * @return void
     */
    public static function harden(): void
    {
        if (Settings::opt('security.hide_version', true)) {
            \add_filter('the_generator', '__return_empty_string');
            \remove_action('wp_head', 'wp_generator');

            // حذف شماره نسخه از صف استایل/اسکریپت
            \add_filter(
                'style_loader_src',
                /** @param string $src */
                static function (string $src): string {
                    if (! str_contains($src, 'ver=')) {
                        return $src;
                    }

                    if (str_contains($src, 'themes/beryon')) {
                        return $src; // نسخه قالب برای کش‌باستینگ لازم است
                    }

                    return (string) \remove_query_arg('ver', $src);
                }
            );

            \add_filter(
                'script_loader_src',
                /** @param string $src */
                static function (string $src): string {
                    if (! str_contains($src, 'ver=') || str_contains($src, 'themes/beryon')) {
                        return $src;
                    }

                    return (string) \remove_query_arg('ver', $src);
                }
            );
        }

        if (Settings::opt('performance.disable_xmlrpc', false)) {
            \add_filter('xmlrpc_enabled', '__return_false');
        }

        if (Settings::opt('performance.disable_rest_for_guests', false)) {
            \add_filter(
                'rest_authentication_errors',
                /** @param mixed $result */
                static function ($result) {
                    if ($result !== null) {
                        return $result;
                    }

                    return \is_user_logged_in() ? $result : new \WP_Error(
                        'rest_disabled',
                        \__('دسترسی به REST برای کاربران مهمان غیرفعال است.', BERYON_TD),
                        ['status' => 401]
                    );
                }
            );
        }

        // غیرفعال کردن ویرایشگر فایل درون پوسته (اگر در wp-config تعریف نشده باشد)
        if (! defined('DISALLOW_FILE_EDIT')) {
            \define('DISALLOW_FILE_EDIT', true);
        }
    }

    /**
     * پیام خطای عمومی صفحه ورود.
     *
     * @return string
     */
    public static function login_errors(): string
    {
        if (! Settings::opt('security.login_errors_generic', true)) {
            return '';
        }

        return \__('نام کاربری یا رمز عبور نادرست است.', BERYON_TD);
    }

    /**
     * محافظت در برابر تلاش‌های اسکن مسیرهای حساس.
     *
     * @return void
     */
    public static function guard(): void
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? \sanitize_text_field(\wp_unslash($_SERVER['REQUEST_URI'])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        if ($uri === '') {
            return;
        }

        $blocked = [
            '/wp-config.php',
            '/xmlrpc.php?rsd',
            '/readme.html',
            '/license.txt',
            '/wp-admin/install.php',
            '/eval-stdin',
            'base64_decode(',
            '/vendor/',
            '/.git/',
            '/.env',
        ];

        $lower = strtolower($uri);

        foreach ($blocked as $pattern) {
            if (str_contains($lower, strtolower($pattern))) {
                self::log_attempt($uri);

                \status_header(403);
                \nocache_headers();

                \wp_die(
                    esc_html__('دسترسی غیرمجاز.', BERYON_TD),
                    403,
                    ['response' => 403, 'back_link' => false]
                );
            }
        }
    }

    /**
     * مسدودسازی User-Agentهای مخرب شناخته‌شده.
     *
     * @param \WP $wp شی اصلی وردپرس
     * @return void
     */
    public static function block_bad_agents(\WP $wp): void
    {
        $agent = Forms::server_var('HTTP_USER_AGENT');

        if ($agent === '') {
            return;
        }

        $bad = ['sqlmap', 'nikto', 'masscan', 'zgrab', 'nmap', 'python-requests/1', 'libwww-perl', 'havij', 'acunetix', 'wpscan', 'fscanner'];

        /**
         * فیلتر فهرست User-Agentهای مسدود.
         *
         * @param array $bad
         */
        $bad = (array) \apply_filters('beryon/security/bad_agents', $bad);

        $lower = strtolower($agent);

        foreach ($bad as $needle) {
            if ($needle !== '' && str_contains($lower, strtolower($needle))) {
                self::log_attempt($agent);

                \status_header(403);
                \nocache_headers();

                // exit خام = صفحه سفید؛ پاسخ خوانا با کد ۴۰۳ برمی‌گردد.
                \wp_die(
                    \esc_html__('دسترسی غیرمجاز.', BERYON_TD),
                    403,
                    ['response' => 403, 'back_link' => false]
                );
            }
        }
    }

    /**
     * ثبت تلاش مشکوک (با محدودسازی نرخ تا اشباع نشدن جدول options).
     *
     * @param string $detail جزئیات
     * @return void
     */
    private static function log_attempt(string $detail): void
    {
        $key   = 'beryon_sec_hits_' . \gmdate('YmdH');
        $count = (int) \get_transient($key);

        \set_transient($key, min($count + 1, 999999), 2 * HOUR_IN_SECONDS);

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            \error_log('[beryon][security] ' . \mb_substr($detail, 0, 200) . ' ip=' . Forms::client_ip()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        }

        /**
         * رویداد تلاش مشکوک.
         *
         * @param string $detail
         */
        \do_action('beryon/security/attempt', $detail);
    }

    /* ============================================================== *
     * یکپارچگی فایل‌ها (File Integrity Monitoring)
     * ============================================================== */

    /**
     * ساخت/به‌روزرسانی اثر انگشت فایل‌ها.
     *
     * @param array<int,string> $files فایل‌ها
     * @return void
     */
    public static function fingerprint(array $files = []): void
    {
        $files = $files !== [] ? $files : self::CORE_FILES;
        $map   = [];

        foreach ($files as $relative) {
            $path = BERYON_PATH . '/' . ltrim($relative, '/');

            if (is_readable($path)) {
                $map[$relative] = hash_file('sha256', $path);
            }
        }

        \update_option(
            self::FINGERPRINT,
            [
                'version' => BERYON_VERSION,
                'created' => time(),
                'files'   => $map,
            ],
            false
        );
    }

    /**
     * مقایسه فایل‌های جاری با اثر انگشت ثبت‌شده.
     *
     * @return array{modified:array<int,string>,missing:array<int,string>,ok:bool,total:int}
     */
    public static function verify(): array
    {
        $stored = (array) \get_option(self::FINGERPRINT, []);
        $files  = (array) ($stored['files'] ?? []);

        if ($files === []) {
            return ['modified' => [], 'missing' => [], 'ok' => true, 'total' => 0];
        }

        $modified = [];
        $missing  = [];

        foreach ($files as $relative => $hash) {
            $path = BERYON_PATH . '/' . ltrim((string) $relative, '/');

            if (! is_readable($path)) {
                $missing[] = (string) $relative;

                continue;
            }

            if (hash_file('sha256', $path) !== $hash) {
                $modified[] = (string) $relative;
            }
        }

        return ['modified' => $modified, 'missing' => $missing, 'ok' => $modified === [] && $missing === [], 'total' => count($files)];
    }

    /**
     * بررسی دوره‌ای یکپارچگی و نمایش هشدار مدیریتی.
     *
     * @return void
     */
    public static function integrity_check(): void
    {
        if (! Settings::opt('security.file_integrity', true) || ! \current_user_can('manage_options')) {
            return;
        }

        if ((int) \get_transient('beryon_integrity_checked') > 0) {
            return;
        }

        \set_transient('beryon_integrity_checked', 1, 12 * HOUR_IN_SECONDS);

        $result = self::verify();

        if ($result['ok']) {
            return;
        }

        \set_transient(
            'beryon_integrity_alert',
            [
                'modified' => $result['modified'],
                'missing'  => $result['missing'],
                'time'     => time(),
            ],
            DAY_IN_SECONDS
        );

        \add_action('admin_notices', [self::class, 'integrity_notice']);
    }

    /**
     * هشدار یکپارچگی در پیشخوان.
     *
     * @return void
     */
    public static function integrity_notice(): void
    {
        $alert = \get_transient('beryon_integrity_alert');

        if (! is_array($alert)) {
            return;
        }

        $files = array_merge((array) ($alert['modified'] ?? []), (array) ($alert['missing'] ?? []));
        $url   = \admin_url('themes.php?page=beryon-updates');

        echo '<div class="notice notice-error"><p dir="rtl"><strong>' .
            esc_html__('هشدار امنیتی قالب بریون:', BERYON_TD) . '</strong> ' .
            esc_html__('تغییر در فایل‌های هسته‌ای قالب تشخیص داده شد:', BERYON_TD) . ' <code>' .
            esc_html(implode(', ', array_slice($files, 0, 6))) . '</code> — ' .
            '<a href="' . esc_url($url) . '">' . esc_html__('بررسی و بازگردانی', BERYON_TD) . '</a></p></div>';
    }
}
