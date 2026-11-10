<?php

/**
 * بارگذار قالب بریون — لایه اتصال به وردپرس
 *
 * همه منطق قالب در app/ (فضای نام App\) نگهداری می‌شود؛ این فایل فقط
 * autoload را بارگذاری و ماژول‌ها را راه‌اندازی می‌کند.
 *
 * @package beryon
 */

defined('ABSPATH') || exit;

/* ------------------------------------------------------------------ *
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


/* ------------------------------------------------------------------ *
 * ۰-۲) نگهبان خطای مرگبار — هیچ‌وقت صفحه سفید تحویل نده
 * ------------------------------------------------------------------ */

/**
 * اگر PHP با خطای مرگبار متوقف شود، به‌جای صفحه سفید یک بلوک تشخیصی چاپ می‌شود.
 *
 * جزئیات فقط برای مدیر سایت یا وقتی WP_DEBUG روشن است نمایش داده می‌شود و
 * همیشه در error_log ثبت می‌گردد.
 *
 * @return void
 */
function beryon_fatal_guard(): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $done  = true;
    $error = error_get_last();

    if (! is_array($error)) {
        return;
    }

    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if (! in_array((int) $error['type'], $fatal, true)) {
        return;
    }

    $message = sprintf('[beryon][fatal] %s in %s:%d', $error['message'], $error['file'], $error['line']);

    error_log($message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

    $show = (defined('WP_DEBUG') && WP_DEBUG)
        || (function_exists('current_user_can') && current_user_can('manage_options'));

    if (! $show) {
        return;
    }

    if (! headers_sent()) {
        status_header(500);
    }

    echo '<div dir="rtl" style="max-width:820px;margin:32px auto;padding:20px 22px;background:#fff3f3;'
        . 'border:2px solid #d5232c;border-radius:14px;font:14px/2 Tahoma,Vazirmatn,sans-serif;color:#7a1116">'
        . '<strong style="font-size:16px">خطای مرگبار در قالب بریون</strong>'
        . '<pre dir="ltr" style="text-align:left;direction:ltr;background:#fff;border:1px solid #e6d9d2;'
        . 'border-radius:10px;padding:12px;margin:12px 0 0;overflow:auto;font:12px/1.8 monospace;'
        . 'white-space:pre-wrap">' . esc_html($message) . '</pre>'
        . '<p style="margin:12px 0 0">اگر این خطا پس از نصب قالب روی داد، نسخه PHP را در پیشخوان میزبانی بررسی کنید '
        . '(حداقل ۷.۴) و در صورت نیاز <code>composer install</code> را اجرا کنید.</p>'
        . '</div>';
}

add_action('shutdown', 'beryon_fatal_guard', 9999);


/* ------------------------------------------------------------------ *
 * ۱) مسیرها و ثابت‌های قالب
 * ------------------------------------------------------------------ */

/** ریشه قالب (بدون اسلش انتهایی) */
define('BERYON_PATH', __DIR__);

/**
 * نسخه قالب — منبع حقیقت برای سیستم به‌روزرسانی Patch.
 *
 * پیش‌تر این ثابت سخت‌کد '1.0.0' بود؛ در نتیجه پنل همیشه نسخه اشتباه نشان
 * می‌داد و بررسی from_version در PatchApplier هم بی‌معنی می‌شد. حالا نسخه از
 * سربرگ style.css خوانده می‌شود (تنها منبع حقیقت).
 */
$beryon_style_head = (string) file_get_contents(__DIR__ . '/style.css', false, null, 0, 4096);

if (preg_match('/^Version:\s*(.+)$/mi', $beryon_style_head, $beryon_version_match) === 1) {
    $beryon_version_match = trim($beryon_version_match[1]);
} else {
    $beryon_version_match = '';
}

define('BERYON_VERSION', $beryon_version_match !== '' ? $beryon_version_match : '1.0.4');
unset($beryon_style_head, $beryon_version_match);

/** دامنه متنی */
define('BERYON_TD', 'beryon');

/* ------------------------------------------------------------------ *
 * ۲) Autoload
 * ------------------------------------------------------------------ */

if (file_exists($beryon_autoload = BERYON_PATH . '/vendor/autoload.php')) {
    require_once $beryon_autoload;
} else {
    // چاره‌جویی: PSR-4 ساده تا قالب بدون composer هم بالا بیاید (فقط کلاس‌های App\).
    spl_autoload_register(
        /**
         * @param string $class نام کامل کلاس
         * @return void
         */
        function ($class) {
            if (! str_starts_with($class, 'App\\')) {
                return;
            }
            $file = BERYON_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_readable($file)) {
                require_once $file;
            }
        }
    );
}

/* ------------------------------------------------------------------ *
 * ۳) توابع قالب (Template Tags)
 * ------------------------------------------------------------------ */

require_once BERYON_PATH . '/app/helpers.php';

/* ------------------------------------------------------------------ *
 * ۴) راه‌اندازی ماژول‌ها
 * ------------------------------------------------------------------ */

/**
 * همه ماژول‌ها متد ایستای boot() دارند و هوک‌های خود را ثبت می‌کنند.
 * اگر Acorn ریشه‌ها (roots/acorn) فعال باشد، ثبت سرویس‌پروایدر از config/app.php
 * نیز انجام می‌شود؛ این فراخوانی‌ها idempotent هستند (دوبار boot نمی‌شوند).
 */
$beryon_modules = [
    App\Setup::class,      // پشتیبانی‌های قالب، منوها، اندازه تصاویر، سلسله‌مراتب Blade
    App\Assets::class,     // صف استایل/اسکریپت، فونت، preload، مسیر تصاویر
    App\Products::class,   // CPT/تاکسونومی محصول، داده نرمال‌شده، Caching
    App\Seo::class,        // متا، اسکیما، سایت‌map، متاباکس سئو
    App\Forms::class,      // REST فرم‌ها، جدول پیام‌ها، کد پیگیری
    App\Optimize::class,   // DOM، lazy-load، srcset، حذف اسکریپت‌های اضافی
    App\Security::class,   // هدرهای امنیتی، سخت‌سازی، یکپارچگی فایل‌ها
    App\Updater::class,    // به‌روزرسانی Patch-محور + نسخه + بک‌آپ + rollback
];

if (is_admin()) {
    $beryon_modules[] = App\Admin\SettingsPage::class; // پنل تنظیمات قالب
    $beryon_modules[] = App\Admin\Submissions::class; // صندوق پیام‌ها
}

if (class_exists('WooCommerce')) {
    $beryon_modules[] = App\WooCommerce::class;      // سازگاری ووکامرس (حالت کاتالوگ/استعلام)
}

foreach ($beryon_modules as $beryon_module) {
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

if (defined('WP_CLI') && WP_CLI && class_exists(App\Cli::class)) {
    WP_CLI::add_command('beryon', App\Cli::class);
}

/* ------------------------------------------------------------------ *
 * ۵) سرویس‌پروایدرهای Acorn (اختیاری — فقط در صورت نصب composer)
 * ------------------------------------------------------------------ */

if (function_exists('add_theme_support') && class_exists('Roots\\Acorn\\ServiceProvider')) {
    add_theme_support('sage');
}

/* ------------------------------------------------------------------ *
 * ۶) تنظیمات قالب و اکشن‌های راه‌اندازی
 * ------------------------------------------------------------------ */

/**
 * هنگام فعال‌سازی قالب: ساخت جدول پیام‌ها، درج مقادیر پیش‌فرض و پاک‌سازی پیوندها.
 */
add_action(
    'after_switch_theme',
    /** @return void */
    static function () {
        // فعال‌سازی نباید هرگز به صفحه سفید ختم شود: خطا ثبت و در پیشخوان
        // گزارش می‌شود و سایت با مقادیر پیش‌فرض config/defaults.json کار می‌کند.
        try {
            App\Forms::install_table();
            App\Settings::seed_defaults();
            App\Updater::install();
        } catch (Throwable $beryon_activation_error) {
            error_log('[beryon] فعال‌سازی: ' . $beryon_activation_error->getMessage()); // phpcs:ignore

            set_transient('beryon_activation_error', $beryon_activation_error->getMessage(), DAY_IN_SECONDS);

            add_action(
                'admin_notices',
                static function (): void {
                    $message = (string) get_transient('beryon_activation_error');

                    echo '<div class="notice notice-warning"><p>'
                        . esc_html__('راه‌اندازی اولیه قالب بریون کامل نشد؛ قالب با مقادیر پیش‌فرض کار می‌کند.', 'beryon')
                        . ($message !== '' ? ' <code>' . esc_html($message) . '</code>' : '')
                        . '</p></div>';
                }
            );
        }

        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules();
        }

        // پیام خوش‌آمدگویی در نخستین بارگذاری پیشخوان پس از همین فعال‌سازی نمایش داده می‌شود.
        update_option('beryon_welcome', 'pending', true);
    }
);

/**
 * پیام خوش‌آمدگویی پس از فعال‌سازی قالب (یک بار برای هر فعال‌سازی).
 *
 * وضعیت در Option «beryon_welcome» است: هنگام فعال‌سازی «pending» می‌شود و با
 * چاپ پیام به شماره نسخه تغییر می‌کند. سایتی که قالب را فقط به‌روزرسانی می‌کند
 * (بدون فعال‌سازی دوباره) پیام «فعال شد» نمی‌بیند.
 *
 * پیش‌تر پرچم یک Transient هفتگی بود (هر هفته دوباره اجرا می‌شد) و متد
 * SettingsPage::welcome_notice وجود نداشت؛ در PHP 8 همین هوک نخستین بارگذاری
 * پیشخوان را با خطای مرگبار متوقف می‌کرد.
 */
add_action(
    'admin_notices',
    /** @return void */
    static function () {
        $beryon_welcome = get_option('beryon_welcome');

        if ($beryon_welcome === false) {
            // نصب قدیمی (پیش از ۱.۰.۴): Option ساخته می‌شود تا در هر بارگذاری پیشخوان
            // برای کلید ناموجود کوئری زده نشود.
            add_option('beryon_welcome', 'none', '', true);

            return;
        }

        if ($beryon_welcome !== 'pending' || ! current_user_can('manage_options')) {
            return;
        }

        update_option('beryon_welcome', BERYON_VERSION, true);

        if (is_callable([App\Admin\SettingsPage::class, 'welcome_notice'])) {
            App\Admin\SettingsPage::welcome_notice();
        }
    }
);
