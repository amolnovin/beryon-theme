<?php

/**
 * پنل تنظیمات قالب بریون — تب‌های محتوایی، سئو، فرم‌ها، عملکرد، امنیت و به‌روزرسانی
 * ------------------------------------------------------------------
 * بر پایه Settings API بومی وردپرس (بدون وابستگی به ACF Pro).
 * همه فیلدها در یک Option (beryon_theme_options) با ساختار «گروه.نام» ذخیره می‌شوند
 * تا فایل config/defaults.json بتواند با Patch به‌روزرسانی شود و مقادیر کاربر حفظ بماند.
 *
 * @package beryon
 */

namespace App\Admin;

use App\Assets;
use App\Forms;
use App\Seo;
use App\Security;
use App\Settings;
use App\Updater;
use App\Updater\PatchApplier;

defined('ABSPATH') || exit;

/**
 * کلاس پنل تنظیمات.
 */
final class SettingsPage
{
    /** اسلاگ صفحه تنظیمات */
    public const PAGE_SLUG = 'beryon-settings';

    /** نام Option فرم */
    public const OPTION = Settings::OPTION_KEY;

    /** گروه تنظیمات در Settings API */
    public const GROUP = 'beryon_settings_group';

    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        \add_action('admin_menu', [self::class, 'menu'], 20);
        \add_action('admin_init', [self::class, 'register']);
        \add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
        \add_action('admin_post_beryon_reset_options', [self::class, 'reset_options']);
        \add_action('admin_post_beryon_flush_caches', [self::class, 'flush_caches']);
        \add_action('admin_post_beryon_reseed', [self::class, 'reseed']);
        \add_action('admin_notices', [self::class, 'flash_notice']);

        // آپلود پچ از طریق فرم (مسیر جایگزین AJAX برای بسته‌های بزرگ)
        \add_action('admin_post_beryon_upload_patch', [self::class, 'upload_patch']);
    }

    /* ------------------------------------------------------------------ *
     * منو و بارگذاری اسکریپت‌ها
     * ------------------------------------------------------------------ */

    /**
     * افزودن منوها.
     *
     * @return void
     */
    public static function menu(): void
    {
        $icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a0a5aa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3 7h7l-5.5 4.5L18.5 21 12 16.8 5.5 21l2-7.5L2 9h7z"/></svg>'
        );

        \add_menu_page(
            \__('قالب بریون', BERYON_TD),
            \__('قالب بریون', BERYON_TD),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render'],
            $icon,
            59
        );

        \add_submenu_page(self::PAGE_SLUG, \__('تنظیمات قالب', BERYON_TD), \__('تنظیمات قالب', BERYON_TD), 'manage_options', self::PAGE_SLUG, [self::class, 'render']);
        \add_submenu_page(self::PAGE_SLUG, \__('درخواست‌های کاربران', BERYON_TD), \__('درخواست‌ها', BERYON_TD), 'manage_options', 'beryon-submissions', [Submissions::class, 'render']);
        \add_submenu_page(self::PAGE_SLUG, \__('به‌روزرسانی قالب (Patch)', BERYON_TD), \__('به‌روزرسانی (Patch)', BERYON_TD), 'manage_options', 'beryon-updates', [self::class, 'render_updater']);
        \add_submenu_page(self::PAGE_SLUG, \__('ابزارهای قالب', BERYON_TD), \__('ابزارها', BERYON_TD), 'manage_options', 'beryon-tools', [self::class, 'render_tools']);
        \add_submenu_page(self::PAGE_SLUG, \__('راهنمای قالب', BERYON_TD), \__('راهنما', BERYON_TD), 'manage_options', 'beryon-docs', [self::class, 'render_docs']);
    }

    /**
     * بارگذاری CSS/JS پنل.
     *
     * @param string $hook هوک صفحه
     * @return void
     */
    public static function enqueue(string $hook): void
    {
        if (strpos($hook, 'beryon') === false) {
            return;
        }

        Assets::admin_assets($hook);

        \wp_localize_script(
            'beryon-admin',
            'beryonAdmin',
            [
                'ajaxUrl'   => \admin_url('admin-ajax.php'),
                'nonce'     => \wp_create_nonce('beryon_admin'),
                'restUrl'   => \esc_url_raw(\rest_url('beryon/v1/')),
                'restNonce' => \wp_create_nonce('wp_rest'),
                'uploadUrl' => \esc_url_raw(\wp_nonce_url(\admin_url('admin-post.php?action=beryon_upload_patch'), 'beryon_upload_patch')),
                'strings'   => [
                    'checking'   => \__('در حال بررسی…', BERYON_TD),
                    'applying'   => \__('در حال اعمال پچ…', BERYON_TD),
                    'confirm'    => \__('این عملیات قابل بازگشت است؛ ادامه می‌دهید؟', BERYON_TD),
                    'confirmRb'  => \__('بازگردانی به نسخه پشتیبان، تغییرات بعدی را حذف می‌کند. ادامه؟', BERYON_TD),
                    'emptyPatch' => \__('ابتدا یک بسته پچ انتخاب کنید.', BERYON_TD),
                    'ok'         => \__('انجام شد', BERYON_TD),
                ],
            ]
        );
    }

    /* ------------------------------------------------------------------ *
     * ثبت و پاک‌سازی (Settings API)
     * ------------------------------------------------------------------ */

    /**
     * طرح تب‌ها و فیلدها.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function schema(): array
    {
        return SettingsSchema::all();
    }

    /**
     * نمایش پیام‌های نتیجه عملیات.
     *
     * @return void
     */
    public static function flash_notice(): void
    {
        $key = 'beryon_admin_flash_' . \get_current_user_id();
        $msg = \get_transient($key);

        if (! is_string($msg) || $msg === '') {
            return;
        }

        \delete_transient($key);

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
    }

    /**
     * پیام خوش‌آمدگویی پس از فعال‌سازی قالب (یک بار؛ از functions.php صدا زده می‌شود).
     *
     * @return void
     */
    public static function welcome_notice(): void
    {
        $links = [
            \admin_url('admin.php?page=' . self::PAGE_SLUG) => \__('تنظیمات قالب', BERYON_TD),
            \admin_url('admin.php?page=beryon-tools')       => \__('درج داده‌های نمونه', BERYON_TD),
            \admin_url('admin.php?page=beryon-docs')        => \__('راهنما', BERYON_TD),
        ];

        $html = [];

        foreach ($links as $url => $label) {
            $html[] = '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }

        echo '<div class="notice notice-success is-dismissible"><p><strong>'
            . esc_html(\sprintf(\__('قالب بریون %s فعال شد.', BERYON_TD), BERYON_VERSION))
            . '</strong> '
            . esc_html__('برای شروع، متن‌ها و اطلاعات تماس را در تنظیمات قالب بازبینی کنید و در صورت نیاز داده‌های نمونه را درج کنید:', BERYON_TD)
            . ' ' . implode(' | ', $html) // phpcs:ignore WordPress.Security.EscapeOutput -- اجزا پیش‌تر escape شده‌اند
            . '</p></div>';
    }

    /**
     * ثبت تنظیمات.
     *
     * @return void
     */
    public static function register(): void
    {
        \register_setting(self::GROUP, self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [self::class, 'sanitize'],
            'default'           => [],
            'show_in_rest'      => false,
        ]);
    }

    /**
     * پاک‌سازی ورودی بر اساس نوع هر فیلد.
     *
     * دو حالت دارد:
     *  - ارسال فرم پنل (options.php): فرم فقط فیلدهای «برگه فعال» را می‌فرستد؛
     *    پس مقادیر ذخیره‌شده پایه قرار می‌گیرند و تنها فیلدهای حاضر در ورودی
     *    جایگزین می‌شوند. کلیدهای بیرون از طرح (schema) نادیده گرفته می‌شوند.
     *  - ذخیره برنامه‌ای (update_option از کد دیگر): ورودی مقدار کامل Option است؛
     *    همان پایه می‌شود و فیلدهای طرح فقط نرمال می‌شوند.
     *
     * پیش‌تر نتیجه از روی کل طرح بازسازی می‌شد؛ در نتیجه ذخیره یک برگه، همه
     * فیلدهای برگه‌های دیگر را خالی می‌کرد.
     *
     * @param mixed $input ورودی
     * @return array<string,mixed>
     */
    public static function sanitize($input): array
    {
        $stored = \get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];

        // ورودی غیرآرایه (درخواست دست‌کاری‌شده یا خطای کدنویسی) هرگز نباید
        // تنظیمات موجود را پاک کند.
        if (! is_array($input)) {
            return $stored;
        }

        $defaults = Settings::defaults();
        $result   = self::is_form_submission() ? $stored : $input;

        foreach (self::schema() as $tab) {
            foreach ((array) ($tab['fields'] ?? []) as $field) {
                $group = (string) ($field['group'] ?? '');
                $name  = (string) ($field['name'] ?? '');

                // فیلدهای تشریفاتی (مانند type=heading) کلید name ندارند و
                // نباید در ذخیره‌سازی شرکت کنند (پیش‌تر کلید خالی '' می‌ساختند).
                if ($group === '' || $name === '') {
                    continue;
                }

                // گروه طرح باید آرایه باشد؛ مقدار ناسازگار کنار گذاشته می‌شود.
                if (isset($result[$group]) && ! is_array($result[$group])) {
                    unset($result[$group]);
                }

                // فیلدی که در ورودی نیست دست‌نخورده می‌ماند.
                if (! isset($input[$group]) || ! is_array($input[$group]) || ! array_key_exists($name, $input[$group])) {
                    continue;
                }

                $type         = (string) ($field['type'] ?? 'text');
                $stored_group = isset($stored[$group]) && is_array($stored[$group]) ? $stored[$group] : [];
                $fallback     = $stored_group[$name] ?? ($defaults[$group][$name] ?? null);

                $result[$group][$name] = self::sanitize_field($input[$group][$name], $type, $field, $fallback);
            }
        }

        /**
         * فیلتر مقادیر پاک‌سازی‌شده پیش از ذخیره.
         *
         * @param array $result مقادیر
         * @param array $input  ورودی خام
         */
        $result = (array) \apply_filters('beryon/settings/sanitize', $result, $input);

        // پاک‌سازی حافظه‌های نهان وابسته (یک بار در هر درخواست)
        static $flush_scheduled = false;

        if (! $flush_scheduled) {
            $flush_scheduled = true;

            \add_action('shutdown', static function (): void {
                Settings::flush();
                Seo::flush_sitemap();
                Assets::flush_image_cache();

                if (\class_exists('\App\Products')) {
                    \App\Products::flush_cache();
                }

                if (\function_exists('wp_cache_flush')) {
                    \wp_cache_flush();
                }
            });
        }

        return $result;
    }

    /**
     * آیا این درخواست، ارسال فرم همین پنل از طریق options.php است؟
     *
     * @return bool
     */
    private static function is_form_submission(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce را options.php پیش از update_option بررسی می‌کند
        $page = isset($_POST['option_page']) && is_string($_POST['option_page']) ? \sanitize_key(\wp_unslash($_POST['option_page'])) : '';

        return $page === self::GROUP;
    }

    /**
     * پاک‌سازی یک فیلد.
     *
     * همه شاخه‌ها روی مقدار «از پیش پاک‌سازی‌شده» هم همان نتیجه را می‌دهند
     * (idempotent)؛ چون وردپرس در نخستین ذخیره، sanitize را دو بار صدا می‌زند
     * (update_option → add_option) و ذخیره برنامه‌ای مقدار ساخت‌یافته می‌دهد.
     *
     * @param mixed                $raw      مقدار خام
     * @param string               $type     نوع
     * @param array<string,mixed>  $field    تعریف فیلد
     * @param mixed                $fallback مقدار جایگزین
     * @return mixed
     */
    private static function sanitize_field($raw, string $type, array $field, $fallback)
    {
        if (! is_scalar($raw) && ! is_array($raw)) {
            $raw = null;
        }

        // فیلد تک‌مقداری با مقدار آرایه‌ای معتبر نیست؛ مقدار قبلی حفظ می‌شود
        // (تبدیل آرایه به رشته، متن «Array» ذخیره می‌کرد).
        if (is_array($raw) && ! in_array($type, ['tags', 'lines', 'list', 'repeater', 'sublist'], true)) {
            return $fallback;
        }

        switch ($type) {
            case 'checkbox':
            case 'switch':
                if ($raw === null) {
                    return (bool) $fallback;
                }

                return in_array($raw, [true, 1, '1', 'on', 'true', 'yes'], true);

            case 'number':
            case 'int':
                if ($raw === null || $raw === '') {
                    return (int) $fallback;
                }

                $value = (int) $raw;
                $min   = isset($field['min']) ? (int) $field['min'] : null;
                $max   = isset($field['max']) ? (int) $field['max'] : null;

                if ($min !== null && $value < $min) {
                    $value = $min;
                }

                if ($max !== null && $value > $max) {
                    $value = $max;
                }

                return $value;

            case 'color':
                return (string) \sanitize_hex_color((string) $raw) ?: (string) $fallback;

            case 'url':
                return (string) $raw === '' ? '' : (string) \esc_url_raw((string) $raw);

            case 'email':
                return (string) \sanitize_email((string) $raw);

            case 'tel':
                $digits = \preg_replace('/[^0-9+\-() ]/', '', (string) $raw);

                return is_string($digits) ? $digits : '';

            case 'textarea':
            case 'html':
                // مرورگر متن چندخطی را با CRLF می‌فرستد؛ به LF یکدست می‌شود تا مقدار
                // ذخیره‌شده با هر بار ذخیره تغییر نکند.
                return (string) \wp_kses_post(str_replace(["\r\n", "\r"], "\n", (string) $raw));

            case 'tags':
            case 'lines':
                return self::sanitize_list($raw, "\n");

            case 'list':
                return self::sanitize_list($raw, ',');

            case 'sublist':
                return self::sanitize_sublist($raw);

            case 'select':
                $options = array_column((array) ($field['options'] ?? []), 'value');
                $options = $options === [] ? array_keys((array) ($field['options'] ?? [])) : $options;

                return in_array((string) $raw, array_map('strval', $options), true) ? (string) $raw : (string) $fallback;

            case 'image':
            case 'media':
            case 'file':
            case 'attachment':
                // مقدار خالی/صفر یعنی «بدون انتخاب» (دکمه «حذف» در پنل)؛ پیش‌تر به
                // شناسه قبلی برمی‌گشت و تصویر انتخاب‌شده قابل حذف نبود.
                return $raw === null ? (int) $fallback : max(0, (int) $raw);

            case 'repeater':
                return self::sanitize_repeater($raw, $field, (array) $fallback);

            default:
                return (string) \sanitize_text_field((string) $raw);
        }
    }

    /**
     * تبدیل رشته چندخطی/جداشده (یا آرایه از پیش ساخت‌یافته) به آرایه پاک‌سازی‌شده.
     *
     * @param mixed  $raw       ورودی (متن فرم یا آرایه)
     * @param string $separator جداکننده («,» ویرگول فارسی «،» را هم می‌پذیرد)
     * @return array<int,string>
     */
    private static function sanitize_list($raw, string $separator): array
    {
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $text  = str_replace(["\r\n", "\r"], "\n", is_scalar($raw) ? (string) $raw : '');
            $parts = $separator === ','
                ? (array) (preg_split('/[,،]/u', $text) ?: [])
                : explode($separator, $text);
        }

        $out = [];

        foreach ($parts as $part) {
            if (! is_scalar($part)) {
                continue;
            }

            $part = trim((string) \sanitize_text_field((string) $part));

            if ($part !== '') {
                $out[] = $part;
            }
        }

        return array_values(array_slice($out, 0, 100));
    }

    /**
     * فهرست «عنوان | توضیح» (هر خط یک مورد) ↔ آرایه [{title, sub}].
     *
     * برای زیرواحدهای چارت سازمانی (org.units.children): نما آرایه‌ای از
     * {title, sub} می‌خواهد ولی فیلد پنل یک textarea ساده بود.
     *
     * @param mixed $raw متن چندخطی فرم یا آرایه از پیش ساخت‌یافته
     * @return array<int,array{title:string,sub:string}>
     */
    private static function sanitize_sublist($raw): array
    {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $items = explode("\n", str_replace(["\r\n", "\r"], "\n", is_scalar($raw) ? (string) $raw : ''));
        }

        $out = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $title = is_scalar($item['title'] ?? null) ? (string) $item['title'] : '';
                $sub   = is_scalar($item['sub'] ?? null) ? (string) $item['sub'] : '';
            } elseif (is_scalar($item)) {
                $parts = explode('|', (string) $item, 2);
                $title = $parts[0];
                $sub   = $parts[1] ?? '';
            } else {
                continue;
            }

            $title = trim((string) \sanitize_text_field($title));
            $sub   = trim((string) \sanitize_text_field($sub));

            if ($title !== '') {
                $out[] = ['title' => $title, 'sub' => $sub];
            }
        }

        return array_values(array_slice($out, 0, 30));
    }

    /**
     * متن قابل ویرایش یک فهرست «عنوان | توضیح» (عکس sanitize_sublist).
     *
     * @param mixed $value مقدار ذخیره‌شده
     * @return string
     */
    private static function sublist_text($value): string
    {
        if (! is_array($value)) {
            return is_scalar($value) ? (string) $value : '';
        }

        $lines = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $title = is_scalar($item['title'] ?? null) ? trim((string) $item['title']) : '';
                $sub   = is_scalar($item['sub'] ?? null) ? trim((string) $item['sub']) : '';

                if ($title !== '') {
                    $lines[] = $sub !== '' ? $title . ' | ' . $sub : $title;
                }
            } elseif (is_scalar($item) && trim((string) $item) !== '') {
                $lines[] = trim((string) $item);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * پاک‌سازی ردیف‌های تکرارشونده.
     *
     * @param mixed               $raw      ورودی
     * @param array<string,mixed> $field    تعریف فیلد
     * @param array<int,mixed>    $fallback مقدار جایگزین
     * @return array<int,array<string,mixed>>
     */
    private static function sanitize_repeater($raw, array $field, array $fallback): array
    {
        $rows   = is_array($raw) ? $raw : [];
        $schema = (array) ($field['row'] ?? []);
        $out    = [];
        $max    = (int) ($field['max'] ?? 40);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $clean = [];
            $empty = true;

            foreach ($schema as $sub) {
                $sub_name = (string) $sub['name'];
                $sub_type = (string) ($sub['type'] ?? 'text');
                $value    = self::sanitize_field($row[$sub_name] ?? '', $sub_type, $sub, '');
                $clean[$sub_name] = $value;

                if ($value !== '' && $value !== [] && $value !== 0 && $value !== false) {
                    $empty = false;
                }
            }

            if (! $empty) {
                $out[] = $clean;
            }

            if (count($out) >= $max) {
                break;
            }
        }

        return $out === [] ? (array) $fallback : array_values($out);
    }

    /* ------------------------------------------------------------------ *
     * رندر صفحات
     * ------------------------------------------------------------------ */

    /**
     * رندر صفحه تنظیمات.
     *
     * @return void
     */
    public static function render(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\__('دسترسی مجاز نیست.', BERYON_TD));
        }

        $values = Settings::all();
        $schema = self::schema();
        $tab    = isset($_GET['tab']) ? (string) \sanitize_key(\wp_unslash($_GET['tab'])) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if (! isset($schema[$tab])) {
            $tab = 'general';
        }

        echo '<div class="wrap beryon-wrap" dir="rtl">';
        self::header(__('تنظیمات قالب بریون', BERYON_TD), sprintf('نسخه %s', BERYON_VERSION));
        echo '<nav class="beryon-tabs" role="tablist">';

        foreach ($schema as $key => $conf) {
            $url  = \add_query_arg(['page' => self::PAGE_SLUG, 'tab' => $key], \admin_url('admin.php'));
            $aria = $key === $tab ? ' aria-current="page"' : '';
            echo '<a class="beryon-tab' . ($key === $tab ? ' is-active' : '') . '" href="' . esc_url($url) . '" role="tab"' . $aria . '>'
                . esc_html($conf['label']) . '</a>';
        }

        echo '</nav>';

        echo '<form method="post" action="' . esc_url(\admin_url('options.php')) . '" class="beryon-form">';
        \settings_fields(self::GROUP);

        $current = $schema[$tab];

        echo '<input type="hidden" name="beryon_tab" value="' . esc_attr($tab) . '">';

        if (! empty($current['intro'])) {
            echo '<p class="beryon-intro">' . \wp_kses_post($current['intro']) . '</p>';
        }

        echo '<div class="beryon-card">';

        foreach ((array) $current['fields'] as $field) {
            self::field($field, $values);
        }

        echo '</div>';

        \submit_button(\__('ذخیره تنظیمات', BERYON_TD), 'primary large', 'submit', true, ['data-loading' => 'true']);
        echo '</form>';

        self::quick_actions();
        self::footer();
        echo '</div>';
    }

    /**
     * رندر صفحه به‌روزرسانی (Patch).
     *
     * @return void
     */
    public static function render_updater(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\__('دسترسی مجاز نیست.', BERYON_TD));
        }

        $values  = Settings::all();
        $feed    = (array) \get_option(Updater::FEED_OPTION, []);
        $history = Updater::history();
        $logs    = Updater::logs(25);
        $backups = PatchApplier::backups();
        $pending = \get_transient('beryon_update_pending');

        echo '<div class="wrap beryon-wrap" dir="rtl">';
        self::header(\__('به‌روزرسانی قالب با Patch', BERYON_TD), \__('نسخه نصب‌شده: ', BERYON_TD) . BERYON_VERSION);

        echo '<nav class="beryon-tabs">';
        echo '<a class="beryon-tab" href="' . esc_url(\admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">' . esc_html__('تنظیمات', BERYON_TD) . '</a>';
        echo '<a class="beryon-tab" href="' . esc_url(\admin_url('admin.php?page=beryon-submissions')) . '">' . esc_html__('درخواست‌ها', BERYON_TD) . '</a>';
        echo '<a class="beryon-tab is-active" href="' . esc_url(\admin_url('admin.php?page=beryon-updates')) . '" aria-current="page">' . esc_html__('به‌روزرسانی', BERYON_TD) . '</a>';
        echo '<a class="beryon-tab" href="' . esc_url(\admin_url('admin.php?page=beryon-tools')) . '">' . esc_html__('ابزارها', BERYON_TD) . '</a>';
        echo '</nav>';

        // --- وضعیت نسخه ---
        echo '<div class="beryon-grid-2">';
        echo '<div class="beryon-card">';
        echo '<h2 class="beryon-card-title">' . esc_html__('وضعیت نسخه', BERYON_TD) . '</h2>';
        echo '<table class="widefat striped beryon-table"><tbody>';
        echo '<tr><th>' . esc_html__('نسخه فعال', BERYON_TD) . '</th><td><code>' . esc_html(BERYON_VERSION) . '</code></td></tr>';
        echo '<tr><th>' . esc_html__('مسیر قالب', BERYON_TD) . '</th><td><code>' . esc_html(basename(BERYON_PATH)) . '</code></td></tr>';
        echo '<tr><th>' . esc_html__('PHP / وردپرس', BERYON_TD) . '</th><td><code>' . esc_html(PHP_VERSION . ' / ' . \get_bloginfo('version')) . '</code></td></tr>';
        echo '<tr><th>' . esc_html__('زمان‌بندی بررسی خودکار', BERYON_TD) . '</th><td>' . (Updater::is_scheduled() ? '<span class="beryon-badge ok">فعال</span>' : '<span class="beryon-badge off">غیرفعال</span>') . '</td></tr>';
        echo '</tbody></table>';

        echo '<p class="beryon-actions">';
        echo '<button type="button" class="button button-primary" data-beryon-action="check-update">' . esc_html__('بررسی به‌روزرسانی', BERYON_TD) . '</button> ';
        echo '<button type="button" class="button" data-beryon-action="integrity">' . esc_html__('بررسی یکپارچگی فایل‌ها', BERYON_TD) . '</button>';
        echo '</p>';
        echo '<div class="beryon-result" data-beryon-result hidden></div>';
        echo '</div>';

        // --- فید راه دور ---
        echo '<div class="beryon-card">';
        echo '<h2 class="beryon-card-title">' . esc_html__('منبع به‌روزرسانی (فید JSON)', BERYON_TD) . '</h2>';
        echo '<form method="post" action="' . esc_url(\admin_url('options.php')) . '">';
        \settings_fields(self::GROUP);
        echo '<input type="hidden" name="beryon_tab" value="updater">';

        foreach (self::schema()['updater']['fields'] as $field) {
            self::field($field, $values);
        }

        \submit_button(\__('ذخیره تنظیمات به‌روزرسانی', BERYON_TD));
        echo '</form>';

        if (is_array($feed) && $feed !== []) {
            echo '<table class="widefat striped beryon-table"><tbody>';
            echo '<tr><th>' . esc_html__('نسخه فید', BERYON_TD) . '</th><td><code>' . esc_html((string) ($feed['version'] ?? '—')) . '</code></td></tr>';
            echo '<tr><th>' . esc_html__('برچسب', BERYON_TD) . '</th><td>' . esc_html((string) ($feed['label'] ?? '—')) . '</td></tr>';
            echo '<tr><th>' . esc_html__('کانال', BERYON_TD) . '</th><td>' . esc_html((string) ($feed['channel'] ?? 'stable')) . '</td></tr>';
            echo '</tbody></table>';
        }

        echo '</div></div>';

        // --- آپلود پچ ---
        echo '<div class="beryon-card">';
        echo '<h2 class="beryon-card-title">' . esc_html__('نصب بسته به‌روزرسانی (Patch)', BERYON_TD) . '</h2>';
        echo '<p class="beryon-intro">' . \wp_kses_post(
            \__('بسته پچ یک فایل <code>zip</code> است که در ریشه آن <code>patch.json</code> و پوشه <code>files/</code> قرار دارد. پیش از اعمال، از فایل‌های دست‌خورده پشتیبان ساخته می‌شود و در صورت خطا، بازگردانی خودکار انجام می‌گیرد. ساختار کامل در <code>docs/PATCH_SPEC.md</code> مستند شده است.', BERYON_TD)
        ) . '</p>';

        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(\wp_nonce_url(\admin_url('admin-post.php?action=beryon_upload_patch'), 'beryon_upload_patch')) . '">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="beryon-patch-file">' . esc_html__('فایل پچ', BERYON_TD) . '</label></th><td>'
            . '<input type="file" name="patch" id="beryon-patch-file" accept=".zip,application/zip" class="regular-text">'
            . '<p class="description">' . esc_html(\sprintf(__('حداکثر اندازه: %s', BERYON_TD), size_format((int) Settings::opt('updater.max_patch_size', 52428800)))) . '</p></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('حالت آزمایشی', BERYON_TD) . '</th><td><label><input type="checkbox" name="dry_run" value="1"> '
            . esc_html__('فقط اعتبارسنجی و نمایش برنامه تغییرات (هیچ فایلی نوشته نمی‌شود)', BERYON_TD) . '</label></td></tr>';
        echo '</tbody></table>';
        \submit_button(\__('بررسی و اعمال پچ', BERYON_TD), 'primary', 'beryon_apply', true);
        echo '</form>';
        echo '</div>';

        // --- پیام‌های نتیجه ---
        $flash = \get_transient('beryon_patch_flash_' . \get_current_user_id());

        if (is_array($flash)) {
            \delete_transient('beryon_patch_flash_' . \get_current_user_id());
            $type = (string) ($flash['type'] ?? 'success');
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p><strong>' . esc_html((string) ($flash['title'] ?? '')) . '</strong><br>' . \wp_kses_post((string) ($flash['message'] ?? '')) . '</p>';

            if (! empty($flash['plan']) && is_array($flash['plan'])) {
                echo '<ul class="beryon-plan">';

                foreach ($flash['plan'] as $row) {
                    echo '<li><code>' . esc_html((string) ($row['op'] ?? '')) . '</code> ' . esc_html((string) ($row['path'] ?? '')) . '</li>';
                }

                echo '</ul>';
            }

            echo '</div>';
        }

        if (is_array($pending) && $pending !== []) {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__('یک به‌روزرسانی آماده نصب است', BERYON_TD) . '</strong><br>'
                . esc_html((string) ($pending['version'] ?? '')) . ' — ' . esc_html((string) ($pending['label'] ?? '')) . '</p></div>';
        }

        // --- پشتیبان‌ها ---
        echo '<div class="beryon-grid-2">';
        echo '<div class="beryon-card">';
        echo '<h2 class="beryon-card-title">' . esc_html__('پشتیبان‌ها (Rollback)', BERYON_TD) . '</h2>';

        if ($backups === []) {
            echo '<p>' . esc_html__('هنوز پشتیبانی ساخته نشده است.', BERYON_TD) . '</p>';
        } else {
            echo '<table class="widefat striped beryon-table"><thead><tr><th>' . esc_html__('فایل', BERYON_TD) . '</th><th>' . esc_html__('تاریخ', BERYON_TD) . '</th><th>' . esc_html__('حجم', BERYON_TD) . '</th><th></th></tr></thead><tbody>';

            foreach ($backups as $backup) {
                echo '<tr><td><code>' . esc_html((string) $backup['name']) . '</code></td>'
                    . '<td>' . esc_html((string) \App\Jalali::format((int) $backup['time'], 'Y/m/d H:i')) . '</td>'
                    . '<td>' . esc_html(\size_format((int) $backup['size'])) . '</td>'
                    . '<td><button type="button" class="button button-small" data-beryon-action="rollback" data-file="' . esc_attr((string) $backup['name']) . '">'
                    . esc_html__('بازگردانی', BERYON_TD) . '</button></td></tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';

        // --- تاریخچه ---
        echo '<div class="beryon-card">';
        echo '<h2 class="beryon-card-title">' . esc_html__('تاریخچه نسخه‌ها', BERYON_TD) . '</h2>';

        if ($history === []) {
            echo '<p>' . esc_html__('رکوردی ثبت نشده است.', BERYON_TD) . '</p>';
        } else {
            echo '<ol class="beryon-history">';

            // Updater::history() از پیش «جدیدترین نخست» است؛ هر رکورد version/time/note دارد.
            foreach (array_slice($history, 0, 12) as $row) {
                echo '<li><strong>' . esc_html((string) ($row['version'] ?? '')) . '</strong> — '
                    . esc_html((string) ($row['note'] ?? ($row['label'] ?? ''))) . ' <span class="beryon-muted">'
                    . esc_html((string) \App\Jalali::format((int) ($row['time'] ?? 0), 'Y/m/d H:i')) . '</span></li>';
            }

            echo '</ol>';
        }

        echo '</div></div>';

        // --- لاگ‌ها ---
        echo '<div class="beryon-card">';
        echo '<h2 class="beryon-card-title">' . esc_html__('لاگ عملیات پچ', BERYON_TD) . '</h2>';

        if ($logs === []) {
            echo '<p>' . esc_html__('لاگی ثبت نشده است.', BERYON_TD) . '</p>';
        } else {
            echo '<table class="widefat striped beryon-table"><thead><tr><th>' . esc_html__('زمان', BERYON_TD) . '</th><th>' . esc_html__('رویداد', BERYON_TD) . '</th><th>' . esc_html__('نتیجه', BERYON_TD) . '</th><th>' . esc_html__('جزئیات', BERYON_TD) . '</th></tr></thead><tbody>';

            foreach ($logs as $log) {
                $ok = (bool) ($log['ok'] ?? false);
                // کلیدهای رکورد لاگ: time (یونیکس) و action (apply/dry_run/rollback/create)
                $log_time = (int) ($log['time'] ?? 0);

                echo '<tr><td>' . esc_html($log_time > 0 ? (string) \App\Jalali::format($log_time, 'Y/m/d H:i') : '—') . '</td>'
                    . '<td><code>' . esc_html((string) ($log['action'] ?? '')) . '</code></td>'
                    . '<td>' . ($ok ? '<span class="beryon-badge ok">موفق</span>' : '<span class="beryon-badge err">ناموفق</span>') . '</td>'
                    . '<td>' . esc_html((string) (($log['message'] ?? '') !== '' ? $log['message'] : ($log['error'] ?? ''))) . '</td></tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';

        // --- یکپارچگی ---
        echo '<div class="beryon-card">';
        echo '<h2 class="beryon-card-title">' . esc_html__('یکپارچگی فایل‌های هسته (FIM)', BERYON_TD) . '</h2>';
        $report = Security::verify();
        echo '<p>' . esc_html(\sprintf(__('آخرین اثر انگشت: %s فایل', BERYON_TD), (string) ($report['total'] ?? 0))) . '</p>';

        if (! empty($report['missing']) || ! empty($report['modified'])) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('تغییر غیرمجاز در فایل‌های هسته تشخیص داده شد:', BERYON_TD) . '</p><ul>';

            foreach (array_merge((array) ($report['missing'] ?? []), (array) ($report['modified'] ?? [])) as $file) {
                echo '<li><code>' . esc_html((string) $file) . '</code></li>';
            }

            echo '</ul></div>';
        } else {
            echo '<p><span class="beryon-badge ok">' . esc_html__('همه فایل‌ها سالم هستند', BERYON_TD) . '</span></p>';
        }

        echo '<p class="beryon-actions"><button type="button" class="button" data-beryon-action="fingerprint">' . esc_html__('بازسازی اثر انگشت', BERYON_TD) . '</button></p>';
        echo '</div>';

        self::footer();
        echo '</div>';
    }

    /**
     * رندر صفحه ابزارها.
     *
     * @return void
     */
    public static function render_tools(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\__('دسترسی مجاز نیست.', BERYON_TD));
        }

        $stats = Forms::stats();

        echo '<div class="wrap beryon-wrap" dir="rtl">';
        self::header(\__('ابزارهای قالب بریون', BERYON_TD), \__('داده‌های نمونه، حافظه نهان و خروجی‌گیری', BERYON_TD));

        echo '<nav class="beryon-tabs">';
        echo '<a class="beryon-tab" href="' . esc_url(\admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">' . esc_html__('تنظیمات', BERYON_TD) . '</a>';
        echo '<a class="beryon-tab" href="' . esc_url(\admin_url('admin.php?page=beryon-submissions')) . '">' . esc_html__('درخواست‌ها', BERYON_TD) . '</a>';
        echo '<a class="beryon-tab" href="' . esc_url(\admin_url('admin.php?page=beryon-updates')) . '">' . esc_html__('به‌روزرسانی', BERYON_TD) . '</a>';
        echo '<a class="beryon-tab is-active" href="' . esc_url(\admin_url('admin.php?page=beryon-tools')) . '" aria-current="page">' . esc_html__('ابزارها', BERYON_TD) . '</a>';
        echo '</nav>';

        echo '<div class="beryon-grid-2">';

        // آمار
        echo '<div class="beryon-card"><h2 class="beryon-card-title">' . esc_html__('آمار فرم‌ها', BERYON_TD) . '</h2>';
        echo '<table class="widefat striped beryon-table"><tbody>';
        echo '<tr><th>' . esc_html__('کل درخواست‌ها', BERYON_TD) . '</th><td><strong>' . esc_html((string) ($stats['total'] ?? 0)) . '</strong></td></tr>';
        echo '<tr><th>' . esc_html__('جدید (خوانده‌نشده)', BERYON_TD) . '</th><td>' . esc_html((string) ($stats['new'] ?? 0)) . '</td></tr>';
        echo '<tr><th>' . esc_html__('تماس', BERYON_TD) . '</th><td>' . esc_html((string) ($stats['contact'] ?? 0)) . '</td></tr>';
        echo '<tr><th>' . esc_html__('نظرات و شکایات', BERYON_TD) . '</th><td>' . esc_html((string) ($stats['feedback'] ?? 0)) . '</td></tr>';
        echo '<tr><th>' . esc_html__('نمایندگی', BERYON_TD) . '</th><td>' . esc_html((string) ($stats['agency'] ?? 0)) . '</td></tr>';
        echo '<tr><th>' . esc_html__('خبرنامه', BERYON_TD) . '</th><td>' . esc_html((string) ($stats['newsletter'] ?? 0)) . '</td></tr>';
        echo '</tbody></table></div>';

        // عملیات
        echo '<div class="beryon-card"><h2 class="beryon-card-title">' . esc_html__('عملیات', BERYON_TD) . '</h2>';

        echo self::action_button('beryon_flush_caches', \__('پاک‌سازی همه حافظه‌های نهان قالب', BERYON_TD), 'secondary');
        echo self::action_button('beryon_reseed', \__('اجرای دوباره داده‌های نمونه (محصولات/صفحات/مقالات)', BERYON_TD), 'secondary', true);
        echo self::action_button('beryon_reset_options', \__('بازنشانی تنظیمات به مقادیر پیش‌فرض', BERYON_TD), 'danger', true);

        echo '<p class="beryon-intro">' . esc_html__('برای اجرای بی‌صدا در سرور می‌توانید از WP-CLI استفاده کنید:', BERYON_TD) . '</p>';
        echo '<pre class="beryon-code" dir="ltr">wp beryon seed --force
wp beryon options reset --yes
wp beryon cache flush
wp beryon patch build --version=1.1.0 --dir=app --out=/tmp/patch.zip
wp beryon patch apply /tmp/patch.zip --dry-run
wp beryon integrity</pre>';
        echo '</div>';

        echo '</div>';
        self::footer();
        echo '</div>';
    }

    /**
     * رندر راهنما.
     *
     * @return void
     */
    public static function render_docs(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\__('دسترسی مجاز نیست.', BERYON_TD));
        }

        $readme = Updater::docs();

        echo '<div class="wrap beryon-wrap" dir="rtl">';
        self::header(\__('راهنمای قالب بریون', BERYON_TD), BERYON_VERSION);

        echo '<div class="beryon-card">';
        echo '<h2 class="beryon-card-title">' . esc_html__('نصب و راه‌اندازی', BERYON_TD) . '</h2>';
        echo '<ol class="beryon-history">';
        echo '<li>' . esc_html__('قالب را در wp-content/themes/beryon قرار دهید و فعال کنید.', BERYON_TD) . '</li>';
        echo '<li>' . esc_html__('در پوشه قالب، دستور composer install و سپس npm run build را اجرا کنید (فایل‌های build شده به‌صورت پیش‌فرض در public/assets قرار دارند، پس بدون Node هم قالب کار می‌کند).', BERYON_TD) . '</li>';
        echo '<li>' . esc_html__('در «ابزارها» روی «اجرای دوباره داده‌های نمونه» بزنید تا محصولات، دسته‌ها، صفحات و مقالات قالب درج شوند.', BERYON_TD) . '</li>';
        echo '<li>' . esc_html__('در «تنظیمات → خواندن»، صفحه اصلی را روی «صفحه ثابت» و صفحه نوشته‌ها را روی «وبلاگ» بگذارید.', BERYON_TD) . '</li>';
        echo '<li>' . esc_html__('پیوندهای یکتا را یک‌بار ذخیره کنید (تنظیمات → پیوندهای یکتا) تا ساختار آدرس‌ها بازنویسی شود.', BERYON_TD) . '</li>';
        echo '<li>' . esc_html__('برای سازگاری کامل با ووکامرس، افزونه WooCommerce را نصب کنید؛ قالب به‌صورت خودکار در «حالت کاتالوگ/استعلام» کار می‌کند.', BERYON_TD) . '</li>';
        echo '</ol></div>';

        if ($readme !== '') {
            echo '<div class="beryon-card"><h2 class="beryon-card-title">' . esc_html__('راهنمای بسته به‌روزرسانی', BERYON_TD) . '</h2>';
            echo '<pre class="beryon-code">' . esc_html($readme) . '</pre></div>';
        }

        self::footer();
        echo '</div>';
    }

    /* ------------------------------------------------------------------ *
     * عناصر UI
     * ------------------------------------------------------------------ */

    /**
     * سربرگ مشترک صفحات.
     *
     * @param string $title   عنوان
     * @param string $version نسخه
     * @return void
     */
    private static function header(string $title, string $version = ''): void
    {
        $logo = \beryon_logo_url();

        echo '<div class="beryon-head">';

        if ($logo !== '') {
            echo '<img class="beryon-head-logo" src="' . esc_url($logo) . '" alt="" width="74" height="56">';
        }

        echo '<div><h1>' . esc_html($title) . '</h1>';

        if ($version !== '') {
            echo '<p class="beryon-muted">' . esc_html($version) . '</p>';
        }

        echo '</div></div>';
    }

    /**
     * پانوشت مشترک.
     *
     * @return void
     */
    private static function footer(): void
    {
        echo '<p class="beryon-footer-note">' . esc_html(
            \sprintf(__('قالب بریون %s — ساخته‌شده برای شرکت بریان گوشت آمل. همه متن‌ها از طریق همین پنل قابل ویرایش‌اند.', BERYON_TD), BERYON_VERSION)
        ) . '</p>';
    }

    /**
     * دکمه‌های عملیات سریع.
     *
     * @return void
     */
    private static function quick_actions(): void
    {
        echo '<div class="beryon-card beryon-card--inline">';
        echo self::action_button('beryon_flush_caches', \__('پاک‌سازی حافظه نهان', BERYON_TD), 'secondary');
        echo self::action_button('beryon_reseed', \__('اجرای داده‌های نمونه', BERYON_TD), 'secondary', true);
        echo '<a class="button" href="' . esc_url(\admin_url('admin.php?page=beryon-updates')) . '">' . esc_html__('به‌روزرسانی قالب', BERYON_TD) . '</a> ';
        echo '<a class="button" href="' . esc_url(\admin_url('admin.php?page=beryon-submissions')) . '">' . esc_html__('درخواست‌های کاربران', BERYON_TD) . '</a>';
        echo '</div>';
    }

    /**
     * دکمه اجرای admin-post.
     *
     * @param string $action   اکشن
     * @param string $label    برچسب
     * @param string $style    استایل
     * @param bool   $confirm  تأیید قبل از اجرا
     * @return string
     */
    private static function action_button(string $action, string $label, string $style = 'secondary', bool $confirm = false): string
    {
        $url = \wp_nonce_url(\admin_url('admin-post.php?action=' . $action), $action);

        return '<form method="post" action="' . esc_url($url) . '" class="beryon-inline-form">'
            . ($confirm ? '<input type="hidden" name="confirm" value="1">' : '')
            . '<button type="submit" class="button button-' . esc_attr($style) . '">' . esc_html($label) . '</button></form> ';
    }

    /**
     * رندر یک فیلد.
     *
     * @param array<string,mixed> $field  تعریف
     * @param array<string,mixed> $values مقادیر
     * @return void
     */
    private static function field(array $field, array $values): void
    {
        $group = (string) ($field['group'] ?? '');
        $name  = (string) ($field['name'] ?? '');
        $type  = (string) ($field['type'] ?? 'text');
        $label = (string) ($field['label'] ?? $name);
        $help  = (string) ($field['help'] ?? '');

        if ($type === 'heading') {
            echo '<h3 class="beryon-field-head">' . esc_html($label) . '</h3>';

            if ($help !== '') {
                echo '<p class="beryon-muted">' . \wp_kses_post($help) . '</p>';
            }

            return;
        }

        $value = $values[$group][$name] ?? Settings::default($group . '.' . $name);

        $input_name = self::OPTION . '[' . $group . '][' . $name . ']';
        $input_id   = 'beryon-' . $group . '-' . $name;

        echo '<div class="beryon-field beryon-field--' . esc_attr($type) . '">';
        echo '<label for="' . esc_attr($input_id) . '">' . esc_html($label) . '</label>';
        echo '<div class="beryon-field-control">';

        switch ($type) {
            case 'checkbox':
            case 'switch':
                // چک‌باکسِ بدون تیک در فرم HTML ارسال نمی‌شود؛ ورودی پنهان «0» پیش از
                // آن می‌آید تا خاموش‌کردن کلید هم ذخیره شود (PHP آخرین مقدار را می‌گیرد).
                echo '<input type="hidden" name="' . esc_attr($input_name) . '" value="0">';
                echo '<label class="beryon-switch"><input type="checkbox" id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" value="1"' . checked((bool) $value, true, false) . '><span></span></label>';
                break;

            case 'textarea':
                echo '<textarea id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" rows="' . (int) ($field['rows'] ?? 4) . '" class="large-text code" dir="' . esc_attr((string) ($field['dir'] ?? 'rtl')) . '">' . esc_textarea((string) $value) . '</textarea>';
                break;

            case 'html':
                echo '<textarea id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" rows="' . (int) ($field['rows'] ?? 6) . '" class="large-text code" dir="rtl">' . esc_textarea((string) $value) . '</textarea>';
                echo '<p class="description">' . esc_html__('اجازه HTML محدود (b، i، a، br، span، strong). توکن‌ها: {count} {year} {brand} {company}', BERYON_TD) . '</p>';
                break;

            case 'tags':
            case 'lines':
                echo '<textarea id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" rows="' . (int) ($field['rows'] ?? 4) . '" class="large-text" dir="rtl">' . esc_textarea(implode("\n", (array) $value)) . '</textarea>';
                echo '<p class="description">' . esc_html__('هر مورد در یک خط جداگانه.', BERYON_TD) . '</p>';
                break;

            case 'list':
                echo '<input type="text" id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" value="' . esc_attr(implode('، ', (array) $value)) . '" class="regular-text" dir="rtl">';
                echo '<p class="description">' . esc_html__('موارد را با «،» یا «,» جدا کنید.', BERYON_TD) . '</p>';
                break;

            case 'select':
                echo '<select id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '">';

                foreach ((array) ($field['options'] ?? []) as $opt_value => $opt_label) {
                    if (is_array($opt_label)) {
                        $opt_value = (string) ($opt_label['value'] ?? '');
                        $opt_label = (string) ($opt_label['label'] ?? '');
                    }

                    echo '<option value="' . esc_attr((string) $opt_value) . '"' . selected((string) $value, (string) $opt_value, false) . '>' . esc_html((string) $opt_label) . '</option>';
                }

                echo '</select>';
                break;

            case 'color':
                echo '<input type="text" id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" value="' . esc_attr((string) $value) . '" class="beryon-color-text" dir="ltr">';
                echo '<input type="color" class="beryon-color" value="' . esc_attr((string) $value) . '" data-sync="' . esc_attr($input_id) . '" aria-label="' . esc_attr($label) . '">';
                break;

            case 'number':
            case 'int':
                echo '<input type="number" id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" value="' . esc_attr((string) $value) . '" class="small-text" dir="ltr"'
                    . (isset($field['min']) ? ' min="' . esc_attr((string) $field['min']) . '"' : '')
                    . (isset($field['max']) ? ' max="' . esc_attr((string) $field['max']) . '"' : '')
                    . (isset($field['step']) ? ' step="' . esc_attr((string) $field['step']) . '"' : '') . '>';
                break;

            case 'url':
                echo '<input type="url" id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" value="' . esc_attr((string) $value) . '" class="regular-text" dir="ltr" placeholder="https://">';
                break;

            case 'email':
                echo '<input type="email" id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" value="' . esc_attr((string) $value) . '" class="regular-text" dir="ltr">';
                break;

            case 'tel':
                echo '<input type="tel" id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" value="' . esc_attr((string) $value) . '" class="regular-text" dir="ltr">';
                break;

            case 'image':
            case 'media':
                self::media_field($input_id, $input_name, (int) $value, (string) ($field['label'] ?? ''), (string) ($field['fallback'] ?? ''));
                break;

            case 'repeater':
                self::repeater_field($input_id, $input_name, (array) $value, $field);
                break;

            default:
                $class = 'regular-text';

                if (! empty($field['wide'])) {
                    $class = 'large-text';
                }

                echo '<input type="text" id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" value="' . esc_attr((string) $value) . '" class="' . esc_attr($class) . '" dir="' . esc_attr((string) ($field['dir'] ?? 'rtl')) . '">';
                break;
        }

        if ($help !== '' && ! in_array($type, ['html', 'tags', 'lines', 'list'], true)) {
            echo '<p class="description">' . \wp_kses_post($help) . '</p>';
        }

        echo '</div></div>';
    }

    /**
     * فیلد انتخاب تصویر از کتابخانه رسانه.
     *
     * @param string $id       شناسه
     * @param string $name     نام فیلد
     * @param int    $value    شناسه پیوست
     * @param string $label    برچسب
     * @param string $fallback نام فایل پیش‌فرض قالب
     * @return void
     */
    private static function media_field(string $id, string $name, int $value, string $label, string $fallback): void
    {
        $url = $value > 0 ? (string) \wp_get_attachment_url($value) : ($fallback !== '' ? \beryon_img($fallback) : '');

        echo '<div class="beryon-media" data-media>';
        echo '<input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
        echo '<div class="beryon-media-preview">' . ($url !== '' ? '<img src="' . esc_url($url) . '" alt="" loading="lazy" decoding="async">' : '<span>' . esc_html__('بدون تصویر', BERYON_TD) . '</span>') . '</div>';
        echo '<div class="beryon-media-actions">';
        echo '<button type="button" class="button" data-media-select>' . esc_html__('انتخاب تصویر', BERYON_TD) . '</button> ';
        echo '<button type="button" class="button-link" data-media-clear>' . esc_html__('حذف', BERYON_TD) . '</button>';
        echo '</div></div>';

        if ($fallback !== '') {
            echo '<p class="description">' . esc_html(\sprintf(__('در صورت انتخاب‌نشدن، تصویر پیش‌فرض قالب استفاده می‌شود: %s', BERYON_TD), $fallback)) . '</p>';
        }
    }

    /**
     * فیلد تکرارشونده (ردیف‌های داینامیک).
     *
     * @param string              $id     شناسه
     * @param string              $name   نام
     * @param array<int,mixed>    $value  ردیف‌ها
     * @param array<string,mixed> $field  تعریف
     * @return void
     */
    private static function repeater_field(string $id, string $name, array $value, array $field): void
    {
        $schema = (array) ($field['row'] ?? []);
        $max    = (int) ($field['max'] ?? 40);

        if ($schema === []) {
            return;
        }

        echo '<div class="beryon-repeater" data-repeater data-max="' . esc_attr((string) $max) . '">';
        echo '<div class="beryon-repeater-head">';

        foreach ($schema as $sub) {
            echo '<span>' . esc_html((string) ($sub['label'] ?? $sub['name'])) . '</span>';
        }

        echo '<span></span></div>';
        echo '<div class="beryon-repeater-rows" data-rows>';

        foreach ($value as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            echo self::repeater_row($name, $index, $row, $schema);
        }

        echo '</div>';

        // الگوی ردیف جدید (برای JS)
        echo '<template data-row-template>' . self::repeater_row($name, '__INDEX__', [], $schema) . '</template>';

        echo '<button type="button" class="button" data-repeater-add>+ ' . esc_html__('افزودن ردیف', BERYON_TD) . '</button>';
        echo '</div>';
    }

    /**
     * یک ردیف از فیلد تکرارشونده.
     *
     * @param string               $name   نام کامل ورودی فیلد، مثال: beryon_theme_options[org][units]
     * @param int|string           $index  اندیس
     * @param array<string,mixed>  $row    مقدارها
     * @param array<int,mixed>     $schema طرح
     * @return string
     */
    private static function repeater_row(string $name, $index, array $row, array $schema): string
    {
        $html   = '<div class="beryon-repeater-row" data-row>';
        $prefix = trim((string) preg_replace('/[^A-Za-z0-9_]+/', '-', $name), '-');

        foreach ($schema as $sub) {
            $sub_name  = (string) $sub['name'];
            $sub_type  = (string) ($sub['type'] ?? 'text');
            $sub_value = $row[$sub_name] ?? ($sub['default'] ?? '');
            $field_id  = $prefix . '-' . (string) $index . '-' . $sub_name;

            // $name از پیش نام کامل ورودی است؛ پیش‌تر دوباره داخل نام Option پیچیده
            // می‌شد (beryon_theme_options[beryon_theme_options[…]]…) و PHP آن را
            // به کلیدی بی‌ربط تجزیه می‌کرد؛ در نتیجه ویرایش ردیف‌ها ذخیره نمی‌شد.
            $input = $name . '[' . $index . '][' . $sub_name . ']';

            if ($sub_type === 'select') {
                $html .= '<select name="' . esc_attr($input) . '" id="' . esc_attr($field_id) . '">';

                foreach ((array) ($sub['options'] ?? []) as $opt_value => $opt_label) {
                    $html .= '<option value="' . esc_attr((string) $opt_value) . '"' . selected(is_scalar($sub_value) ? (string) $sub_value : '', (string) $opt_value, false) . '>' . esc_html((string) $opt_label) . '</option>';
                }

                $html .= '</select>';
            } elseif ($sub_type === 'sublist') {
                $html .= '<textarea name="' . esc_attr($input) . '" id="' . esc_attr($field_id) . '" rows="3" dir="rtl">' . esc_textarea(self::sublist_text($sub_value)) . '</textarea>';
            } elseif ($sub_type === 'textarea') {
                $html .= '<textarea name="' . esc_attr($input) . '" id="' . esc_attr($field_id) . '" rows="2" dir="rtl">' . esc_textarea(is_scalar($sub_value) ? (string) $sub_value : '') . '</textarea>';
            } else {
                $html .= '<input type="text" name="' . esc_attr($input) . '" id="' . esc_attr($field_id) . '" value="' . esc_attr(is_scalar($sub_value) ? (string) $sub_value : '') . '" dir="' . esc_attr((string) ($sub['dir'] ?? 'rtl')) . '">';
            }
        }

        $html .= '<button type="button" class="button-link" data-row-remove title="' . esc_attr__('حذف ردیف', BERYON_TD) . '">✕</button></div>';

        return $html;
    }

    /* ------------------------------------------------------------------ *
     * اکشن‌های admin-post
     * ------------------------------------------------------------------ */

    /**
     * بازنشانی تنظیمات.
     *
     * @return void
     */
    public static function reset_options(): void
    {
        self::guard('beryon_reset_options');

        // مقادیر پیش‌فرض از فایل خود قالب (config/defaults.json) می‌آیند و از پیش
        // ساخت‌یافته‌اند؛ بدون گذر از پاک‌سازیِ فرم ذخیره می‌شوند تا عیناً همان
        // مقادیر بنشینند (پیش‌تر فهرست‌ها هنگام بازنشانی به «Array» تبدیل می‌شدند).
        \remove_filter('sanitize_option_' . self::OPTION, [self::class, 'sanitize']);
        \update_option(self::OPTION, Settings::defaults(), false);
        \add_filter('sanitize_option_' . self::OPTION, [self::class, 'sanitize']);
        Settings::flush();

        self::redirect_back(\__('تنظیمات به مقادیر پیش‌فرض بازنشانی شد.', BERYON_TD));
    }

    /**
     * پاک‌سازی حافظه‌های نهان.
     *
     * @return void
     */
    public static function flush_caches(): void
    {
        self::guard('beryon_flush_caches');

        Assets::flush_image_cache();
        Seo::flush_sitemap();
        \App\Products::flush_cache();
        \delete_transient('beryon_integrity_checked');
        \delete_transient('beryon_integrity_alert');

        if (\function_exists('wp_cache_flush')) {
            \wp_cache_flush();
        }

        self::redirect_back(\__('همه حافظه‌های نهان قالب پاک شد.', BERYON_TD));
    }

    /**
     * اجرای دوباره داده‌های نمونه.
     *
     * @return void
     */
    public static function reseed(): void
    {
        self::guard('beryon_reseed');

        $result = \App\Seeder::run(['force' => false]);

        self::redirect_back(\sprintf(
            \__('داده‌های نمونه اعمال شد: %s محصول، %s دسته، %s صفحه، %s نوشته.', BERYON_TD),
            (string) ($result['products'] ?? 0),
            (string) ($result['categories'] ?? 0),
            (string) ($result['pages'] ?? 0),
            (string) ($result['posts'] ?? 0)
        ));
    }

    /**
     * آپلود و اعمال پچ از فرم.
     *
     * @return void
     */
    public static function upload_patch(): void
    {
        self::guard('beryon_upload_patch');

        $flash_key = 'beryon_patch_flash_' . \get_current_user_id();

        if (empty($_FILES['patch']['tmp_name'])) {
            \set_transient($flash_key, [
                'type'    => 'error',
                'title'   => \__('فایلی انتخاب نشد', BERYON_TD),
                'message' => \__('یک بسته zip حاوی patch.json انتخاب کنید.', BERYON_TD),
            ], 60);

            self::redirect_back('', 'beryon-updates');
        }

        $dry_run = isset($_POST['dry_run']);
        $result  = PatchApplier::apply(
            (string) $_FILES['patch']['tmp_name'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            [
                'dry_run' => $dry_run,
                'user'    => \wp_get_current_user()->display_name,
            ]
        );

        if (! empty($result['ok'])) {
            \set_transient($flash_key, [
                'type'    => 'success',
                'title'   => $dry_run ? \__('اعتبارسنجی موفق (حالت آزمایشی)', BERYON_TD) : \__('پچ با موفقیت اعمال شد', BERYON_TD),
                'message' => esc_html((string) ($result['message'] ?? '')),
                'plan'    => (array) ($result['plan']['rows'] ?? []),
            ], 120);
        } else {
            \set_transient($flash_key, [
                'type'    => 'error',
                'title'   => \__('اعمال پچ ناموفق بود', BERYON_TD),
                'message' => esc_html((string) ($result['message'] ?? '')) . ' <code>' . esc_html((string) ($result['code'] ?? '')) . '</code>',
            ], 120);
        }

        self::redirect_back('', 'beryon-updates');
    }

    /**
     * بررسی nonce + صلاحیت.
     *
     * @param string $action نام اکشن
     * @return void
     */
    private static function guard(string $action): void
    {
        $nonce = isset($_REQUEST['_wpnonce']) ? (string) \sanitize_text_field(\wp_unslash($_REQUEST['_wpnonce'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        if (! \wp_verify_nonce($nonce, $action) || ! \current_user_can('manage_options')) {
            \wp_die(\__('نشست منقضی یا دسترسی نامجاز است.', BERYON_TD), '', ['response' => 403]);
        }
    }

    /**
     * بازگشت به صفحه.
     *
     * @param string $message پیام (اختیاری)
     * @param string $page    اسلاگ صفحه
     * @return void
     */
    private static function redirect_back(string $message = '', string $page = ''): void
    {
        if ($message !== '') {
            \set_transient('beryon_admin_flash_' . \get_current_user_id(), $message, 60);
        }

        $target = $page === '' ? self::PAGE_SLUG : $page;

        \wp_safe_redirect(\admin_url('admin.php?page=' . $target));
        exit;
    }
}
