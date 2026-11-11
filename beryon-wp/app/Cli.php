<?php

/**
 * دستورات WP-CLI قالب بریون
 * ------------------------------------------------------------------
 *   wp beryon version
 *   wp beryon seed [--force] [--media] [--skip-pages] [--skip-posts]
 *   wp beryon options list [--group=seo]
 *   wp beryon options get <key>
 *   wp beryon options set <key> <value>
 *   wp beryon options reset [--yes]
 *   wp beryon cache flush
 *   wp beryon patch inspect <zip>
 *   wp beryon patch apply <zip> [--dry-run] [--yes]
 *   wp beryon patch build --version=1.1.0 [--label=…] [--out=…] [--dir=…]
 *   wp beryon patch backups
 *   wp beryon patch rollback <file> [--yes]
 *   wp beryon patch feed [--check]
 *   wp beryon integrity [--refresh]
 *   wp beryon doctor
 *   wp beryon images [--regenerate]
 *
 * @package beryon
 */

namespace App;

use App\Updater\PatchApplier;

defined('ABSPATH') || exit;

/**
 * کلاس دستورات CLI.
 */
final class Cli
{
    /**
     * نمایش نسخه قالب.
     *
     * @subcommand version
     *
     * @return void
     */
    public function version(): void
    {
        \WP_CLI::line('beryon theme: ' . BERYON_VERSION);
        \WP_CLI::line('installed  : ' . Updater::version());
        \WP_CLI::line('path       : ' . BERYON_PATH);
    }

    /**
     * درج داده‌های نمونه قالب (محصولات، دسته‌ها، صفحات، مقالات، منوها).
     *
     * ## OPTIONS
     *
     * [--force]
     * : بازنویسی محتوای موجود.
     *
     * [--media]
     * : بارگذاری تصاویر پوشه قالب به کتابخانه رسانه.
     *
     * [--skip-pages]
     * : صفحات درج نشوند.
     *
     * [--skip-posts]
     * : مقالات درج نشوند.
     *
     * @subcommand seed
     *
     * @param array<int,string>            $args       آرگومان‌ها
     * @param array<string,string|bool>    $assoc_args گزینه‌ها
     * @return void
     */
    public function seed(array $args, array $assoc_args = []): void
    {
        $result = Seeder::run([
            'force'      => ! empty($assoc_args['force']),
            'media'      => ! empty($assoc_args['media']),
            'skip_pages' => ! empty($assoc_args['skip-pages']),
            'skip_posts' => ! empty($assoc_args['skip-posts']),
        ]);

        \WP_CLI::success(
            \sprintf(
                'seed done → categories:%d products:%d pages:%d posts:%d media:%d menus:%d',
                $result['categories'],
                $result['products'],
                $result['pages'],
                $result['posts'],
                $result['media'],
                $result['menus']
            )
        );
    }

    /**
     * مدیریت تنظیمات قالب.
     *
     * ## OPTIONS
     *
     * <action>
     * : list|get|set|reset
     *
     * [<key>]
     * : کلید به‌صورت group.name
     *
     * [<value>]
     * : مقدار جدید (برای set)
     *
     * [--group=<group>]
     * : فیلتر گروه در list
     *
     * [--yes]
     * : تأیید بدون پرسش
     *
     * @subcommand options
     *
     * @param array<int,string>         $args       آرگومان‌ها
     * @param array<string,string|bool> $assoc_args گزینه‌ها
     * @return void
     */
    public function options(array $args, array $assoc_args = []): void
    {
        $action = (string) ($args[0] ?? 'list');

        if ($action === 'reset') {
            \WP_CLI::confirm('بازنشانی همه تنظیمات به پیش‌فرض؟', $assoc_args);
            \update_option(Settings::OPTION_KEY, Settings::defaults(), false);
            Settings::flush();
            \WP_CLI::success('تنظیمات بازنشانی شد.');

            return;
        }

        if ($action === 'get') {
            $key = (string) ($args[1] ?? '');

            if ($key === '') {
                \WP_CLI::error('کلید را وارد کنید، مثال: seo.title_template');
            }

            $value = Settings::opt($key);

            \WP_CLI::line(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return;
        }

        if ($action === 'set') {
            $key   = (string) ($args[1] ?? '');
            $value = $args[2] ?? '';

            if ($key === '') {
                \WP_CLI::error('کلید را وارد کنید.');
            }

            $stored = (array) \get_option(Settings::OPTION_KEY, []);
            $parts  = explode('.', $key);
            $ref    = &$stored;

            foreach ($parts as $index => $part) {
                if ($index === count($parts) - 1) {
                    $ref[$part] = $this->cast($value);
                } else {
                    if (! isset($ref[$part]) || ! is_array($ref[$part])) {
                        $ref[$part] = [];
                    }

                    $ref = &$ref[$part];
                }
            }

            unset($ref);
            Settings::save($stored);
            \WP_CLI::success($key . ' → ' . (is_scalar($value) ? (string) $value : json_encode($value)));

            return;
        }

        // list
        $group  = (string) ($assoc_args['group'] ?? '');
        $values = Settings::all();
        $rows   = [];

        foreach ($values as $group_name => $fields) {
            if ($group !== '' && $group_name !== $group) {
                continue;
            }

            if (! is_array($fields)) {
                continue;
            }

            foreach ($fields as $name => $value) {
                $rows[] = [
                    'key'   => $group_name . '.' . $name,
                    'type'  => is_array($value) ? 'array' : gettype($value),
                    'value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value,
                ];
            }
        }

        \WP_CLI\Utils\format_items('table', $rows, ['key', 'type', 'value']);
    }

    /**
     * تبدیل مقدار رشته‌ای CLI به نوع مناسب.
     *
     * @param mixed $value مقدار
     * @return mixed
     */
    private function cast($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if (is_numeric($value)) {
            return strpos($value, '.') === false ? (int) $value : (float) $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }

    /**
     * پاک‌سازی حافظه‌های نهان.
     *
     * ## OPTIONS
     *
     * <action>
     * : flush
     *
     * @subcommand cache
     *
     * @param array<int,string> $args آرگومان‌ها
     * @return void
     */
    public function cache(array $args): void
    {
        Assets::flush_image_cache();
        Seo::flush_sitemap();
        Products::flush_cache();

        if (\function_exists('wp_cache_flush')) {
            \wp_cache_flush();
        }

        \WP_CLI::success('حافظه نهان قالب پاک شد.');
    }

    /**
     * ابزارهای به‌روزرسانی Patch.
     *
     * ## OPTIONS
     *
     * <action>
     * : inspect|apply|build|backups|rollback|feed
     *
     * [<file>]
     * : مسیر فایل zip
     *
     * [--dry-run]
     * : فقط اعتبارسنجی
     *
     * [--yes]
     * : بدون پرسش تأیید
     *
     * [--version=<version>]
     * : نسخه هدف (برای build)
     *
     * [--label=<label>]
     * : برچسب انتشار
     *
     * [--notes=<notes>]
     * : یادداشت انتشار
     *
     * [--out=<path>]
     * : مسیر خروجی (برای build)
     *
     * [--dir=<path>]
     * : پوشه فایل‌های تغییریافته (برای build)
     *
     * [--check]
     * : بررسی فید راه دور
     *
     * @subcommand patch
     *
     * @param array<int,string>         $args       آرگومان‌ها
     * @param array<string,string|bool> $assoc_args گزینه‌ها
     * @return void
     */
    public function patch(array $args, array $assoc_args = []): void
    {
        $action = (string) ($args[0] ?? '');
        $file   = (string) ($args[1] ?? '');

        switch ($action) {
            case 'inspect':
                if ($file === '') {
                    \WP_CLI::error('مسیر فایل zip را وارد کنید.');
                }

                $result = PatchApplier::inspect($file);

                if (empty($result['ok'])) {
                    \WP_CLI::error((string) ($result['message'] ?? 'inspect failed') . ' [' . (string) ($result['code'] ?? '') . ']');
                }

                $manifest = (array) ($result['manifest'] ?? []);
                \WP_CLI::line('id      : ' . (string) ($manifest['id'] ?? ''));
                \WP_CLI::line('version : ' . (string) ($manifest['version'] ?? ''));
                \WP_CLI::line('label   : ' . (string) ($manifest['label'] ?? ''));
                \WP_CLI::line('files   : ' . count((array) ($manifest['files'] ?? [])));
                \WP_CLI::success('manifest معتبر است.');
                break;

            case 'apply':
                if ($file === '') {
                    \WP_CLI::error('مسیر فایل zip را وارد کنید.');
                }

                $dry = ! empty($assoc_args['dry-run']);

                if (! $dry) {
                    \WP_CLI::confirm('اعمال پچ روی فایل‌های قالب؟', $assoc_args);
                }

                $result = PatchApplier::apply($file, [
                    'dry_run' => $dry,
                    'user'    => 'wp-cli',
                ]);

                foreach ((array) ($result['plan']['rows'] ?? []) as $row) {
                    \WP_CLI::line(sprintf('  %-7s %s', (string) ($row['op'] ?? ''), (string) ($row['path'] ?? '')));
                }

                if (empty($result['ok'])) {
                    \WP_CLI::error((string) ($result['message'] ?? '') . ' [' . (string) ($result['code'] ?? '') . ']');
                }

                \WP_CLI::success($dry ? 'اعتبارسنجی موفق (حالت آزمایشی)' : (string) ($result['message'] ?? 'patch applied'));
                break;

            case 'build':
                $version = (string) ($assoc_args['version'] ?? '');

                if ($version === '') {
                    \WP_CLI::error('--version لازم است.');
                }

                $paths = $this->collect_paths($args, $assoc_args);

                if ($paths === []) {
                    \WP_CLI::error('مسیر یا پوشه‌ای برای بسته‌بندی پیدا نشد. مثال: wp beryon patch build --version=1.1.0 --dir=app');
                }

                $result = Updater::create($paths, $version, [
                    'label'   => (string) ($assoc_args['label'] ?? ''),
                    'notes'   => (string) ($assoc_args['notes'] ?? ''),
                    'channel' => (string) ($assoc_args['channel'] ?? ''),
                ]);

                if (empty($result['ok'])) {
                    \WP_CLI::error((string) ($result['message'] ?? 'build failed'));
                }

                $out = (string) ($assoc_args['out'] ?? '');

                if ($out !== '' && (string) ($result['file'] ?? '') !== $out && is_readable((string) $result['file'])) {
                    copy((string) $result['file'], $out);
                    \WP_CLI::line('copy → ' . $out);
                }

                \WP_CLI::success('بسته ساخته شد: ' . (string) ($result['file'] ?? '') . ' (' . \size_format((int) ($result['size'] ?? 0)) . ')');
                \WP_CLI::line('sha256: ' . (string) ($result['sha256'] ?? ''));
                break;

            case 'backups':
                $backups = PatchApplier::backups();

                if ($backups === []) {
                    \WP_CLI::warning('پشتیبانی موجود نیست.');

                    return;
                }

                $rows = [];

                foreach ($backups as $backup) {
                    $rows[] = [
                        'name' => (string) $backup['name'],
                        'date' => Jalali::format((int) $backup['time'], 'Y/m/d H:i'),
                        'size' => \size_format((int) $backup['size']),
                    ];
                }

                \WP_CLI\Utils\format_items('table', $rows, ['name', 'date', 'size']);
                break;

            case 'rollback':
                if ($file === '') {
                    \WP_CLI::error('نام فایل پشتیبان را وارد کنید.');
                }

                // «patch backups» فقط نام فایل را نشان می‌دهد؛ نامِ تنها در پوشه پشتیبان‌ها جست‌وجو می‌شود.
                if (! is_readable($file)) {
                    $file = PatchApplier::backups_dir() . '/' . basename($file);
                }

                \WP_CLI::confirm('بازگردانی این پشتیبان؟', $assoc_args);
                $result = PatchApplier::restore_backup($file);

                if (empty($result['ok'])) {
                    \WP_CLI::error((string) ($result['message'] ?? 'rollback failed'));
                }

                \WP_CLI::success('بازگردانی انجام شد.');
                break;

            case 'feed':
                if (! empty($assoc_args['check'])) {
                    $result = Updater::check_remote();

                    if (empty($result['ok'])) {
                        \WP_CLI::error((string) ($result['message'] ?? 'feed failed'));
                    }

                    \WP_CLI::line('remote version : ' . (string) ($result['feed']['version'] ?? ''));
                    \WP_CLI::line('current        : ' . Updater::version());
                    \WP_CLI::line('update needed  : ' . (! empty($result['update']) ? 'yes' : 'no'));

                    return;
                }

                $feed = (array) \get_option(Updater::FEED_OPTION, []);

                if ($feed === []) {
                    \WP_CLI::warning('فیدی ذخیره نشده است.');

                    return;
                }

                \WP_CLI\Utils\format_items('json', [$feed], array_keys($feed));
                break;

            default:
                \WP_CLI::error('اکشن نامعتبر. موارد مجاز: inspect|apply|build|backups|rollback|feed');
        }
    }

    /**
     * گردآوری فهرست فایل‌های نسبی برای ساخت بسته Patch.
     *
     * @param array<int,string>         $args       آرگومان‌های موقعیتی (پس از اکشن)
     * @param array<string,string|bool> $assoc_args گزینه‌ها
     * @return array<int,string>
     */
    private function collect_paths(array $args, array $assoc_args): array
    {
        $paths = array_slice($args, 1);
        $dir   = (string) ($assoc_args['dir'] ?? '');

        if ($dir !== '') {
            $paths[] = $dir;
        }

        $resolved = [];

        foreach ($paths as $path) {
            $relative = trim((string) $path, '/');

            if ($relative === '' || $relative === '.') {
                continue;
            }

            $absolute = BERYON_PATH . '/' . $relative;

            if (is_file($absolute)) {
                $resolved[] = $relative;

                continue;
            }

            if (! is_dir($absolute)) {
                \WP_CLI::warning('مسیر یافت نشد: ' . $relative);

                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile()) {
                    $full = str_replace(BERYON_PATH . '/', '', str_replace('\\', '/', $file->getPathname()));

                    if (! str_contains($full, '/vendor/') && ! str_contains($full, '/node_modules/')) {
                        $resolved[] = $full;
                    }
                }
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * بررسی یکپارچگی فایل‌های هسته قالب.
     *
     * ## OPTIONS
     *
     * [--refresh]
     * : بازسازی اثر انگشت
     *
     * @subcommand integrity
     *
     * @param array<int,string>         $args       آرگومان‌ها
     * @param array<string,string|bool> $assoc_args گزینه‌ها
     * @return void
     */
    public function integrity(array $args = [], array $assoc_args = []): void
    {
        if (! empty($assoc_args['refresh'])) {
            Security::fingerprint();
            \WP_CLI::success('اثر انگشت فایل‌ها بازسازی شد.');

            return;
        }

        $report = Security::verify();

        if ((int) ($report['total'] ?? 0) === 0) {
            \WP_CLI::warning('اثر انگشتی ثبت نشده است؛ ابتدا integrity --refresh را اجرا کنید.');

            return;
        }

        foreach ((array) ($report['modified'] ?? []) as $file) {
            \WP_CLI::warning('modified: ' . $file);
        }

        foreach ((array) ($report['missing'] ?? []) as $file) {
            \WP_CLI::error('missing : ' . $file, false);
        }

        if (! empty($report['ok'])) {
            \WP_CLI::success('همه ' . (string) $report['total'] . ' فایل هسته سالم‌اند.');
        } else {
            \WP_CLI::error('تغییر غیرمجاز تشخیص داده شد.');
        }
    }

    /**
     * بررسی سلامت محیط قالب.
     *
     * @subcommand doctor
     *
     * @return void
     */
    public function doctor(): void
    {
        $checks = [
            ['PHP ≥ 7.4', version_compare(PHP_VERSION, '7.4.0', '>=')],
            ['WP ≥ 6.0', version_compare(\get_bloginfo('version'), '6.0', '>=')],
            ['composer autoload', class_exists('\Illuminate\View\Factory') || is_readable(BERYON_PATH . '/vendor/autoload.php')],
            ['build manifest', is_readable(BERYON_PATH . '/public/assets/manifest.json') || is_readable(BERYON_PATH . '/public/assets/css/app.css')],
            ['config/catalog.json', is_readable(BERYON_PATH . '/config/catalog.json')],
            ['config/defaults.json', is_readable(BERYON_PATH . '/config/defaults.json')],
            ['config/blog.json', is_readable(BERYON_PATH . '/config/blog.json')],
            ['views/layouts/app.blade.php', is_readable(BERYON_PATH . '/resources/views/layouts/app.blade.php')],
            ['پوشه patches نوشتنی', is_writable(PatchApplier::patches_dir()) || is_writable(dirname(PatchApplier::patches_dir()))],
            ['WooCommerce', class_exists('\WooCommerce')],
            ['بدون تداخل افزونه سئوی سوم‌شخص', ! Seo::plugin_active()],
            ['صفحه اصلی تنظیم شده', (int) \get_option('page_on_front') > 0],
            ['دسته‌های محصول', count(Products::categories()) > 0],
            ['محصولات', Products::total_count() > 0],
            ['منوی اصلی', \has_nav_menu('primary')],
        ];

        $rows = [];

        foreach ($checks as [$label, $ok]) {
            $rows[] = ['check' => $label, 'status' => $ok ? 'OK' : 'NEEDS ATTENTION'];
        }

        \WP_CLI\Utils\format_items('table', $rows, ['check', 'status']);
        \WP_CLI::line('theme version: ' . BERYON_VERSION . ' / installed: ' . Updater::version());
    }

    /**
     * ابزارهای تصویر.
     *
     * ## OPTIONS
     *
     * [--regenerate]
     * : بازسازی اندازه‌های تصویر قالب
     *
     * [--list]
     * : فهرست تصاویر پوشه قالب
     *
     * @subcommand images
     *
     * @param array<int,string>         $args       آرگومان‌ها
     * @param array<string,string|bool> $assoc_args گزینه‌ها
     * @return void
     */
    public function images(array $args = [], array $assoc_args = []): void
    {
        $dir = BERYON_PATH . '/public/assets/images';

        if (! empty($assoc_args['list']) || (! empty($assoc_args['regenerate']) === false)) {
            $rows = [];

            foreach ((array) glob($dir . '/*') as $file) {
                if (is_dir((string) $file)) {
                    continue;
                }

                $rows[] = [
                    'name' => basename((string) $file),
                    'size' => \size_format((int) filesize((string) $file)),
                    'ext'  => strtolower(pathinfo((string) $file, PATHINFO_EXTENSION)),
                ];
            }

            \WP_CLI\Utils\format_items('table', $rows, ['name', 'size', 'ext']);
        }

        if (! empty($assoc_args['regenerate'])) {
            global $wpdb;

            $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' LIMIT 2000"); // phpcs:ignore WordPress.DB

            $progress = \WP_CLI\Utils\make_progress_bar('regenerate', count((array) $ids));

            foreach ((array) $ids as $id) {
                $file = \get_attached_file((int) $id);

                if (is_string($file) && is_readable($file)) {
                    \wp_update_attachment_metadata((int) $id, \wp_generate_attachment_metadata((int) $id, $file));
                }

                $progress->tick();
            }

            $progress->finish();
            \WP_CLI::success('اندازه‌های تصویر بازسازی شد.');
        }
    }
}
