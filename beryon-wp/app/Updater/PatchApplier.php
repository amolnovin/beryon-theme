<?php

/**
 * اعمال‌کننده Patch — هسته سیستم به‌روزرسانی امن قالب بریون
 * ------------------------------------------------------------------
 * قالب بسته (Patch Package):
 *   beryon-patch-1.1.0.zip
 *   ├── patch.json          (بیانیه/manifest — ساختار در PATCH_SPEC.md)
 *   ├── files/…             (فایل‌های جایگزین/جدید، با همان ساختار نسبی قالب)
 *   └── signature.txt       (اختیاری: hash_hmac sha256 از بایت‌های patch.json)
 *
 * مراحل اجرا: validate → stage → backup → apply → post-apply → log
 * هر خطا در مرحله apply باعث rollback خودکار از نسخه پشتیبان همان اجرا می‌شود.
 *
 * @package beryon
 */

namespace App\Updater;

defined('ABSPATH') || exit;

use App\Assets;
use App\Forms;
use App\Products;
use App\Security;
use App\Seo;
use App\Settings;

/**
 * کلاس اعمال Patch.
 */
final class PatchApplier
{
    /** نسخه ساختار بیانیه */
    public const FORMAT = 1;

    /** شناسه قالب */
    public const THEME = 'beryon';

    /** حد مجاز پیمایش مسیر */
    private const MAX_DEPTH = 12;

    /**
     * مسیر موقت استیجینگ (استاتیک تا در همه متدها در دسترس باشد).
     *
     * @var string
     */
    private static $staging = '';

    /**
     * تغییرات اعمال‌شده در اجرای جاری (برای rollback).
     *
     * @var array<int,array<string,string>>
     */
    private static $changes = [];

    /**
     * مسیر ریشه قالب (بدون اسلش انتهایی).
     *
     * @return string
     */
    public static function root(): string
    {
        return rtrim(BERYON_PATH, '/');
    }

    /**
     * مسیر پوشه Patchها.
     *
     * @return string
     */
    public static function patches_dir(): string
    {
        $dir = self::root() . '/public/patches';

        wp_mkdir_p($dir);

        return $dir;
    }

    /**
     * مسیر پوشه پشتیبان‌ها.
     *
     * @return string
     */
    public static function backups_dir(): string
    {
        $dir = self::patches_dir() . '/backups';

        wp_mkdir_p($dir);

        return $dir;
    }

    /**
     * بازکردن و اعتبارسنجی یک بسته Patch.
     *
     * @param string $zip_path مسیر فایل zip
     * @return array{ok:bool,error?:string,code?:string,manifest?:array<string,mixed>,zip?:\ZipArchive}
     */
    public static function inspect(string $zip_path): array
    {
        if (! class_exists('\ZipArchive')) {
            return self::fail('zip_missing', \__('افزونه Zip در PHP فعال نیست؛ برای اعمال Patch لازم است.', BERYON_TD));
        }

        if (! is_readable($zip_path)) {
            return self::fail('unreadable', \__('فایل Patch قابل خواندن نیست.', BERYON_TD));
        }

        $max = (int) Settings::opt('updater.max_patch_size', 52428800);

        if ($max > 0 && (int) filesize($zip_path) > $max) {
            return self::fail('too_large', sprintf(\__('حجم Patch بیش از حد مجاز است (%s).', BERYON_TD), size_format($max)));
        }

        $zip = new \ZipArchive();

        if ($zip->open($zip_path) !== true) {
            return self::fail('bad_zip', \__('فایل zip معتبر نیست.', BERYON_TD));
        }

        $manifest_json = $zip->getFromName('patch.json');
        $manifest_dir  = '';

        if ($manifest_json === false) {
            // پشتیبانی از بسته‌هایی که بیانیه در زیرپوشه قرار دارد
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);

                if (preg_match('#^([^/]+/)patch\.json$#', $name, $match)) {
                    $manifest_json = $zip->getFromName($name);
                    $manifest_dir  = (string) $match[1];
                    break;
                }
            }
        }

        if (! is_string($manifest_json) || $manifest_json === '') {
            $zip->close();

            return self::fail('no_manifest', \__('فایل patch.json در بسته یافت نشد.', BERYON_TD));
        }

        $manifest = json_decode($manifest_json, true);

        if (! is_array($manifest)) {
            $zip->close();

            return self::fail('bad_manifest', \__('patch.json قابل تجزیه نیست (JSON نامعتبر).', BERYON_TD));
        }

        // امضای جدا (signature.txt کنار patch.json)؛ در نبود آن، کلید signature مانیفست بررسی می‌شود.
        $signature_txt = $zip->getFromName($manifest_dir . 'signature.txt');

        $check = self::validate_manifest($manifest, $manifest_json, is_string($signature_txt) ? $signature_txt : '');

        if (! $check['ok']) {
            $zip->close();

            return $check;
        }

        return ['ok' => true, 'manifest' => $manifest, 'zip' => $zip, 'raw_manifest' => $manifest_json];
    }

    /**
     * اعتبارسنجی بیانیه Patch.
     *
     * @param array<string,mixed> $manifest  بیانیه
     * @param string              $raw       متن خام patch.json (برای امضا)
     * @param string              $signature محتوای signature.txt بسته (در صورت وجود)
     * @return array{ok:bool,error?:string,code?:string}
     */
    public static function validate_manifest(array $manifest, string $raw = '', string $signature = ''): array
    {
        if ((int) ($manifest['format'] ?? 0) !== self::FORMAT) {
            return self::fail('format', sprintf(\__('نسخه ساختار Patch پشتیبانی نمی‌شود (انتظار: %d).', BERYON_TD), self::FORMAT));
        }

        if ((string) ($manifest['theme'] ?? '') !== self::THEME) {
            return self::fail('theme', \__('این Patch برای قالب بریون ساخته نشده است.', BERYON_TD));
        }

        $version = (string) ($manifest['version'] ?? '');

        if (! preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.\-]+)?$/', $version)) {
            return self::fail('version', \__('نسخه Patch نامعتبر است (باید semantic version باشد).', BERYON_TD));
        }

        $requires = (array) ($manifest['requires'] ?? []);

        if (! empty($requires['php']) && version_compare(PHP_VERSION, (string) $requires['php'], '<')) {
            return self::fail('php', sprintf(\__('این Patch به PHP %s یا بالاتر نیاز دارد (نسخه جاری: %s).', BERYON_TD), $requires['php'], PHP_VERSION));
        }

        if (! empty($requires['wordpress'])) {
            global $wp_version;

            if (version_compare((string) $wp_version, (string) $requires['wordpress'], '<')) {
                return self::fail('wordpress', sprintf(\__('این Patch به وردپرس %s یا بالاتر نیاز دارد.', BERYON_TD), $requires['wordpress']));
            }
        }

        $min_theme = (string) ($requires['theme_min'] ?? '');

        if ($min_theme !== '' && version_compare(BERYON_VERSION, $min_theme, '<')) {
            return self::fail('theme_min', sprintf(\__('ابتدا قالب را به نسخه %s ارتقا دهید.', BERYON_TD), $min_theme));
        }

        $files = (array) ($manifest['files'] ?? []);

        if ($files === []) {
            return self::fail('empty', \__('هیچ فایلی در این Patch تعریف نشده است.', BERYON_TD));
        }

        $allow_ext  = (array) Settings::opt('updater.patch_ext_allowlist', []);
        $allow_slug = (array) Settings::opt('updater.patch_slug_allowlist', []);
        $max_file   = (int) Settings::opt('updater.max_file_size', 15728640);

        foreach ($files as $index => $file) {
            if (! is_array($file)) {
                return self::fail('files', sprintf(\__('ردیف %d در بخش files نامعتبر است.', BERYON_TD), $index + 1));
            }

            $operation = (string) ($file['op'] ?? 'copy');

            if (! in_array($operation, ['copy', 'delete', 'mkdir'], true)) {
                return self::fail('op', sprintf(\__('عملیات «%s» پشتیبانی نمی‌شود.', BERYON_TD), $operation));
            }

            $path = (string) ($file['path'] ?? '');

            if (! self::safe_path($path)) {
                return self::fail('path', sprintf(\__('مسیر «%s» نامعتبر یا ناامن است.', BERYON_TD), $path));
            }

            if (! self::allowed_slug($path, $allow_slug)) {
                return self::fail('scope', sprintf(\__('مسیر «%s» بیرون از محدوده مجاز قالب است.', BERYON_TD), $path));
            }

            if ($operation === 'copy') {
                $source = (string) ($file['from'] ?? '');

                if (! self::safe_path($source) || ! str_starts_with(ltrim($source, '/'), 'files/')) {
                    return self::fail('source', sprintf(\__('منبع «%s» باید داخل پوشه files/ باشد.', BERYON_TD), $source));
                }

                $hash = (string) ($file['sha256'] ?? '');

                if (! preg_match('/^[a-f0-9]{64}$/', $hash)) {
                    return self::fail('hash', sprintf(\__('checksum فایل «%s» نامعتبر است.', BERYON_TD), $path));
                }

                if ($max_file > 0 && (int) ($file['size'] ?? 0) > $max_file) {
                    return self::fail('size', sprintf(\__('فایل «%s» بزرگ‌تر از حد مجاز است.', BERYON_TD), $path));
                }

                if ($allow_ext !== [] && ! self::allowed_ext($path, $allow_ext)) {
                    return self::fail('ext', sprintf(\__('پسوند فایل «%s» در فهرست مجاز نیست.', BERYON_TD), $path));
                }
            }
        }

        $sql = (array) ($manifest['sql'] ?? []);

        foreach ($sql as $statement) {
            if (! is_string($statement)) {
                return self::fail('sql', \__('دستور SQL نامعتبر است.', BERYON_TD));
            }

            if (preg_match('/\b(drop\s+database|truncate\s+wp_|delete\s+from\s+wp_users)\b/i', $statement)) {
                return self::fail('sql', \__('دستور SQL مخرب تشخیص داده شد.', BERYON_TD));
            }
        }

        // امضا: اگر کلید امضا در سایت تنظیم شده باشد، بسته باید امضای معتبر داشته باشد.
        //  ۱) signature.txt → HMAC-SHA256 روی بایت‌های خام patch.json
        //  ۲) کلید signature داخل مانیفست → HMAC روی مانیفستِ بدون همان کلید
        //     (signing_payload؛ همان چیزی که Updater::create امضا می‌کند).
        // پیش‌تر فقط حالت ۲ خوانده می‌شد و با متن خامِ «دارای» کلید signature
        // مقایسه می‌شد؛ در نتیجه هیچ بسته امضاشده‌ای تأیید نمی‌شد.
        $signature_key = (string) Settings::opt('updater.signature_key', '');

        if ($signature_key !== '' && $raw !== '') {
            $provided = strtolower(trim($signature));

            if ($provided !== '') {
                $expected = hash_hmac('sha256', $raw, $signature_key);
            } else {
                $provided = strtolower(trim((string) ($manifest['signature'] ?? '')));
                $expected = hash_hmac('sha256', self::signing_payload($manifest), $signature_key);
            }

            if ($provided === '' || ! hash_equals($expected, $provided)) {
                return self::fail('signature', \__('امضای Patch تأیید نشد.', BERYON_TD));
            }
        }

        return ['ok' => true];
    }

    /**
     * متن یکتای مانیفست برای امضا: مانیفست بدون کلید signature با همان
     * قالب‌بندی‌ای که Updater::create در patch.json می‌نویسد.
     *
     * @param array<string,mixed> $manifest بیانیه
     * @return string
     */
    public static function signing_payload(array $manifest): string
    {
        unset($manifest['signature']);

        return (string) \wp_json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * اجرای کامل یک Patch.
     *
     * @param string               $zip_path مسیر فایل zip
     * @param array<string,mixed>  $options  گزینه‌ها (dry_run, skip_backup, actor)
     * @return array<string,mixed>
     */
    public static function apply(string $zip_path, array $options = []): array
    {
        $options = wp_parse_args(
            $options,
            [
                'dry_run'     => false,
                'skip_backup' => false,
                'actor'       => \get_current_user_id(),
                'source'      => 'upload',
            ]
        );

        $inspect = self::inspect($zip_path);

        if (! $inspect['ok']) {
            self::log($inspect, $options);

            return $inspect;
        }

        $manifest = $inspect['manifest'];
        $zip      = $inspect['zip'];

        if (! self::root_writable()) {
            $zip->close();

            return self::fail('readonly', \__('پوشه قالب قابل نوشتن نیست؛ دسترسی فایل‌ها را بررسی کنید.', BERYON_TD));
        }

        // ۱) استخراج به استیجینگ
        $stage = self::stage($zip, $manifest);
        $zip->close();

        if (! $stage['ok']) {
            self::cleanup_staging();
            self::log($stage, $options);

            return $stage;
        }

        // ۲) پیش‌نمایش تغییرات
        $plan = self::build_plan($manifest);

        if ($options['dry_run']) {
            self::cleanup_staging();

            return [
                'ok'       => true,
                'dry_run'  => true,
                'version'  => (string) ($manifest['version'] ?? ''),
                'plan'     => $plan,
                'message'  => \__('بررسی موفق بود؛ هیچ تغییری اعمال نشد.', BERYON_TD),
            ];
        }

        // ۳) پشتیبان‌گیری
        $backup = '';

        if (! $options['skip_backup']) {
            $backup_result = self::create_backup($plan, $manifest);

            if (! $backup_result['ok']) {
                self::cleanup_staging();
                self::log($backup_result, $options);

                return $backup_result;
            }

            $backup = (string) $backup_result['file'];
        }

        // ۴) اعمال
        $result = self::run_operations($manifest, $stage);

        // ۵) پس از اعمال یا rollback
        if ($result['ok']) {
            self::post_apply($manifest);
        } elseif ($backup !== '') {
            self::restore_backup($backup, false);
            $result['rolled_back'] = true;
        }

        self::cleanup_staging();

        $result['version'] = $result['ok'] ? (string) ($manifest['version'] ?? '') : BERYON_VERSION;
        $result['backup']  = $backup;
        $result['plan']    = $plan;

        self::log($result, $options, $manifest);

        return $result;
    }

    /**
     * استخراج امن فایل‌ها به پوشه موقت.
     *
     * @param \ZipArchive         $zip      آرشیو
     * @param array<string,mixed> $manifest بیانیه
     * @return array<string,mixed>
     */
    private static function stage(\ZipArchive $zip, array $manifest): array
    {
        $uploads = \wp_get_upload_dir();
        $base    = \trailingslashit($uploads['basedir'] ?? WP_CONTENT_DIR . '/uploads') . 'beryon-updater/';

        wp_mkdir_p($base);

        $staging = $base . 'stage-' . \wp_generate_password(10, false, false) . '-' . time();

        if (! wp_mkdir_p($staging)) {
            return self::fail('stage', \__('ساخت پوشه موقت ناموفق بود.', BERYON_TD));
        }

        self::$staging = $staging;

        $allow_ext = (array) Settings::opt('updater.patch_ext_allowlist', []);
        $max_file  = (int) Settings::opt('updater.max_file_size', 15728640);
        $prefix    = self::manifest_prefix($zip);

        foreach ((array) $manifest['files'] as $file) {
            if (($file['op'] ?? 'copy') !== 'copy') {
                continue;
            }

            $source = ltrim((string) ($file['from'] ?? ''), '/');
            $entry  = $prefix . $source;
            $target = $staging . '/' . str_replace('/', DIRECTORY_SEPARATOR, $source);

            if (! self::safe_path($source) || ! str_starts_with($source, 'files/')) {
                return self::fail('path', sprintf(\__('منبع نامعتبر: %s', BERYON_TD), $source));
            }

            if ($allow_ext !== [] && ! self::allowed_ext($source, $allow_ext)) {
                return self::fail('ext', sprintf(\__('پسوند «%s» مجاز نیست.', BERYON_TD), $source));
            }

            $stream = $zip->getStream($entry);

            if (! is_resource($stream)) {
                return self::fail('missing', sprintf(\__('فایل «%s» داخل بسته Patch یافت نشد.', BERYON_TD), $entry));
            }

            wp_mkdir_p(dirname($target));

            $handle = fopen($target, 'wb'); // phpcs:ignore WordPress.WP.AlternativeFunctions

            if (! is_resource($handle)) {
                fclose($stream); // phpcs:ignore WordPress.WP.AlternativeFunctions

                return self::fail('write', sprintf(\__('نوشتن فایل «%s» ناموفق بود.', BERYON_TD), $source));
            }

            $written = 0;

            while (! feof($stream)) {
                $chunk = fread($stream, 8192); // phpcs:ignore WordPress.WP.AlternativeFunctions

                if ($chunk === false) {
                    break;
                }

                $written += strlen($chunk);

                if ($max_file > 0 && $written > $max_file) {
                    fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions
                    fclose($stream); // phpcs:ignore WordPress.WP.AlternativeFunctions

                    return self::fail('size', sprintf(\__('فایل «%s» از حد مجاز بزرگ‌تر است.', BERYON_TD), $source));
                }

                fwrite($handle, $chunk); // phpcs:ignore WordPress.WP.AlternativeFunctions
            }

            fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions
            fclose($stream); // phpcs:ignore WordPress.WP.AlternativeFunctions

            $expected = strtolower((string) ($file['sha256'] ?? ''));
            $actual   = (string) hash_file('sha256', $target);

            if (! hash_equals($expected, $actual)) {
                return self::fail('checksum', sprintf(\__('checksum فایل «%s» مطابقت نکرد.', BERYON_TD), $file['path'] ?? $source));
            }

            if (isset($file['mode']) && preg_match('/^0?[0-7]{3,4}$/', (string) $file['mode'])) {
                chmod($target, octdec((string) $file['mode'])); // phpcs:ignore WordPress.WP.AlternativeFunctions
            } else {
                chmod($target, 0644); // phpcs:ignore WordPress.WP.AlternativeFunctions
            }
        }

        return ['ok' => true, 'staging' => $staging];
    }

    /**
     * ساخت برنامه تغییرات (برای نمایش و پشتیبان‌گیری).
     *
     * @param array<string,mixed> $manifest بیانیه
     * @return array{copy:int,delete:int,mkdir:int,paths:array<int,string>,exists:array<int,string>,new:array<int,string>,rows:array<int,array{op:string,path:string}>}
     */
    public static function build_plan(array $manifest): array
    {
        $plan = ['copy' => 0, 'delete' => 0, 'mkdir' => 0, 'paths' => [], 'exists' => [], 'new' => [], 'rows' => []];

        foreach ((array) $manifest['files'] as $file) {
            $operation = (string) ($file['op'] ?? 'copy');
            $path      = (string) ($file['path'] ?? '');
            $absolute  = self::root() . '/' . ltrim($path, '/');

            $plan[$operation] = ($plan[$operation] ?? 0) + 1;
            $plan['paths'][]  = $path;

            if (file_exists($absolute)) {
                $plan['exists'][] = $path;
            } else {
                $plan['new'][] = $path;
            }

            // ردیف نمایشی (پیام پنل و WP-CLI): add = فایل تازه، replace = جایگزینی
            $label = $operation;

            if ($operation === 'copy') {
                $label = file_exists($absolute) ? 'replace' : 'add';
            }

            $plan['rows'][] = ['op' => $label, 'path' => $path];
        }

        // دستورهای SQL هم بخشی از تغییرات‌اند و باید در پیش‌نمایش دیده شوند.
        foreach ((array) ($manifest['sql'] ?? []) as $statement) {
            if (is_string($statement) && trim($statement) !== '') {
                $plan['rows'][] = ['op' => 'sql', 'path' => trim($statement)];
            }
        }

        return $plan;
    }

    /**
     * اجرای عملیات‌های فایل.
     *
     * @param array<string,mixed> $manifest بیانیه
     * @param array<string,mixed> $stage    نتیجه استیجینگ
     * @return array<string,mixed>
     */
    private static function run_operations(array $manifest, array $stage): array
    {
        $staging = (string) ($stage['staging'] ?? '');
        $applied = 0;

        foreach ((array) $manifest['files'] as $file) {
            $operation = (string) ($file['op'] ?? 'copy');
            $path      = ltrim((string) ($file['path'] ?? ''), '/');
            $target    = self::root() . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);

            if (! self::safe_path($path)) {
                return self::fail('path', sprintf(\__('مسیر نامعتبر هنگام اعمال: %s', BERYON_TD), $path));
            }

            if ($operation === 'mkdir') {
                if (! is_dir($target) && ! wp_mkdir_p($target)) {
                    return self::fail('mkdir', sprintf(\__('ساخت پوشه «%s» ناموفق بود.', BERYON_TD), $path));
                }

                $applied++;

                continue;
            }

            if ($operation === 'delete') {
                if (file_exists($target)) {
                    if (! @unlink($target)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
                        return self::fail('delete', sprintf(\__('حذف «%s» ناموفق بود.', BERYON_TD), $path));
                    }

                    self::record_change($path, 'deleted');
                }

                $applied++;

                continue;
            }

            // copy
            $source = $staging . '/' . str_replace('/', DIRECTORY_SEPARATOR, ltrim((string) ($file['from'] ?? ''), '/'));

            if (! is_readable($source)) {
                return self::fail('source', sprintf(\__('فایل منبع «%s» در استیجینگ نیست.', BERYON_TD), $file['from'] ?? ''));
            }

            wp_mkdir_p(dirname($target));

            // جایگزینی اتمیک: نخست در همان پوشه کپی، سپس rename
            $temporary = $target . '.tmp-' . \wp_generate_password(6, false, false);

            if (! copy($source, $temporary)) {
                return self::fail('copy', sprintf(\__('کپی «%s» ناموفق بود.', BERYON_TD), $path));
            }

            $mode = isset($file['mode']) && preg_match('/^0?[0-7]{3,4}$/', (string) $file['mode'])
                ? (int) octdec((string) $file['mode'])
                : 0644;

            chmod($temporary, $mode); // phpcs:ignore WordPress.WP.AlternativeFunctions

            $existed = file_exists($target);

            if (! rename($temporary, $target)) {
                @unlink($temporary); // phpcs:ignore WordPress.PHP.NoSilencedErrors

                return self::fail('rename', sprintf(\__('جای‌گذاری «%s» ناموفق بود.', BERYON_TD), $path));
            }

            self::record_change($path, $existed ? 'replaced' : 'added');
            $applied++;
        }

        // دستورات SQL
        foreach ((array) ($manifest['sql'] ?? []) as $statement) {
            self::run_sql((string) $statement);
        }

        return ['ok' => true, 'applied' => $applied, 'message' => sprintf(\__('Patch با موفقیت اعمال شد (%d فایل).', BERYON_TD), $applied)];
    }

    /**
     * ثبت تغییر برای rollback.
     *
     * @param string $path   مسیر
     * @param string $action نوع تغییر
     * @return void
     */
    private static function record_change(string $path, string $action): void
    {
        self::$changes[] = ['path' => $path, 'action' => $action];
    }

    /**
     * اجرای یک دستور SQL ساده (فقط ALTER/CREATE/UPDATE/INSERT روی جداول قالب).
     *
     * @param string $statement دستور
     * @return bool
     */
    private static function run_sql(string $statement): bool
    {
        global $wpdb;

        $statement = trim($statement);

        if ($statement === '' || ! preg_match('/^(alter|create|update|insert|delete)\b/i', $statement)) {
            return false;
        }

        if (preg_match('/\b(drop\s+table|truncate)\b/i', $statement) && ! str_contains(strtolower($statement), 'beryon')) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->query($statement) !== false;
    }

    /**
     * اقدامات پس از اعمال موفق.
     *
     * @param array<string,mixed> $manifest بیانیه
     * @return void
     */
    public static function post_apply(array $manifest): void
    {
        $version = (string) ($manifest['version'] ?? BERYON_VERSION);

        // نام کامل: داخل فضای نام App\Updater نام کوتاه «Updater» به کلاس ناموجود
        // App\Updater\Updater تفسیر می‌شد و اعمال Patch پس از جایگزینی فایل‌ها با
        // خطای مرگبار متوقف می‌شد (بدون ثبت نسخه و لاگ).
        \App\Updater::set_version($version);
        Settings::flush();

        // پاک‌سازی کش‌ها
        Assets::flush_image_cache();
        Seo::flush_sitemap();
        Products::flush_cache();

        if (function_exists('opcache_reset')) {
            @\opcache_reset(); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }

        \flush_rewrite_rules(false);

        // کدِ در حافظه هنوز نسخه پیشین است؛ با حذف این گزینه، وردپرس در درخواست بعدی
        // قواعد بازنویسی را با فایل‌های تازه دوباره می‌سازد.
        \delete_option('rewrite_rules');

        // به‌روزرسانی اثر انگشت امنیتی
        Security::fingerprint();

        /**
         * پس از اعمال موفق Patch.
         *
         * @param string $version
         * @param array  $manifest
         * @param array  $changes
         */
        \do_action('beryon/patch/applied', $version, $manifest, self::$changes);

        self::$changes = [];
    }

    /* ============================================================== *
     * پشتیبان‌گیری و بازگردانی
     * ============================================================== */

    /**
     * ساخت پشتیبان از فایل‌هایی که قرار است تغییر کنند.
     *
     * @param array<string,mixed> $plan     برنامه
     * @param array<string,mixed> $manifest بیانیه
     * @return array<string,mixed>
     */
    public static function create_backup(array $plan, array $manifest): array
    {
        if (! class_exists('\ZipArchive')) {
            return self::fail('zip_missing', \__('برای پشتیبان‌گیری به افزونه Zip نیاز است.', BERYON_TD));
        }

        $name = sprintf('beryon-backup-%s-to-%s-%s.zip', BERYON_VERSION, (string) ($manifest['version'] ?? 'patch'), \gmdate('Ymd-His'));
        $file = self::backups_dir() . '/' . $name;

        $zip = new \ZipArchive();

        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return self::fail('backup', \__('ساخت فایل پشتیبان ناموفق بود.', BERYON_TD));
        }

        $zip->addFromString(
            'backup.json',
            (string) wp_json_encode(
                [
                    'created'     => time(),
                    'from_version' => BERYON_VERSION,
                    'to_version'  => (string) ($manifest['version'] ?? ''),
                    'patch_id'    => (string) ($manifest['id'] ?? ''),
                    'paths'       => (array) ($plan['paths'] ?? []),
                ],
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            )
        );

        foreach ((array) ($plan['paths'] ?? []) as $path) {
            $absolute = self::root() . '/' . ltrim((string) $path, '/');

            if (is_file($absolute) && is_readable($absolute)) {
                $zip->addFile($absolute, 'files/' . ltrim((string) $path, '/'));
            }
        }

        $zip->close();

        self::prune_backups();

        return ['ok' => true, 'file' => $file, 'name' => $name];
    }

    /**
     * بازگردانی یک پشتیبان.
     *
     * @param string $file  مسیر zip پشتیبان
     * @param bool   $reset آیا نسخه قالب هم برگردانده شود
     * @return array<string,mixed>
     */
    public static function restore_backup(string $file, bool $reset = true): array
    {
        if (! is_readable($file) || ! class_exists('\ZipArchive')) {
            return self::fail('backup', \__('فایل پشتیبان در دسترس نیست.', BERYON_TD));
        }

        $zip = new \ZipArchive();

        if ($zip->open($file) !== true) {
            return self::fail('backup', \__('فایل پشتیبان معتبر نیست.', BERYON_TD));
        }

        $meta = json_decode((string) $zip->getFromName('backup.json'), true);
        $restored = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);

            if (! str_starts_with($entry, 'files/')) {
                continue;
            }

            $relative = substr($entry, strlen('files/'));

            if (! self::safe_path($relative)) {
                continue;
            }

            $target   = self::root() . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $contents = $zip->getFromIndex($i);

            if ($contents === false) {
                continue;
            }

            wp_mkdir_p(dirname($target));

            if (file_put_contents($target, $contents) !== false) { // phpcs:ignore WordPress.WP.AlternativeFunctions
                chmod($target, 0644); // phpcs:ignore WordPress.WP.AlternativeFunctions
                $restored++;
            }
        }

        $zip->close();

        if ($reset && is_array($meta) && ! empty($meta['from_version'])) {
            \App\Updater::set_version((string) $meta['from_version'], \__('بازگردانی از پشتیبان', BERYON_TD));
        }

        Assets::flush_image_cache();
        Seo::flush_sitemap();
        Products::flush_cache();
        Security::fingerprint();
        \flush_rewrite_rules(false);

        // مثل post_apply: کدِ در حافظه هنوز نسخه پیش از بازگردانی است؛ با حذف این گزینه
        // قواعد بازنویسی در درخواست بعدی با فایل‌های بازگردانده‌شده ساخته می‌شوند.
        \delete_option('rewrite_rules');

        self::log(['ok' => true, 'action' => 'rollback', 'restored' => $restored, 'backup' => basename($file)], ['source' => 'rollback']);

        return ['ok' => true, 'restored' => $restored, 'message' => sprintf(\__('بازگردانی انجام شد (%d فایل).', BERYON_TD), $restored)];
    }

    /**
     * فهرست پشتیبان‌ها.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function backups(): array
    {
        $dir   = self::backups_dir();
        $items = [];

        foreach ((array) glob($dir . '/*.zip') as $file) {
            $items[] = [
                'name' => basename((string) $file),
                'file' => (string) $file,
                'size' => (int) filesize((string) $file),
                'time' => (int) filemtime((string) $file),
            ];
        }

        usort(
            $items,
            /**
             * @param array<string,mixed> $a
             * @param array<string,mixed> $b
             */
            static fn (array $a, array $b): int => ($b['time'] ?? 0) <=> ($a['time'] ?? 0)
        );

        return $items;
    }

    /**
     * حذف پشتیبان‌های مازاد.
     *
     * @return void
     */
    public static function prune_backups(): void
    {
        $keep = max(1, (int) Settings::opt('updater.backups_keep', 5));
        $all  = self::backups();

        foreach (array_slice($all, $keep) as $backup) {
            if (isset($backup['file']) && is_writable((string) $backup['file'])) {
                @unlink((string) $backup['file']); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            }
        }
    }

    /**
     * پاک‌سازی پوشه استیجینگ.
     *
     * @return void
     */
    public static function cleanup_staging(): void
    {
        if (self::$staging === '') {
            return;
        }

        self::delete_tree(self::$staging);
        self::$staging = '';
    }

    /**
     * حذف بازگشتی یک پوشه.
     *
     * @param string $dir مسیر
     * @return void
     */
    public static function delete_tree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach ((array) scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;

            if (is_dir($path)) {
                self::delete_tree($path);
            } else {
                @unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            }
        }

        @rmdir($dir); // phpcs:ignore WordPress.PHP.NoSilencedErrors
    }

    /* ============================================================== *
     * ابزارهای اعتبارسنجی
     * ============================================================== */

    /**
     * امنیت مسیر (بدون ..، بدون مسیر مطلق، بدون کاراکتر نامعتبر).
     *
     * @param string $path مسیر
     * @return bool
     */
    public static function safe_path(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        if ($path === '' || strlen($path) > 400) {
            return false;
        }

        if (preg_match('#^(/|[A-Za-z]:)#', $path)) {
            return false; // مسیر مطلق
        }

        if (str_contains($path, "\0") || preg_match('/[\x00-\x1F]/', $path)) {
            return false;
        }

        $parts = explode('/', trim($path, '/'));

        if (count($parts) > self::MAX_DEPTH) {
            return false;
        }

        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..' || str_contains($part, ':')) {
                return false;
            }
        }

        return true;
    }

    /**
     * بررسی مجاز بودن مسیر بر اساس allowlist.
     *
     * @param string              $path    مسیر
     * @param array<int,string>   $allowed پیشوندهای مجاز
     * @return bool
     */
    public static function allowed_slug(string $path, array $allowed): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($allowed === []) {
            return true;
        }

        foreach ($allowed as $prefix) {
            $prefix = (string) $prefix;

            if ($prefix === '') {
                continue;
            }

            if (str_ends_with($prefix, '/')) {
                if (str_starts_with($path, $prefix)) {
                    return true;
                }
            } elseif ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * بررسی پسوند مجاز.
     *
     * @param string            $path    مسیر
     * @param array<int,string> $allowed پسوندهای مجاز
     * @return bool
     */
    public static function allowed_ext(string $path, array $allowed): bool
    {
        $base = basename(str_replace('\\', '/', $path));

        // فایل‌های بدون پسوند مشخص (مانند .htaccess) با نام کامل مقایسه می‌شوند
        foreach ($allowed as $ext) {
            $ext = strtolower(ltrim((string) $ext, '.'));

            if ($ext === '') {
                continue;
            }

            if ($base === '.' . $ext || str_ends_with(strtolower($base), '.' . $ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * آیا ریشه قالب قابل نوشتن است؟
     *
     * @return bool
     */
    public static function root_writable(): bool
    {
        return wp_is_writable(self::root() . '/app') || wp_is_writable(self::root());
    }

    /**
     * پیشوند داخلی بسته (در صورت وجود یک پوشه بالادستی).
     *
     * @param \ZipArchive $zip آرشیو
     * @return string
     */
    private static function manifest_prefix(\ZipArchive $zip): string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (preg_match('#^([^/]+)/patch\.json$#', $name, $match)) {
                return $match[1] . '/';
            }

            if ($name === 'patch.json') {
                return '';
            }
        }

        return '';
    }

    /**
     * ثبت رویداد در لاگ.
     *
     * @param array<string,mixed> $result   نتیجه
     * @param array<string,mixed> $options  گزینه‌ها
     * @param array<string,mixed> $manifest بیانیه
     * @return void
     */
    public static function log(array $result, array $options = [], array $manifest = []): void
    {
        \App\Updater::log(
            [
                'time'      => time(),
                'ok'        => (bool) ($result['ok'] ?? false),
                'action'    => (string) ($result['action'] ?? ($options['dry_run'] ?? false ? 'dry_run' : 'apply')),
                'code'      => (string) ($result['code'] ?? ''),
                'error'     => (string) ($result['error'] ?? ''),
                'message'   => (string) ($result['message'] ?? ''),
                'version'   => (string) ($manifest['version'] ?? ($result['version'] ?? '')),
                'patch_id'  => (string) ($manifest['id'] ?? ''),
                'label'     => (string) ($manifest['label'] ?? ''),
                'source'    => (string) ($options['source'] ?? 'upload'),
                'actor'     => (int) ($options['actor'] ?? \get_current_user_id()),
                'ip'        => Forms::client_ip(),
                'applied'   => (int) ($result['applied'] ?? 0),
                'backup'    => (string) ($result['backup'] ?? ''),
            ]
        );
    }

    /**
     * ساخت پاسخ خطا.
     *
     * @param string $code    کد
     * @param string $message پیام
     * @return array{ok:false,code:string,error:string,message:string}
     */
    private static function fail(string $code, string $message): array
    {
        return ['ok' => false, 'code' => $code, 'error' => $message, 'message' => $message];
    }
}
