<?php

/**
 * پیکربندی Acorn/Sage 11 — قالب بریون
 *
 * @package beryon
 */

defined('ABSPATH') || exit;

return [
    /*
    |--------------------------------------------------------------------------
    | نام و محیط برنامه
    |--------------------------------------------------------------------------
    */
    'name'      => \get_bloginfo('name', 'display') ?: 'Beryon',
    'env'       => defined('WP_ENV') ? WP_ENV : 'production',

    /*
    |--------------------------------------------------------------------------
    | مسیرهای داده و کش
    |--------------------------------------------------------------------------
    | برای سازگاری با میزبانی‌های اشتراکی، همه مسیرها نسبی به پوشه قالب‌اند.
    */
    'paths' => [
        'root'    => BERYON_PATH,
        'base'    => BERYON_PATH,
        'storage' => defined('WP_CONTENT_DIR') && is_writable(WP_CONTENT_DIR)
            ? WP_CONTENT_DIR . '/uploads/beryon-storage'
            : BERYON_PATH . '/storage',
    ],

    /*
    |--------------------------------------------------------------------------
    | سرویس‌پروایدرها
    |--------------------------------------------------------------------------
    */
    'providers' => [
        App\Providers\ThemeServiceProvider::class,
        App\Providers\ViewServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | aliases سازگاری
    |--------------------------------------------------------------------------
    */
    'aliases' => [
        'Beryon'   => App\Acorn::class,
        'Settings' => App\Settings::class,
        'Seo'      => App\Seo::class,
        'Assets'   => App\Assets::class,
    ],
];
