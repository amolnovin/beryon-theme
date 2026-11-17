<?php

/**
 * مدیریت به‌روزرسانی قالب بریون — Patch-Based Update System
 * ------------------------------------------------------------------
 * مسئولیت‌ها:
 *   - نگهداری نسخه جاری و تاریخچه نسخه‌ها
 *   - لاگ رویدادها (فایل JSONL + Option)
 *   - بررسی به‌روزرسانی از فید راه دور (با تأیید hash)
 *   - زمان‌بندی بررسی خودکار (wp-cron)
 *   - ساخت بسته Patch (برای تیم توسعه)
 *   - رابط کاربری پنل در App\Admin\Settings
 *
 * استاندارد بسته و بیانیه: docs/PATCH_SPEC.md
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

use App\Updater\PatchApplier;

/**
 * کلاس به‌روزرسانی.
 */
final class Updater
{
    /** Option نسخه جاری */
    public const VERSION_OPTION = 'beryon_theme_version';

    /** Option تاریخچه نسخه‌ها */
    public const HISTORY_OPTION = 'beryon_theme_versions';

    /** Option نتیجه آخرین بررسی راه دور (تا ۱.۰.۵؛ فقط برای پاک‌سازی نگه داشته شده) */
    public const FEED_OPTION = 'beryon_update_feed';

    /** نام فایل لاگ */
    public const LOG_FILE = 'patch-log.jsonl';

    /** هوک cron بررسی راه دور (تا ۱.۰.۵؛ فقط برای پاک‌سازی نگه داشته شده) */
    public const CRON_HOOK = 'beryon_update_check';

    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        // از ۱.۰.۶ به‌روزرسانی فقط با بارگذاری فایل zip بسته Patch انجام می‌شود؛ بررسی و
        // دانلود از سرور راه دور (فید JSON، cron و اعلان «به‌روزرسانی موجود است») حذف شده است.
        \add_action('after_switch_theme', [self::class, 'install']);
        \add_action('admin_init', [self::class, 'sync_version_option']);
        \add_action('wp_ajax_beryon_apply_patch', [self::class, 'ajax_apply']);
        \add_action('wp_ajax_beryon_rollback', [self::class, 'ajax_rollback']);
        \add_action('wp_ajax_beryon_integrity', [self::class, 'ajax_integrity']);
        \add_action('wp_ajax_beryon_fingerprint', [self::class, 'ajax_fingerprint']);

        if (defined('WP_CLI') && WP_CLI) {
            \add_action('cli_init', [self::class, 'register_cli']);
        }
    }

    /**
     * همگام‌سازی خودکار Option نسخه با نسخه واقعی فایل‌ها.
     *
     * اگر فایل‌های قالب (با zip/FTP/Patch) جایگزین شوند ولی Option نسخه کهنه
     * بماند، پنل و بررسی‌های Patch نسخه اشتباه می‌بینند؛ اینجا خودبه‌خود
     * اصلاح می‌شود.
     *
     * @return void
     */
    public static function sync_version_option(): void
    {
        if (\get_option(self::VERSION_OPTION, BERYON_THEME_VERSION) === BERYON_THEME_VERSION) {
            return;
        }

        \update_option(self::VERSION_OPTION, BERYON_THEME_VERSION, false);

        // فایل‌های قالب عوض شده‌اند؛ قواعد بازنویسی (مثل سایت‌مپ) در درخواست بعدی
        // با کد تازه از نو ساخته می‌شوند.
        \delete_option('rewrite_rules');

        $history   = (array) \get_option(self::HISTORY_OPTION, []);
        $history[] = [
            'version' => BERYON_THEME_VERSION,
            'time'    => time(),
            'note'    => \__('همگام‌سازی خودکار نسخه پس از جایگزینی فایل‌ها', BERYON_TD),
        ];

        \update_option(self::HISTORY_OPTION, $history, false);
    }

    /**
     * نصب/راه‌اندازی اولیه.
     *
     * @return void
     */
    public static function install(): void
    {
        if (\get_option(self::VERSION_OPTION) === false) {
            \add_option(self::VERSION_OPTION, BERYON_THEME_VERSION, '', false);
        }

        if (\get_option(self::HISTORY_OPTION) === false) {
            \add_option(
                self::HISTORY_OPTION,
                [
                    [
                        'version' => BERYON_THEME_VERSION,
                        'time'    => time(),
                        'note'    => \__('نصب اولیه قالب', BERYON_TD),
                    ],
                ],
                '',
                false
            );
        }

        wp_mkdir_p(PatchApplier::patches_dir());
        self::protect_dirs();
        Security::fingerprint();
    }

    /**
     * محافظت از پوشه‌های Patch (جلوگیری از فهرست‌شدن و اجرای مستقیم).
     *
     * @return void
     */
    private static function protect_dirs(): void
    {
        foreach ([PatchApplier::patches_dir(), PatchApplier::backups_dir()] as $dir) {
            foreach (['index.php' => "<?php\n// Silence is golden.\n", '.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n"] as $name => $content) {
                $file = $dir . '/' . $name;

                if (! file_exists($file) && wp_is_writable($dir)) {
                    file_put_contents($file, $content); // phpcs:ignore WordPress.WP.AlternativeFunctions
                }
            }
        }
    }

    /**
     * نسخه جاری قالب (از Option؛ در صورت نبود، ثابت فایل).
     *
     * @return string
     */
    public static function version(): string
    {
        $version = (string) \get_option(self::VERSION_OPTION, BERYON_THEME_VERSION);

        return $version !== '' ? $version : BERYON_THEME_VERSION;
    }

    /**
     * ثبت نسخه جدید.
     *
     * @param string $version نسخه
     * @param string $note    یادداشت
     * @return void
     */
    public static function set_version(string $version, string $note = ''): void
    {
        \update_option(self::VERSION_OPTION, $version, false);

        $history   = (array) \get_option(self::HISTORY_OPTION, []);
        $history[] = [
            'version' => $version,
            'time'    => time(),
            'note'    => $note !== '' ? $note : \__('به‌روزرسانی از طریق Patch', BERYON_TD),
        ];

        \update_option(self::HISTORY_OPTION, array_slice($history, -25), false);
    }

    /**
     * تاریخچه نسخه‌ها.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function history(): array
    {
        $history = (array) \get_option(self::HISTORY_OPTION, []);

        return array_reverse($history);
    }

    /* ============================================================== *
     * لاگ
     * ============================================================== */

    /**
     * مسیر فایل لاگ.
     *
     * @return string
     */
    public static function log_path(): string
    {
        return PatchApplier::patches_dir() . '/' . self::LOG_FILE;
    }

    /**
     * ثبت یک رویداد.
     *
     * @param array<string,mixed> $entry رویداد
     * @return void
     */
    public static function log(array $entry): void
    {
        $entry['time']          = (int) ($entry['time'] ?? time());
        $entry['theme_version'] = self::version();
        $entry['site']          = \home_url('/');

        $file = self::log_path();

        if (wp_is_writable(dirname($file))) {
            file_put_contents($file, wp_json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX); // phpcs:ignore WordPress.WP.AlternativeFunctions
        }

        $recent   = (array) \get_option('beryon_patch_log', []);
        $recent[] = $entry;

        \update_option('beryon_patch_log', array_slice($recent, -100), false);

        /**
         * رویداد لاگ Patch.
         *
         * @param array $entry
         */
        \do_action('beryon/updater/log', $entry);
    }

    /**
     * خواندن لاگ.
     *
     * @param int $limit تعداد
     * @return array<int,array<string,mixed>>
     */
    public static function logs(int $limit = 50): array
    {
        $entries = [];
        $file    = self::log_path();

        if (is_readable($file)) {
            $lines = array_slice(array_filter(explode("\n", (string) file_get_contents($file))), -$limit * 2); // phpcs:ignore WordPress.WP.AlternativeFunctions

            foreach ($lines as $line) {
                $decoded = json_decode((string) $line, true);

                if (is_array($decoded)) {
                    $entries[] = $decoded;
                }
            }
        }

        if ($entries === []) {
            $entries = (array) \get_option('beryon_patch_log', []);
        }

        return array_slice(array_reverse($entries), 0, $limit);
    }

    /* ============================================================== *
     * پاک‌سازی به‌روزرسانی راه دور (حذف‌شده در ۱.۰.۶)
     * ============================================================== */

    /**
     * برچیدن باقی‌مانده‌های «به‌روزرسانی از راه دور» نسخه‌های ≤ ۱.۰.۵.
     *
     * رویداد cron بررسی فید، نتیجه ذخیره‌شده آخرین بررسی و اعلان «به‌روزرسانی آماده
     * نصب» حذف می‌شوند. بسته‌های Patch فقط از راه بارگذاری فایل zip نصب می‌شوند.
     *
     * @return void
     */
    public static function disable_remote(): void
    {
        if (\wp_next_scheduled(self::CRON_HOOK)) {
            \wp_clear_scheduled_hook(self::CRON_HOOK);
        }

        \delete_option(self::FEED_OPTION);
        \delete_transient('beryon_update_pending');
    }

    /* ============================================================== *
     * AJAX پنل
     * ============================================================== */

    /**
     * اعمال Patch (AJAX).
     *
     * @return void
     */
    public static function ajax_apply(): void
    {
        self::guard();

        // فقط فایل بارگذاری‌شده پذیرفته می‌شود (منبع راه دور در ۱.۰.۶ حذف شد).
        if (! isset($_FILES['patch']) || empty($_FILES['patch']['tmp_name'])) { // phpcs:ignore WordPress.Security.NonceVerification
            \wp_send_json_error(['message' => \__('فایلی ارسال نشد.', BERYON_TD)], 400);
        }

        // بررسی نوع و اندازه
        if ((int) ($_FILES['patch']['size'] ?? 0) > (int) Settings::opt('updater.max_patch_size', 52428800)) { // phpcs:ignore WordPress.Security.NonceVerification
            \wp_send_json_error(['message' => \__('حجم فایل بیش از حد مجاز است.', BERYON_TD)], 413);
        }

        $file = (string) $_FILES['patch']['tmp_name']; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput

        if (! class_exists('\ZipArchive')) {
            \wp_send_json_error(['message' => \__('افزونه Zip در PHP فعال نیست.', BERYON_TD)], 500);
        }

        $zip = new \ZipArchive();

        if ($zip->open($file) !== true) {
            \wp_send_json_error(['message' => \__('فایل zip معتبر نیست.', BERYON_TD)], 400);
        }

        // انتقال به پوشه Patch (برای ماندگاری و لاگ)
        $target = PatchApplier::patches_dir() . '/uploaded-' . \gmdate('Ymd-His') . '.zip';
        $zip->close();

        if (! \move_uploaded_file($file, $target) && ! \rename($file, $target)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
            $target = $file;
        }

        $file = $target;

        $dry = isset($_POST['dry_run']) && (string) $_POST['dry_run'] === '1'; // phpcs:ignore WordPress.Security.NonceVerification

        $result = PatchApplier::apply(
            $file,
            [
                'dry_run' => $dry,
                'source'  => 'upload',
                'actor'   => \get_current_user_id(),
            ]
        );

        if (empty($result['ok'])) {
            \wp_send_json_error($result, 422);
        }

        \wp_send_json_success(
            [
                'version' => (string) ($result['version'] ?? self::version()),
                'message' => (string) ($result['message'] ?? \__('Patch اعمال شد.', BERYON_TD)),
                'applied' => (int) ($result['applied'] ?? 0),
                'backup'  => (string) ($result['backup'] ?? ''),
                'plan'    => $result['plan'] ?? [],
            ]
        );
    }

    /**
     * بازگردانی (AJAX).
     *
     * @return void
     */
    public static function ajax_rollback(): void
    {
        self::guard();

        // دکمه «بازگردانی» پنل نام فایل را با کلید file می‌فرستد (data-file)؛ کلید
        // backup برای سازگاری با فراخوان‌های پیشین همچنان پذیرفته می‌شود.
        $name = '';

        foreach (['backup', 'file'] as $param) {
            if (isset($_POST[$param]) && is_string($_POST[$param]) && $_POST[$param] !== '') { // phpcs:ignore WordPress.Security.NonceVerification
                $name = \sanitize_file_name(\wp_unslash($_POST[$param])); // phpcs:ignore WordPress.Security.NonceVerification
                break;
            }
        }

        if ($name === '') {
            \wp_send_json_error(['message' => \__('پشتیبانی انتخاب نشده است.', BERYON_TD)], 400);
        }

        $file = PatchApplier::backups_dir() . '/' . $name;

        if (! is_readable($file)) {
            \wp_send_json_error(['message' => \__('فایل پشتیبان یافت نشد.', BERYON_TD)], 404);
        }

        $result = PatchApplier::restore_backup($file);

        if (empty($result['ok'])) {
            \wp_send_json_error($result, 500);
        }

        \wp_send_json_success($result);
    }

    /**
     * بررسی یکپارچگی فایل‌ها (AJAX).
     *
     * @return void
     */
    public static function ajax_integrity(): void
    {
        self::guard();

        $action = isset($_POST['integrity_action']) ? \sanitize_key(\wp_unslash($_POST['integrity_action'])) : 'verify'; // phpcs:ignore WordPress.Security.NonceVerification

        if ($action === 'refresh') {
            Security::fingerprint();

            \wp_send_json_success(self::integrity_payload(\__('اثر انگشت فایل‌ها به‌روزرسانی شد.', BERYON_TD)));
        }

        \wp_send_json_success(self::integrity_payload());
    }

    /**
     * بازسازی اثر انگشت فایل‌ها (AJAX) — دکمه «بازسازی اثر انگشت» صفحه به‌روزرسانی.
     *
     * @return void
     */
    public static function ajax_fingerprint(): void
    {
        self::guard();

        Security::fingerprint();

        \wp_send_json_success(self::integrity_payload(\__('اثر انگشت فایل‌ها بازسازی شد.', BERYON_TD)));
    }

    /**
     * پاسخ یکپارچگی به شکلی که اسکریپت پنل نمایش می‌دهد
     * (message, total, modified, log در سطح اول؛ result برای سازگاری می‌ماند).
     *
     * @param string $message پیام دلخواه
     * @return array<string,mixed>
     */
    private static function integrity_payload(string $message = ''): array
    {
        $result  = Security::verify();
        $changed = array_merge(
            array_map(static function ($path): string {
                return 'M ' . (string) $path;
            }, (array) ($result['modified'] ?? [])),
            array_map(static function ($path): string {
                return 'D ' . (string) $path;
            }, (array) ($result['missing'] ?? []))
        );

        if ($message === '') {
            $message = ! empty($result['ok'])
                ? \__('فایل‌های هسته قالب بدون تغییرند.', BERYON_TD)
                : \__('برخی فایل‌های هسته قالب تغییر کرده یا حذف شده‌اند.', BERYON_TD);
        }

        return [
            'message'  => $message,
            'total'    => (int) ($result['total'] ?? 0),
            'modified' => count($changed),
            'log'      => $changed,
            'result'   => $result,
        ];
    }

    /**
     * بررسی دسترسی و nonce برای همه عملیات پنل.
     *
     * @return void
     */
    private static function guard(): void
    {
        $nonce = isset($_REQUEST['nonce']) ? \sanitize_text_field(\wp_unslash($_REQUEST['nonce'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        if ($nonce === '' || ! \wp_verify_nonce($nonce, 'beryon_admin')) {
            \wp_send_json_error(['message' => \__('نشست امنیتی نامعتبر است؛ صفحه را تازه کنید.', BERYON_TD)], 403);
        }

        if (! \current_user_can('manage_options')) {
            \wp_send_json_error(['message' => \__('اجازه دسترسی ندارید.', BERYON_TD)], 403);
        }
    }

    /* ============================================================== *
     * ساخت بسته Patch (برای تیم توسعه)
     * ============================================================== */

    /**
     * ساخت یک بسته Patch از روی فایل‌های قالب.
     *
     * @param array<int,string>   $paths   مسیرهای نسبی
     * @param string              $version نسخه جدید
     * @param array<string,mixed> $meta    اطلاعات تکمیلی (label, notes, requires)
     * @return array<string,mixed>
     */
    public static function create(array $paths, string $version, array $meta = []): array
    {
        if (! class_exists('\ZipArchive')) {
            return ['ok' => false, 'message' => \__('افزونه Zip فعال نیست.', BERYON_TD)];
        }

        if ($paths === []) {
            return ['ok' => false, 'message' => \__('فایلی برای بسته‌بندی انتخاب نشده است.', BERYON_TD)];
        }

        if (! preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.\-]+)?$/', $version)) {
            return ['ok' => false, 'message' => \__('نسخه معتبر نیست (semantic version).', BERYON_TD)];
        }

        $files  = [];
        $errors = [];

        foreach ($paths as $relative) {
            $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');

            if (! PatchApplier::safe_path($relative)) {
                $errors[] = $relative;

                continue;
            }

            $absolute = BERYON_THEME_PATH . '/' . $relative;

            if (! is_file($absolute) || ! is_readable($absolute)) {
                $errors[] = $relative;

                continue;
            }

            $files[] = [
                'path'   => $relative,
                'from'   => 'files/' . $relative,
                'op'     => 'copy',
                'sha256' => hash_file('sha256', $absolute),
                'size'   => (int) filesize($absolute),
                'mode'   => '0644',
            ];
        }

        if ($files === []) {
            return ['ok' => false, 'message' => \__('هیچ فایل معتبری یافت نشد.', BERYON_TD), 'errors' => $errors];
        }

        // حداقل PHP بسته همان حداقلِ خود قالب است (سربرگ «Requires PHP» در style.css).
        // پیش‌تر «8.1» سخت‌کد بود و بسته‌ای که خود قالب می‌ساخت روی PHP 7.4 و 8.0 با
        // خطای «این Patch به PHP 8.1 یا بالاتر نیاز دارد» رد می‌شد.
        $requires_php = (string) \wp_get_theme(\get_template())->get('RequiresPHP');

        if ($requires_php === '') {
            $requires_php = '7.4';
        }

        $manifest = [
            'format'       => PatchApplier::FORMAT,
            'theme'        => PatchApplier::THEME,
            'id'           => 'beryon-patch-' . $version . '-' . \gmdate('YmdHis'),
            'version'      => $version,
            'from_version' => self::version(),
            'channel'      => (string) ($meta['channel'] ?? 'stable'),
            'label'        => (string) ($meta['label'] ?? sprintf(\__('به‌روزرسانی %s', BERYON_TD), $version)),
            'notes'        => (string) ($meta['notes'] ?? ''),
            'author'       => (string) ($meta['author'] ?? 'Beryon Web Team'),
            'released_at'  => \gmdate('c'),
            'requires'     => [
                'php'       => (string) ($meta['requires']['php'] ?? $requires_php),
                'wordpress' => (string) ($meta['requires']['wordpress'] ?? '6.4'),
                'theme_min' => (string) ($meta['requires']['theme_min'] ?? self::version()),
            ],
            'files'        => $files,
            'post_apply'   => ['flush_rewrites', 'clean_caches', 'refingerprint'],
        ];

        // امضا روی مانیفستِ «بدون کلید signature» محاسبه می‌شود؛ PatchApplier هنگام
        // اعتبارسنجی همان متن را با signing_payload بازسازی و مقایسه می‌کند.
        $key = (string) Settings::opt('updater.signature_key', '');

        if ($key !== '') {
            $manifest['signature'] = hash_hmac('sha256', PatchApplier::signing_payload($manifest), $key);
        }

        $json = (string) wp_json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $name = 'beryon-patch-' . $version . '.zip';
        $file = PatchApplier::patches_dir() . '/' . $name;

        $zip = new \ZipArchive();

        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return ['ok' => false, 'message' => \__('ساخت فایل zip ناموفق بود.', BERYON_TD)];
        }

        $zip->addFromString('patch.json', $json);
        $zip->addFromString('README.txt', self::patch_readme($manifest));

        foreach ($files as $entry) {
            $zip->addFile(BERYON_THEME_PATH . '/' . $entry['path'], 'files/' . $entry['path']);
        }

        $zip->close();

        self::log(
            [
                'ok'      => true,
                'action'  => 'create',
                'version' => $version,
                'message' => sprintf(\__('بسته Patch ساخته شد: %s', BERYON_TD), $name),
                'applied' => count($files),
            ]
        );

        return [
            'ok'       => true,
            'file'     => $file,
            'name'     => $name,
            'sha256'   => hash_file('sha256', $file),
            'size'     => (int) filesize($file),
            'files'    => count($files),
            'errors'   => $errors,
            'manifest' => $manifest,
        ];
    }

    /**
     * متن راهنمای داخل بسته Patch.
     *
     * @param array<string,mixed> $manifest بیانیه
     * @return string
     */
    private static function patch_readme(array $manifest): string
    {
        $lines = [
            'Beryon Theme Patch Package',
            '============================',
            'id       : ' . (string) $manifest['id'],
            'version  : ' . (string) $manifest['version'],
            'label    : ' . (string) $manifest['label'],
            'released : ' . (string) $manifest['released_at'],
            'files    : ' . count((array) $manifest['files']),
            '',
            'روش نصب:',
            '1) پیشخوان وردپرس → نمایش → تنظیمات بریون → برگه «به‌روزرسانی (Patch)»',
            '2) همین فایل zip را بارگذاری و «بررسی» سپس «اعمال Patch» را بزنید.',
            '3) در صورت بروز خطا، از بخش پشتیبان‌ها «بازگردانی» کنید.',
            '',
            'یا با WP-CLI:',
            '   wp beryon patch apply /path/to/' . basename((string) ($manifest['id'] ?? 'patch')) . '.zip',
        ];

        return implode("\n", $lines) . "\n";
    }

    /**
     * متن راهنمای ساختار Patch (از فایل docs یا الگوی درون‌خطی).
     *
     * @return string
     */
    public static function docs(): string
    {
        $file = BERYON_THEME_PATH . '/docs/PATCH_SPEC.md';

        if (is_readable($file)) {
            return (string) file_get_contents($file);
        }

        return implode("\n", [
            'ساختار بسته Patch قالب بریون',
            '=============================',
            'patch.zip',
            '├── patch.json        ← بیانیه (اجباری؛ در ریشه یا یک سطح پوشه)',
            '├── README.txt',
            '└── files/',
            '    └── app/… , resources/views/… , public/assets/…',
            '',
            'فیلدهای patch.json:',
            '  format:1  theme:"beryon"  id  version(semver)  from_version  channel',
            '  label  notes  author  released_at',
            '  requires: { php, wordpress, theme_min }',
            '  files: [ { path, from, op:copy|delete|mkdir, sha256, size, mode } ]',
            '  sql: [ … ]        ← دستورهای مخرب (DROP/TRUNCATE/DELETE بدون WHERE) رد می‌شوند',
            '  post_apply: [ … ]  signature (HMAC-SHA256 اختیاری)',
            '',
            'جریان اجرا: اعتبارسنجی → Stage در uploads/beryon-updater → پشتیبان‌گیری →',
            '            اعمال اتمی (tmp + rename) → post_apply یا بازگردانی خودکار.',
            '',
            'ساخت بسته:  wp beryon patch build --version=1.1.0 --dir=app --out=/tmp/patch.zip',
            'اعتبارسنجی: wp beryon patch inspect /tmp/patch.zip',
            'اعمال آزمایشی: wp beryon patch apply /tmp/patch.zip --dry-run',
        ]) . "\n";
    }

    /**
     * ثبت دستورات WP-CLI.
     *
     * @return void
     */
    public static function register_cli(): void
    {
        if (class_exists(Cli::class)) {
            \WP_CLI::add_command('beryon', Cli::class);
        }
    }
}
