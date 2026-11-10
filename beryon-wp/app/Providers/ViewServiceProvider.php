<?php

/**
 * سرویس‌پروایدر نماها — قالب بریون (Acorn)
 * ------------------------------------------------------------------
 * داده مشترک Blade (تنظیمات، منوها، اطلاعات سایت) را برای همه نماها
 * فراهم می‌کند و Composerهای نما را ثبت می‌نماید.
 *
 * @package beryon
 */

namespace App\Providers;

use App\Nav;
use App\Products;
use App\Settings;

/**
 * کلاس سرویس‌پروایدر نما.
 */
final class ViewServiceProvider extends \Roots\Acorn\ServiceProvider
{
    /**
     * راه‌اندازی.
     *
     * @return void
     */
    public function boot(): void
    {
        \add_filter('sage/view/data', [self::class, 'view_data'], 10, 2);
    }

    /**
     * داده پیش‌فرض نماها.
     *
     * @param array<string,mixed> $data داده
     * @param string              $view نام نما
     * @return array<string,mixed>
     */
    public static function view_data(array $data, string $view = ''): array
    {
        $data['opt']       = static fn (string $key, $default = null) => Settings::opt($key, $default);
        $data['t']         = static fn (string $text): string => \beryon_tokens($text);
        $data['fa']        = static fn ($value): string => \beryon_fa($value);
        $data['options']   = Settings::all();
        $data['nav']       = Nav::items('primary');
        $data['nav_footer_quick'] = Nav::items('footer_quick');
        $data['nav_footer_cats']  = Nav::items('footer_cats');
        $data['categories'] = Products::categories();
        $data['total_count'] = Products::total_count();
        $data['is_rtl']    = \is_rtl();
        $data['site_name'] = (string) \get_bloginfo('name');

        return $data;
    }
}
