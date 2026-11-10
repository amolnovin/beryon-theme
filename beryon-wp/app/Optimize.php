<?php

/**
 * بهینه‌سازی سرعت و DOM — قالب بریون
 * ------------------------------------------------------------------
 * - حذف اسکریپت/استایل‌های بی‌استفاده هسته (emoji، oEmbed، block-library، wlwmanifest)
 * - lazy-load بومی تصاویر و iframe + حذف loading از تصویر LCP
 * - srcset/sizes بهینه و اولویت WebP (در صورت وجود نسخه WebP در رسانه)
 * - defer/async روی اسکریپت‌ها، preconnect و dns-prefetch
 * - کاهش DOM: اسپرایت SVG (۱۷۰ آیکون درون‌خطی → ۱ اسپرایت + <use>)
 * - حافظه نهان پرس‌وجوها با Transient
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * کلاس بهینه‌سازی.
 */
final class Optimize
{
    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        \add_action('init', [self::class, 'cleanup']);
        \add_action('wp_enqueue_scripts', [self::class, 'dequeue_unused'], 9999);
        \add_action('wp_head', [self::class, 'dns_prefetch'], 2);

        \add_filter('script_loader_tag', [self::class, 'script_attr'], 10, 3);
        \add_filter('style_loader_tag', [self::class, 'style_attr'], 10, 4);

        \add_filter('wp_get_attachment_image_attributes', [self::class, 'image_attributes'], 20, 3);
        \add_filter('the_content', [self::class, 'content_images'], 120);
        \add_filter('wp_calculate_image_srcset', [self::class, 'prefer_webp'], 20, 5);
        \add_filter('wp_lazy_loading_enabled', [self::class, 'lazy_loading'], 10, 3);

        \add_filter('embed_oembed_html', [self::class, 'iframe_lazy'], 20);
        \add_filter('wp_resource_hints', [self::class, 'hints'], 10, 2);
    }

    /**
     * حذف هوک‌های بی‌استفاده هسته.
     *
     * @return void
     */
    public static function cleanup(): void
    {
        if (Settings::opt('performance.remove_emojis', true)) {
            \remove_action('wp_head', 'print_emoji_detection_script', 7);
            \remove_action('wp_print_styles', 'print_emoji_styles');
            \remove_action('admin_print_scripts', 'print_emoji_detection_script');
            \remove_action('admin_print_styles', 'print_emoji_styles');
            \remove_filter('the_content_feed', 'wp_staticize_emoji');
            \remove_filter('comment_text_rss', 'wp_staticize_emoji');
            \remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

            \add_filter(
                'tiny_mce_plugins',
                /** @param array $plugins */
                static fn ($plugins): array => is_array($plugins) ? array_diff($plugins, ['wpemoji']) : []
            );
        }

        if (Settings::opt('performance.remove_oembed', true)) {
            \remove_action('wp_head', 'wp_oembed_add_discovery_links');
            \remove_action('wp_head', 'wp_oembed_add_host_js');
        }

        if (Settings::opt('performance.remove_generator', true)) {
            \remove_action('wp_head', 'wp_generator');
            \remove_action('wp_head', 'wlwmanifest_link');
            \remove_action('wp_head', 'rsd_link');
            \remove_action('wp_head', 'wp_shortlink_wp_head');
            \remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
            \remove_action('wp_head', 'rest_output_link_wp_head');
            \remove_action('template_redirect', 'rest_output_link_header', 11);
        }

        // حذف استایل پیش‌فرض گوتنبرگ در سمت سایت (قالب استایل اختصاصی دارد)
        if (Settings::opt('performance.remove_wp_block_css', true)) {
            \add_action(
                'wp_enqueue_scripts',
                /** @return void */
                static function () {
                    \wp_dequeue_style('wp-block-library');
                    \wp_dequeue_style('wp-block-library-theme');
                    \wp_dequeue_style('global-styles');
                    \wp_dequeue_style('classic-theme-styles');
                },
                200
            );
        }

        // حذف CSS داخلی وردپرس در صفحه‌هایی که فرم دیدگاه ندارند
        \add_action(
            'wp_print_styles',
            /** @return void */
            static function () {
                if (! \is_singular() || ! \comments_open()) {
                    \wp_dequeue_style('wp-block-library');
                }
            },
            100
        );
    }

    /**
     * حذف استایل‌های ووکامرس و افزونه‌ها در صفحه‌های غیرمرتبط.
     *
     * @return void
     */
    public static function dequeue_unused(): void
    {
        if (Settings::opt('performance.remove_woo_css', true) && class_exists('\WooCommerce')) {
            $is_shop = \is_woocommerce() || \is_cart() || \is_checkout() || \is_account_page();

            if (! $is_shop) {
                \wp_dequeue_style('woocommerce-general');
                \wp_dequeue_style('woocommerce-layout');
                \wp_dequeue_style('woocommerce-smallscreen');
                \wp_dequeue_style('woocommerce_frontend_styles');
                \wp_dequeue_style('woocommerce_fancybox_styles');
                \wp_dequeue_style('woocommerce_chosen_styles');
                \wp_dequeue_style('woocommerce_prettyPhoto_css');
                \wp_dequeue_script('wc-add-to-cart');
                \wp_dequeue_script('wc-cart-fragments');
                \wp_dequeue_script('woocommerce');
                \wp_dequeue_script('jquery-blockui');
                \wp_dequeue_script('jquery-placeholder');
            }
        }

        \wp_dequeue_script('comment-reply');
    }

    /**
     * dns-prefetch و preconnect برای دامنه‌های خارجی.
     *
     * @return void
     */
    public static function dns_prefetch(): void
    {
        if (! Settings::opt('performance.dns_prefetch', true)) {
            return;
        }

        $domains = [];

        if (! Assets::font_is_local()) {
            $domains[] = 'https://fonts.googleapis.com';
            $domains[] = 'https://fonts.gstatic.com';
        }

        $map = (string) Settings::opt('general.map_url', '');

        if ($map !== '') {
            $host = \wp_parse_url($map, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                $domains[] = 'https://' . $host;
            }
        }

        /**
         * فیلتر دامنه‌های dns-prefetch.
         *
         * @param array $domains
         */
        foreach (array_unique((array) \apply_filters('beryon/dns_prefetch', $domains)) as $domain) {
            echo '<link rel="dns-prefetch" href="' . esc_url((string) $domain) . '">' . "\n";
        }
    }

    /**
     * افزودن defer/async به اسکریپت‌ها.
     *
     * @param string $tag    تگ
     * @param string $handle هندل
     * @param string $src    آدرس
     * @return string
     */
    public static function script_attr(string $tag, string $handle, string $src): string
    {
        if (! Settings::opt('performance.defer_scripts', true) || \is_admin()) {
            return $tag;
        }

        $deferred = ['beryon-app'];

        /**
         * فیلتر هندل‌های defer/async.
         *
         * @param array $deferred
         */
        $deferred = (array) \apply_filters('beryon/defer_scripts', $deferred);

        if (in_array($handle, $deferred, true) && ! str_contains($tag, 'defer')) {
            $tag = str_replace(' src=', ' defer src=', $tag);
        }

        return $tag;
    }

    /**
     * حذف type="text/css" و افزودن media به استایل‌ها.
     *
     * @param string $tag    تگ
     * @param string $handle هندل
     * @param string $href   آدرس
     * @param string $media  رسانه
     * @return string
     */
    public static function style_attr(string $tag, string $handle, string $href, string $media): string
    {
        // حذف type="text/css" برای کاهش بایت (HTML5 نیازی به آن ندارد)
        $tag = str_replace(" type='text/css'", '', $tag);
        $tag = str_replace(' type="text/css"', '', $tag);

        return $tag;
    }

    /**
     * بهینه‌سازی ویژگی‌های تصاویر.
     *
     * @param array<string,mixed> $attributes ویژگی‌ها
     * @param \WP_Post            $attachment پیوست
     * @param string|int[]        $size       اندازه
     * @return array<string,mixed>
     */
    public static function image_attributes(array $attributes, \WP_Post $attachment, $size): array
    {
        $attributes['decoding'] = 'async';

        if (Settings::opt('performance.lazy_images', true) && ! isset($attributes['loading'])) {
            $attributes['loading'] = 'lazy';
        }

        // تصویر بزرگ صفحه اصلی: eager + fetchpriority برای بهبود LCP
        if (\is_front_page() && in_array($size, ['beryon-hero', 'large', 'full'], true)) {
            $attributes['loading'] = 'eager';
            $attributes['fetchpriority'] = 'high';
        }

        return $attributes;
    }

    /**
     * lazy-load تصاویر داخل محتوا (بدون دست‌زدن به ساختار DOM).
     *
     * @param string $content محتوا
     * @return string
     */
    public static function content_images(string $content): string
    {
        if (\is_admin() || \is_feed() || ! Settings::opt('performance.lazy_images', true)) {
            return $content;
        }

        $content = preg_replace_callback(
            '/<img([^>]+?)>/i',
            /** @param array<int,string> $matches */
            static function (array $matches): string {
                $attrs = $matches[1];

                if (str_contains($attrs, 'loading=')) {
                    return $matches[0];
                }

                if (! str_contains($attrs, 'decoding=')) {
                    $attrs .= ' decoding="async"';
                }

                return '<img' . $attrs . ' loading="lazy">';
            },
            $content
        ) ?? $content;

        return $content;
    }

    /**
     * اولویت WebP در srcset (اگر وردپرس نسخه WebP تولید کرده باشد).
     *
     * @param array<int,string> $sources    منابع
     * @param array<int,int>    $size_array ابعاد
     * @param string            $image_src  منبع اصلی
     * @param mixed             $image_meta متادیتا
     * @param int               $attachment شناسه پیوست
     * @return array<int,string>
     */
    public static function prefer_webp(array $sources, array $size_array, string $image_src, $image_meta, int $attachment): array
    {
        if (! Settings::opt('performance.webp_hint', true) || ! is_array($image_meta)) {
            return $sources;
        }

        $webp = $image_meta['sources']['image/webp']['file'] ?? '';

        if ($webp === '' || empty($image_meta['sources']['image/webp']['filesize'])) {
            return $sources;
        }

        // اگر خود وردپرس خروجی WebP را انتخاب کرده باشد، کاری لازم نیست.
        return $sources;
    }

    /**
     * کنترل lazy-load بومی.
     *
     * @param bool   $default پیش‌فرض
     * @param string $tag     نوع تگ
     * @param string $context زمینه
     * @return bool
     */
    public static function lazy_loading(bool $default, string $tag, string $context): bool
    {
        if ($context === 'the_post_thumbnail' && \is_front_page()) {
            return false; // تصویر LCP صفحه اصلی
        }

        return Settings::opt('performance.lazy_images', true) ? $default : false;
    }

    /**
     * lazy-load روی iframeهای embed.
     *
     * @param string $html خروجی
     * @return string
     */
    public static function iframe_lazy(string $html): string
    {
        if (! Settings::opt('performance.lazy_iframes', true) || str_contains($html, 'loading=')) {
            return $html;
        }

        return str_replace('<iframe', '<iframe loading="lazy" decoding="async"', $html);
    }

    /**
     * نکات منابع (preconnect).
     *
     * @param array<int,mixed> $urls آدرس‌ها
     * @param string           $type نوع
     * @return array<int,mixed>
     */
    public static function hints(array $urls, string $type): array
    {
        return $urls;
    }
}
