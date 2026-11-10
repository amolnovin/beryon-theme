<?php

/**
 * سازگاری ووکامرس — قالب بریون
 * ------------------------------------------------------------------
 * سایت بریون «فروشگاهی» نیست؛ کاتالوگ + استعلام قیمت عمده است. بنابراین:
 *   - حالت کاتالوگ: حذف قیمت، سبد خرید، افزودن به سبد و جست‌وجوی فروشگاهی
 *   - دکمه «استعلام قیمت عمده» جایگزین add-to-cart
 *   - گالری، تب‌ها و محصولات مرتبط با مارک‌آپ اختصاصی قالب رندر می‌شوند
 *   - سازگاری HPOS و قالب‌های override در پوشه woocommerce/
 * اگر در تنظیمات «حالت فروشگاه» فعال شود، همه‌چیز به رفتار استاندارد ووکامرس برمی‌گردد.
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * کلاس ووکامرس.
 */
final class WooCommerce
{
    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        if (! class_exists('\WooCommerce')) {
            return;
        }

        \add_action('after_setup_theme', [self::class, 'support'], 20);
        \add_action('init', [self::class, 'image_sizes']);

        \add_filter('woocommerce_enqueue_styles', '__return_empty_array');
        \add_filter('loop_shop_per_page', [self::class, 'per_page'], 20);
        \add_filter('loop_shop_columns', [self::class, 'columns'], 20);
        \add_filter('woocommerce_output_related_products_args', [self::class, 'related_args'], 20);
        \add_filter('woocommerce_product_thumbnails_columns', [self::class, 'thumb_columns']);
        \add_filter('woocommerce_breadcrumb_defaults', [self::class, 'breadcrumb_defaults']);
        \add_filter('woocommerce_display_item_meta', '__return_empty_string');
        \add_filter('woocommerce_product_get_rating_html', '__return_empty_string');
        \add_filter('woocommerce_show_page_title', '__return_false');
        \add_filter('woocommerce_template_loader_files', [self::class, 'template_files'], 10, 2);

        \add_action('wp_enqueue_scripts', [self::class, 'dequeue_styles'], 99);

        if (self::catalog_mode()) {
            // حذف عناصر فروش
            \remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
            \remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
            \remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
            \remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
            \remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
            \remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10);
            \remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10);
            \remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
            \remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
            \remove_action('woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10);
            \remove_action('woocommerce_archive_description', 'woocommerce_product_archive_description', 10);

            \add_filter('woocommerce_is_purchasable', '__return_false');
            \add_filter('woocommerce_product_single_add_to_cart_text', [self::class, 'inquiry_text']);
            \add_filter('woocommerce_product_add_to_cart_text', [self::class, 'inquiry_text']);
            \add_filter('woocommerce_add_to_cart_redirect', [self::class, 'inquiry_redirect']);

            // سبد خرید و حساب کاربری برای مهمان‌ها پنهان شود
            \add_filter('woocommerce_cart_needs_shipping', '__return_false');
            \add_action('wp_loaded', [self::class, 'hide_cart_endpoints'], 20);

            \add_action('woocommerce_single_product_summary', [self::class, 'inquiry_button'], 30);
            \add_action('woocommerce_after_single_product_summary', [self::class, 'related_section'], 25);
        }

        // تب‌های محصول با ساختار قالب (توضیحات | مشخصات | نگهداری)
        \add_filter('woocommerce_product_tabs', [self::class, 'product_tabs'], 98);
        \remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);

        // برگرداندن عنوان محصول به مارک‌آپ قالب
        \remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
        \add_action('woocommerce_single_product_summary', [self::class, 'product_title'], 5);
    }

    /**
     * آیا حالت کاتالوگ/استعلام فعال است؟
     *
     * @return bool
     */
    public static function catalog_mode(): bool
    {
        return (bool) Settings::opt('products.catalog_mode', true);
    }

    /**
     * پشتیبانی‌های قالب.
     *
     * @return void
     */
    public static function support(): void
    {
        \add_theme_support('woocommerce', [
            'thumbnail_image_width' => 480,
            'single_image_width'    => 900,
            'product_grid'          => [
                'default_rows'    => 4,
                'min_rows'        => 1,
                'default_columns' => 4,
                'min_columns'     => 1,
                'max_columns'     => 4,
            ],
        ]);
    }

    /**
     * اندازه تصاویر محصولات.
     *
     * @return void
     */
    public static function image_sizes(): void
    {
        // ووکامرس اندازه‌های قالب را می‌شناسد؛ فقط بازسازی در صورت نیاز
        if (\get_option('beryon_woo_sizes_done') !== BERYON_VERSION) {
            \update_option('beryon_woo_sizes_done', BERYON_VERSION, false);
        }
    }

    /**
     * حذف استایل‌های ووکامرس (قالب استایل اختصاصی دارد).
     *
     * @return void
     */
    public static function dequeue_styles(): void
    {
        if (Settings::opt('performance.remove_woo_css', true)) {
            \wp_dequeue_style('wc-blocks-style');
            \wp_dequeue_style('woocommerce-inline');
            \wp_deregister_style('select2');
            \wp_dequeue_style('select2');
        }
    }

    /**
     * تعداد محصول در صفحه فروشگاه.
     *
     * @return int
     */
    public static function per_page(): int
    {
        return (int) Settings::opt('products.per_page', 24);
    }

    /**
     * تعداد ستون‌ها.
     *
     * @return int
     */
    public static function columns(): int
    {
        return 4;
    }

    /**
     * ستون‌های بندانگشتی گالری.
     *
     * @return int
     */
    public static function thumb_columns(): int
    {
        return 4;
    }

    /**
     * آرگومان‌های محصولات مرتبط.
     *
     * @param array<string,mixed> $args آرگومان‌ها
     * @return array<string,mixed>
     */
    public static function related_args(array $args): array
    {
        $args['posts_per_page'] = (int) Settings::opt('products.related_count', 4);
        $args['orderby']        = 'rand';

        return $args;
    }

    /**
     * تنظیمات پیش‌فرض breadcrumb ووکامرس (استفاده نمی‌شود؛ breadcrumb قالب جایگزین است).
     *
     * @param array<string,mixed> $defaults پیش‌فرض‌ها
     * @return array<string,mixed>
     */
    public static function breadcrumb_defaults(array $defaults): array
    {
        $defaults['wrap_before'] = '<nav class="breadcrumb" aria-label="' . esc_attr__('مسیر صفحه', BERYON_TD) . '">';
        $defaults['wrap_after']  = '</nav>';
        $defaults['before']      = '';
        $defaults['after']       = '';
        $defaults['delimiter']   = ' <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><use href="#i-chevron-left"></use></svg> ';
        $defaults['home']        = \__('خانه', BERYON_TD);

        return $defaults;
    }

    /**
     * متن دکمه استعلام.
     *
     * @return string
     */
    public static function inquiry_text(): string
    {
        return (string) Settings::opt('products.inquiry_label', \__('استعلام قیمت عمده', BERYON_TD));
    }

    /**
     * هدایت تلاش افزودن به سبد → صفحه نمایندگی.
     *
     * @param string $url آدرس
     * @return string
     */
    public static function inquiry_redirect(string $url): string
    {
        return (string) \beryon_page_url('agency', 'agency');
    }

    /**
     * پنهان‌سازی مسیرهای سبد/پرداخت برای مهمان در حالت کاتالوگ.
     *
     * @return void
     */
    public static function hide_cart_endpoints(): void
    {
        if (! self::catalog_mode() || \is_user_logged_in()) {
            return;
        }

        if (\function_exists('is_cart') && (\is_cart() || \is_checkout())) {
            \wp_safe_redirect((string) \beryon_page_url('agency', 'agency'));
            exit;
        }
    }

    /**
     * عنوان محصول با مارک‌آپ قالب.
     *
     * @return void
     */
    public static function product_title(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        echo '<h1>' . esc_html($product->get_name()) . '</h1>';
    }

    /**
     * دکمه استعلام قیمت (جایگزین add-to-cart).
     *
     * @return void
     */
    public static function inquiry_button(): void
    {
        $agency  = (string) \beryon_page_url('agency', 'agency');
        $contact = (string) \beryon_page_url('contact-us', 'contact');

        echo '<div class="ps-actions">';
        echo '<a class="btn btn-primary" href="' . esc_url($agency) . '">' . esc_html(self::inquiry_text()) . '</a>';
        echo '<a class="btn btn-ghost" href="' . esc_url($contact) . '">' . esc_html__('تماس با واحد فروش', BERYON_TD) . '</a>';
        echo '</div>';
    }

    /**
     * بخش محصولات مرتبط با مارک‌آپ قالب.
     *
     * @return void
     */
    public static function related_section(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $related = Products::related($product->get_id(), (int) Settings::opt('products.related_count', 4));

        if ($related === []) {
            return;
        }

        echo '<div class="sec-head reveal" style="margin-top:80px">';
        echo '<span class="ribbon-tag">' . esc_html(\beryon_tokens((string) Settings::opt('products.related_tag', 'پیشنهادهای مشابه'))) . '</span>';
        echo '<h2>' . esc_html(\beryon_tokens((string) Settings::opt('products.related_title', 'سایر محصولات این دسته'))) . '</h2>';
        echo '<span class="underline"></span></div>';
        echo '<div class="grid products-grid">';

        foreach ($related as $item) {
            echo \beryon_view('components.product-card', ['product' => $item]); // phpcs:ignore WordPress.Security.EscapeOutput
        }

        echo '</div>';
    }

    /**
     * تب‌های محصول (توضیحات | مشخصات | نگهداری).
     *
     * @param array<string,array<string,mixed>> $tabs تب‌ها
     * @return array<string,array<string,mixed>>
     */
    public static function product_tabs(array $tabs): array
    {
        $keep = [];

        foreach (['description', 'additional_information', 'beryon_specs', 'beryon_keep'] as $key) {
            if (isset($tabs[$key])) {
                $keep[$key] = $tabs[$key];
            }
        }

        $keep['description']['title']    = (string) Settings::opt('products.tab_desc', \__('توضیحات محصول', BERYON_TD));
        $keep['description']['priority'] = 10;

        $product_id = \get_the_ID();
        $data       = $product_id ? Products::normalize($product_id) : [];

        $keep['beryon_specs'] = [
            'title'    => (string) Settings::opt('products.tab_spec', \__('مشخصات محصول', BERYON_TD)),
            'priority' => 20,
            'callback' => [self::class, 'tab_specs'],
            'data'     => $data,
        ];

        $keep['beryon_keep'] = [
            'title'    => (string) Settings::opt('products.tab_keep', \__('نگهداری و مصرف', BERYON_TD)),
            'priority' => 30,
            'callback' => [self::class, 'tab_keep'],
            'data'     => $data,
        ];

        unset($keep['additional_information']);

        return $keep;
    }

    /**
     * محتوای تب مشخصات.
     *
     * @return void
     */
    public static function tab_specs(): void
    {
        $product_id = (int) \get_the_ID();
        $data       = Products::normalize($product_id);

        echo \beryon_view('woocommerce.single-product.spec-table', ['product' => $data]); // phpcs:ignore WordPress.Security.EscapeOutput
    }

    /**
     * محتوای تب نگهداری.
     *
     * @return void
     */
    public static function tab_keep(): void
    {
        $product_id = (int) \get_the_ID();
        $data       = Products::normalize($product_id);
        $keep       = (string) ($data['keep_html'] ?? '');

        if ($keep === '') {
            $keep = (string) Settings::opt('products.default_keep', '');
        }

        if ($keep === '') {
            echo '<p>' . esc_html__('برای این محصول شرایط نگهداری ثبت نشده است.', BERYON_TD) . '</p>';

            return;
        }

        echo Products::lines_to_list($keep); // phpcs:ignore WordPress.Security.EscapeOutput
    }

    /**
     * افزودن قالب‌های پوشه woocommerce قالب به جست‌وجوی ووکامرس.
     *
     * @param array<int,string> $files        فایل‌ها
     * @param string            $default_file فایل پیش‌فرض
     * @return array<int,string>
     */
    public static function template_files(array $files, string $default_file): array
    {
        array_unshift($files, 'woocommerce/');

        return $files;
    }
}
