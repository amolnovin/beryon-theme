<?php

/**
 * سرویس‌پروایدر اصلی قالب بریون (Acorn/Sage 11)
 * ------------------------------------------------------------------
 * مسیر نماها، داده مشترک Blade و اتصال ماژول‌های قالب را در چرخه حیات
 * Laravel/Acorn ثبت می‌کند. اگر Acorn نصب نباشد، functions.php همان
 * ماژول‌ها را مستقیم boot می‌کند؛ بنابراین این پروایدر idempotent است.
 *
 * @package beryon
 */

namespace App\Providers;

use App\Acorn;
use App\Assets;
use App\Forms;
use App\Optimize;
use App\Products;
use App\Security;
use App\Seo;
use App\Settings;
use App\Setup;
use App\Updater;

/**
 * کلاس سرویس‌پروایدر قالب.
 */
final class ThemeServiceProvider extends \Roots\Acorn\ServiceProvider
{
    /**
     * ماژول‌هایی که یک‌بار boot می‌شوند.
     *
     * @var array<int,class-string>
     */
    private const MODULES = [
        Setup::class,
        Assets::class,
        Products::class,
        Seo::class,
        Forms::class,
        Optimize::class,
        Security::class,
        Updater::class,
    ];

    /**
     * آیا قبلاً boot شده است؟
     *
     * @var bool
     */
    private static $booted = false;

    /**
     * ثبت سرویس‌ها.
     *
     * @return void
     */
    public function register(): void
    {
        // مسیر نماها برای Blade
        $this->app->bind('beryon.views', static fn (): string => BERYON_PATH . '/resources/views');
    }

    /**
     * راه‌اندازی سرویس‌ها.
     *
     * @return void
     */
    public function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        Acorn::share([
            'beryon' => [
                'version' => defined('BERYON_VERSION') ? BERYON_VERSION : '1.0.0',
                'name'    => Settings::opt('general.brand_name', 'بریون'),
            ],
        ]);

        foreach (self::MODULES as $module) {
            if (class_exists($module) && method_exists($module, 'boot')) {
                $module::boot();
            }
        }

        if (is_admin()) {
            \App\Admin\SettingsPage::boot();
            \App\Admin\Submissions::boot();
        }

        if (class_exists('\WooCommerce')) {
            \App\WooCommerce::boot();
        }

        if (defined('WP_CLI') && WP_CLI && class_exists(\App\Cli::class)) {
            \WP_CLI::add_command('beryon', \App\Cli::class);
        }
    }
}
