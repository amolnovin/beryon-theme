<?php

/**
 * راه‌اندازی قالب بریون — پشتیبانی‌ها، منوها، اندازه تصاویر و سلسله‌مراتب Blade
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * کلاس راه‌اندازی.
 */
final class Setup
{
    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        \add_action('after_setup_theme', [self::class, 'setup']);
        \add_action('after_setup_theme', [self::class, 'image_sizes'], 20);
        \add_action('init', [self::class, 'load_textdomain']);

        \add_filter('sage/template', [self::class, 'template'], 10, 1);
        \add_filter('template_include', [self::class, 'template_include'], 99);
        \add_filter('theme_page_templates', [self::class, 'page_templates']);
        \add_filter('excerpt_length', [self::class, 'excerpt_length']);
        \add_filter('excerpt_more', [self::class, 'excerpt_more']);
        \add_filter('nav_menu_css_class', [self::class, 'nav_classes'], 10, 2);
    }

    /**
     * پشتیبانی‌های قالب.
     *
     * @return void
     */
    public static function setup(): void
    {
        // زبان و جهت
        \add_theme_support('title-tag');
        \add_theme_support('automatic-feed-links');
        \add_theme_support('post-thumbnails');
        \add_theme_support('responsive-embeds');
        \add_theme_support('html5', ['comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets']);
        \add_theme_support('customize-selective-refresh-widgets');
        \add_theme_support('wp-block-styles');
        \add_theme_support('editor-styles');
        \add_theme_support('align-wide');

        // لوگو و منوها
        \add_theme_support(
            'custom-logo',
            [
                'height'      => 112,
                'width'       => 148,
                'flex-height' => true,
                'flex-width'  => true,
            ]
        );

        \register_nav_menus(
            [
                'primary'      => \__('منوی اصلی (هدر)', BERYON_TD),
                'footer_quick' => \__('فوتر — دسترسی سریع', BERYON_TD),
                'footer_cats'  => \__('فوتر — گروه‌های محصولات', BERYON_TD),
            ]
        );

        // ووکامرس
        \add_theme_support('woocommerce');
        \add_theme_support('wc-product-gallery-zoom');
        \add_theme_support('wc-product-gallery-lightbox');
        \add_theme_support('wc-product-gallery-slider');

        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \add_action(
                'before_woocommerce_init',
                /** @return void */
                static function () {
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, false);
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('product_block_editor', __FILE__, false);
                }
            );
        }

        // استایل ویرایشگر
        $editor = Assets::resolve('css/editor.css', 'resources/css/editor.css');

        if ($editor['path'] !== '') {
            \add_editor_style(str_replace(BERYON_PATH . '/', '', $editor['path']));
        }
    }

    /**
     * اندازه‌های تصویر قالب (همه WebP/بهینه از راه فیلترهای Optimize).
     *
     * @return void
     */
    public static function image_sizes(): void
    {
        \add_image_size('beryon-hero', 1240, 920, false);      // هیرو صفحه اصلی
        \add_image_size('beryon-card', 640, 480, true);        // کارت محصول/مقاله
        \add_image_size('beryon-cat', 720, 480, true);         // کارت دسته‌بندی
        \add_image_size('beryon-thumb', 240, 240, true);       // بندانگشتی گالری/سایدبار
        \add_image_size('beryon-wide', 1200, 630, true);       // og:image
        \add_image_size('beryon-about', 900, 900, false);      // تصویر درباره/کارخانه
    }

    /**
     * بارگذاری دامنه متنی.
     *
     * @return void
     */
    public static function load_textdomain(): void
    {
        \load_theme_textdomain(BERYON_TD, BERYON_PATH . '/resources/lang');
    }

    /**
     * نگاشت سلسله‌مراتب قالب وردپرس → فایل‌های Blade (Sage).
     *
     * @param string $template نام قالب پیشنهادی وردپرس
     * @return string
     */
    public static function template(string $template): string
    {
        $template = preg_replace('/\.php$/', '', (string) $template) ?? $template;

        if (locate_template("views/{$template}.blade.php") !== '' || is_readable(BERYON_PATH . "/resources/views/{$template}.blade.php")) {
            return "{$template}.blade";
        }

        return $template;
    }

    /**
     * اطمینان از رندر Blade در همه مسیرها (از جمله قالب‌های صفحه دلخواه).
     *
     * @param string $template مسیر قالب
     * @return string
     */
    public static function template_include(string $template): string
    {
        // اگر وردپرس یک فایل .php معمولی از ریشه قالب انتخاب کرده باشد و
        // معادل Blade آن موجود باشد، Blade اولویت دارد.
        $relative = str_replace(\trailingslashit(BERYON_PATH), '', $template);
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        if ($relative !== '' && str_ends_with($relative, '.php')) {
            $base = basename($relative, '.php');
            $blade = "resources/views/{$base}.blade.php";

            if (is_readable(BERYON_PATH . '/' . $blade) && ! is_readable(BERYON_PATH . '/' . $relative)) {
                return BERYON_PATH . '/index.php';
            }
        }

        // مسیرهای ووکامرس: قالب افزونه انتخاب می‌شود، اما نمای Blade ما
        // (single-product ، archive-product ، taxonomy-product_cat ،
        // woocommerce/cart|checkout|myaccount) مارک‌آپ بریون را حفظ می‌کند.
        if (Template::is_woo_route()) {
            /**
             * اجازه لغو بازگردانی مسیر قالب ووکامرس به قالب بریون.
             *
             * @param bool   $hijack   آیا مسیر بازگردانی شود
             * @param string $template مسیر قالب انتخاب‌شده
             */
            if ((bool) \apply_filters('beryon/template/hijack', true, $template)) {
                return BERYON_PATH . '/index.php';
            }
        }

        return $template;
    }

    /**
     * ثبت قالب‌های صفحه (Template Name) از فایل‌های views/page-*.blade.php
     *
     * @param array<string,string> $templates قالب‌ها
     * @return array<string,string>
     */
    public static function page_templates(array $templates): array
    {
        $dir = BERYON_PATH . '/resources/views';

        foreach ((array) glob($dir . '/page-*.blade.php') as $file) {
            $name = basename((string) $file);
            $head = (string) file_get_contents((string) $file, false, null, 0, 600);

            if (preg_match('/Template Name:\s*(.+)/i', $head, $match)) {
                $templates['views/' . $name] = trim($match[1]);
            }
        }

        return $templates;
    }

    /**
     * طول چکیده.
     *
     * @return int
     */
    public static function excerpt_length(): int
    {
        return (int) Settings::opt('products.excerpt_length', 22);
    }

    /**
     * نشانگر ادامه چکیده.
     *
     * @return string
     */
    public static function excerpt_more(): string
    {
        return '…';
    }

    /**
     * کلاس آیتم‌های منو (افزودن has-dropdown برای زیرمنوها).
     *
     * @param array<int,string> $classes کلاس‌ها
     * @param \WP_Post          $item    آیتم منو
     * @return array<int,string>
     */
    public static function nav_classes(array $classes, \WP_Post $item): array
    {
        if (in_array('menu-item-has-children', $classes, true)) {
            $classes[] = 'has-dropdown';
        }

        return $classes;
    }
}
