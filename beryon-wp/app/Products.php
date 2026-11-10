<?php

/**
 * محصولات بریون — ثبت نوع محتوا، داده نرمال‌شده و حافظه نهان
 * ------------------------------------------------------------------
 * دو حالت:
 *   ۱) ووکامرس فعال  → از post type «product» و تاکسونومی «product_cat» استفاده می‌شود
 *      و قالب در «حالت کاتالوگ» (بدون قیمت و سبد خرید) کار می‌کند.
 *   ۲) بدون ووکامرس  → نوع محتوای داخلی «beryon_product» با تاکسونومی «product_cat»
 *      ثبت می‌شود تا سایت بدون افزونه جانبی کامل باشد.
 *
 * در هر دو حالت خروجی توابع این کلاس یکسان است (آرایه نرمال‌شده) و بنابراین
 * قالب‌های Blade بدون تغییر در هر دو حالت یکسان رندر می‌شوند.
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * کلاس محصولات.
 */
final class Products
{
    /** نوع محتوای داخلی */
    public const CPT = 'beryon_product';

    /** تاکسونومی داخلی دسته محصول */
    public const TAX = 'product_cat';

    /** کلیدهای متای محصول */
    public const META = [
        'meat'       => '_beryon_meat_percent',
        'meat_type'  => '_beryon_meat_type',
        'unit_weight' => '_beryon_unit_weight',
        'packaging'  => '_beryon_packaging',
        'shelf_life' => '_beryon_shelf_life',
        'storage'    => '_beryon_storage',
        'group'      => '_beryon_group',
        'keep'       => '_beryon_keep_html',
        'desc_extra' => '_beryon_desc_html',
    ];

    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        \add_action('init', [self::class, 'register']);
        \add_action('save_post_' . self::CPT, [self::class, 'save_meta'], 10, 2);
        \add_action('save_post_product', [self::class, 'save_meta'], 10, 2);
        \add_action('add_meta_boxes', [self::class, 'meta_boxes']);

        // پاک‌سازی حافظه نهان هنگام تغییر محصولات
        foreach (['save_post', 'deleted_post', 'wp_insert_term', 'edited_term', 'delete_term'] as $hook) {
            \add_action($hook, [self::class, 'flush_cache']);
        }
    }

    /**
     * نوع محتوای فعال.
     *
     * @return string
     */
    public static function post_type(): string
    {
        return self::woo_active() ? 'product' : self::CPT;
    }

    /**
     * تاکسونومی فعال دسته محصولات.
     *
     * @return string
     */
    public static function taxonomy(): string
    {
        return self::TAX;
    }

    /**
     * آیا ووکامرس فعال است؟
     *
     * @return bool
     */
    public static function woo_active(): bool
    {
        return class_exists('\WooCommerce');
    }

    /**
     * ثبت نوع محتوا و تاکسونومی.
     *
     * @return void
     */
    public static function register(): void
    {
        // اگر ووکامرس فعال است، product_cat از قبل ثبت شده؛ فقط CPT داخلی (غیرعمومی) ثبت می‌شود.
        $woo = self::woo_active();

        \register_post_type(
            self::CPT,
            [
                'labels'          => [
                    'name'          => \__('محصولات بریون (داخلی)', BERYON_TD),
                    'singular_name' => \__('محصول', BERYON_TD),
                    'add_new_item'  => \__('افزودن محصول جدید', BERYON_TD),
                    'edit_item'     => \__('ویرایش محصول', BERYON_TD),
                    'search_items'  => \__('جست‌وجوی محصولات', BERYON_TD),
                    'not_found'     => \__('محصولی یافت نشد', BERYON_TD),
                ],
                'public'          => ! $woo,
                'show_ui'         => true,
                'show_in_menu'    => true,
                'menu_icon'       => 'dashicons-carrot',
                'menu_position'   => 26,
                'show_in_rest'    => true,
                'has_archive'     => $woo ? false : 'products',
                'rewrite'         => $woo ? false : ['slug' => 'productall', 'with_front' => false],
                'supports'        => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'page-attributes'],
                'taxonomies'      => $woo ? [] : [self::TAX, 'product_tag'],
                'exclude_from_search' => $woo,
                'publicly_queryable'  => ! $woo,
            ]
        );

        if (! taxonomy_exists(self::TAX)) {
            \register_taxonomy(
                self::TAX,
                [self::CPT],
                [
                    'labels'            => [
                        'name'          => \__('گروه‌های محصولات', BERYON_TD),
                        'singular_name' => \__('گروه محصول', BERYON_TD),
                        'search_items'  => \__('جست‌وجوی گروه‌ها', BERYON_TD),
                        'all_items'     => \__('همه گروه‌ها', BERYON_TD),
                        'edit_item'     => \__('ویرایش گروه', BERYON_TD),
                        'add_new_item'  => \__('افزودن گروه جدید', BERYON_TD),
                    ],
                    'public'            => true,
                    'hierarchical'      => true,
                    'show_ui'           => true,
                    'show_in_rest'      => true,
                    'show_admin_column' => true,
                    'rewrite'           => ['slug' => 'product-category', 'with_front' => false],
                ]
            );
        }

        if (! taxonomy_exists('product_tag')) {
            \register_taxonomy(
                'product_tag',
                [self::CPT],
                [
                    'labels'       => [
                        'name'          => \__('برچسب‌های محصول', BERYON_TD),
                        'singular_name' => \__('برچسب محصول', BERYON_TD),
                    ],
                    'public'       => true,
                    'hierarchical' => false,
                    'show_ui'      => true,
                    'show_in_rest' => true,
                    'rewrite'      => ['slug' => 'product-tag', 'with_front' => false],
                ]
            );
        }

        // ثبت اصطلاحات پیش‌فرض گروه‌ها
        self::ensure_terms();
    }

    /**
     * اطمینان از وجود ۵ گروه محصولی پیش‌فرض.
     *
     * @return void
     */
    public static function ensure_terms(): void
    {
        $cats = self::catalog()['categories'];

        foreach ($cats as $slug => $cat) {
            if (! \term_exists($slug, self::TAX)) {
                $inserted = \wp_insert_term($cat['name'], self::TAX, ['slug' => $slug, 'description' => $cat['desc'] ?? '']);

                if (! is_wp_error($inserted) && isset($inserted['term_id']) && ! empty($cat['image'])) {
                    $attachment = Assets::attachment_by_name($cat['image']);

                    if ($attachment > 0) {
                        \update_term_meta((int) $inserted['term_id'], 'thumbnail_id', $attachment);
                    }
                }
            }
        }
    }

    /**
     * کاتالوگ مرجع (داده اولیه قالب که از config/catalog.json خوانده می‌شود).
     *
     * @return array{categories:array<string,array<string,mixed>>,products:array<int,array<string,mixed>>}
     */
    public static function catalog(): array
    {
        static $catalog = null;

        if ($catalog !== null) {
            return $catalog;
        }

        $file = BERYON_PATH . '/config/catalog.json';
        $data = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;

        $catalog = is_array($data) && isset($data['products'])
            ? ['categories' => (array) ($data['categories'] ?? []), 'products' => (array) $data['products']]
            : ['categories' => [], 'products' => []];

        /**
         * فیلتر کاتالوگ مرجع.
         *
         * @param array $catalog
         */
        return (array) \apply_filters('beryon/catalog', $catalog);
    }

    /* ============================================================== *
     * پرس‌وجو و نرمال‌سازی
     * ============================================================== */

    /**
     * دریافت محصولات به‌صورت آرایه نرمال‌شده.
     *
     * @param array<string,mixed> $args کلیدها: category, tag, featured, limit, exclude, orderby, search, ids
     * @return array<int,array<string,mixed>>
     */
    public static function query(array $args = []): array
    {
        $defaults = [
            'category' => '',
            'tag'      => '',
            'featured' => false,
            'limit'    => (int) Settings::opt('products.grid_limit', 60),
            'exclude'  => [],
            'orderby'  => 'menu_order',
            'search'   => '',
            'ids'      => [],
        ];

        $args = wp_parse_args($args, $defaults);

        $cache_key = 'beryon_products_' . substr(md5(wp_json_encode($args)), 0, 20);
        $cached    = \get_transient($cache_key);

        if (is_array($cached)) {
            return $cached;
        }

        $post_type  = self::post_type();
        $query_args = [
            'post_type'              => $post_type,
            'post_status'            => 'publish',
            'posts_per_page'         => max(1, (int) $args['limit']),
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'orderby'                => in_array($args['orderby'], ['menu_order', 'title', 'date', 'rand'], true) ? $args['orderby'] : 'menu_order',
            'order'                  => 'ASC',
            'fields'                 => 'ids',
        ];

        if ($args['orderby'] === 'menu_order') {
            $query_args['orderby'] = ['menu_order' => 'ASC', 'title' => 'ASC'];
        }

        if (! empty($args['ids'])) {
            $query_args['post__in'] = array_map('intval', (array) $args['ids']);
            $query_args['orderby']  = 'post__in';
        }

        if (! empty($args['exclude'])) {
            $query_args['post__not_in'] = array_map('intval', (array) $args['exclude']);
        }

        if ($args['search'] !== '') {
            $query_args['s'] = (string) $args['search'];
        }

        if ($args['category'] !== '' && taxonomy_exists(self::TAX)) {
            $query_args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery
                [
                    'taxonomy' => self::TAX,
                    'field'    => 'slug',
                    'terms'    => (array) $args['category'],
                ],
            ];
        }

        if ($args['tag'] !== '' && taxonomy_exists('product_tag')) {
            $query_args['tax_query'][] = [ // phpcs:ignore WordPress.DB.SlowDBQuery
                'taxonomy' => 'product_tag',
                'field'    => 'slug',
                'terms'    => (array) $args['tag'],
            ];
        }

        if ($args['featured']) {
            $query_args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery
                ['key' => '_beryon_featured', 'value' => '1'],
            ];
        }

        /**
         * فیلتر آرگومان‌های پرس‌وجوی محصولات.
         *
         * @param array $query_args
         * @param array $args
         */
        $query_args = (array) \apply_filters('beryon/products/query_args', $query_args, $args);

        $query = new \WP_Query($query_args);
        $items = array_map([self::class, 'normalize'], $query->posts);
        $items = array_values(array_filter($items));

        \set_transient($cache_key, $items, (int) Settings::opt('performance.cache_ttl', 3600));

        return $items;
    }

    /**
     * نرمال‌سازی یک محصول (ووکامرس یا CPT داخلی) به آرایه یک‌شکل.
     *
     * @param int|\WP_Post $post محصول
     * @return array<string,mixed>
     */
    public static function normalize($post): array
    {
        $post = \get_post($post);

        if (! $post instanceof \WP_Post) {
            return [];
        }

        $terms = \get_the_terms($post, self::TAX);
        $term  = is_array($terms) && isset($terms[0]) ? $terms[0] : null;
        $term_slug = $term ? (string) $term->slug : '';
        $cat_short = (string) (self::catalog()['categories'][$term_slug]['short'] ?? ($term ? $term->name : ''));

        $tags = \get_the_terms($post, 'product_tag');
        $tag_slugs = is_array($tags) ? array_map(
            /** @param \WP_Term $tag */
            static fn ($tag): string => $tag->slug,
            $tags
        ) : [];
        $tag_names = is_array($tags) ? array_map(
            /** @param \WP_Term $tag */
            static fn ($tag): string => $tag->name,
            $tags
        ) : [];

        $meat = \get_post_meta($post->ID, self::META['meat'], true);
        $image = self::product_image($post);
        $description = self::product_description($post);

        $badges = array_slice(array_values(array_filter($tag_names)), 0, 2);

        // «نوع محصول» در جدول مشخصات = اولین برچسب (کوکتل، پنیری، هات‌داگ و…)
        $type_label = $tag_names !== [] ? (string) $tag_names[0] : (string) (self::catalog()['categories'][$term ? $term->slug : '']['short'] ?? '');

        if ($meat === '' || $meat === null) {
            $group = (string) \get_post_meta($post->ID, self::META['group'], true);

            if ($group === 'frozen' || ($term && str_contains((string) $term->slug, 'frozen'))) {
                array_unshift($badges, \__('آماده طبخ', BERYON_TD));
            } elseif ($group === 'plant' || ($term && str_contains((string) $term->slug, 'plant'))) {
                array_unshift($badges, \__('گیاهی', BERYON_TD));
            }
        }

        return [
            'id'          => $post->ID,
            'title'       => get_the_title($post),
            'link'        => (string) get_permalink($post),
            'image'       => $image,
            'category'    => $term ? (string) $term->slug : '',
            'cat_name'    => $term ? (string) $term->name : '',
            'cat_link'    => $term ? (string) \get_term_link($term) : '',
            'meat'        => $meat === '' || $meat === null ? null : (int) \beryon_en_digits((string) $meat),
            'meat_type'   => (string) \get_post_meta($post->ID, self::META['meat_type'], true),
            'unit_weight' => (string) \get_post_meta($post->ID, self::META['unit_weight'], true),
            'packaging'   => (string) \get_post_meta($post->ID, self::META['packaging'], true),
            'shelf_life'  => (string) \get_post_meta($post->ID, self::META['shelf_life'], true),
            'storage'     => (string) \get_post_meta($post->ID, self::META['storage'], true),
            'keep_html'   => (string) \get_post_meta($post->ID, self::META['keep'], true),
            'desc_html'   => (string) \get_post_meta($post->ID, self::META['desc_extra'], true),
            'short'       => $description,
            'tags'        => $tag_slugs,
            'badges'      => $badges,
            'featured'    => (bool) \get_post_meta($post->ID, '_beryon_featured', true),
        ];
    }

    /**
     * تصویر محصول (شاخص → گروه → پیش‌فرض قالب).
     *
     * @param \WP_Post $post محصول
     * @return string
     */
    public static function product_image(\WP_Post $post): string
    {
        if (\has_post_thumbnail($post)) {
            $url = \get_the_post_thumbnail_url($post, 'beryon-card');

            if ($url) {
                return (string) $url;
            }
        }

        $terms = \get_the_terms($post, self::TAX);

        if (is_array($terms) && isset($terms[0])) {
            $thumb_id = (int) \get_term_meta($terms[0]->term_id, 'thumbnail_id', true);

            if ($thumb_id > 0) {
                $url = \wp_get_attachment_image_url($thumb_id, 'beryon-card');

                if ($url) {
                    return (string) $url;
                }
            }

            $catalog = self::catalog();
            $image   = $catalog['categories'][$terms[0]->slug]['image'] ?? '';

            if ($image !== '') {
                return Assets::image_url((string) $image);
            }
        }

        return Assets::image_url((string) Settings::opt('products.default_image', 'cat-sausage.webp'));
    }

    /**
     * توضیح کوتاه محصول.
     *
     * @param \WP_Post $post محصول
     * @return string
     */
    public static function product_description(\WP_Post $post): string
    {
        if ($post->post_excerpt !== '') {
            return \beryon_excerpt_text(wp_strip_all_tags($post->post_excerpt), 120);
        }

        if (self::woo_active() && \function_exists('wc_get_product')) {
            $product = \wc_get_product($post->ID);

            if ($product instanceof \WC_Product && $product->get_short_description() !== '') {
                return \beryon_excerpt_text(wp_strip_all_tags($product->get_short_description()), 120);
            }
        }

        return \beryon_excerpt_text((string) $post->post_content, 120);
    }

    /**
     * دسته‌های محصولات با تعداد و تصویر.
     *
     * @param string $exclude اسلاگ دسته‌ای که باید حذف شود
     * @return array<int,array<string,mixed>>
     */
    public static function categories(string $exclude = ''): array
    {
        $cache_key = 'beryon_cats_' . md5($exclude);
        $cached    = \get_transient($cache_key);

        if (is_array($cached)) {
            return $cached;
        }

        $terms = \get_terms(
            [
                'taxonomy'   => self::TAX,
                'hide_empty' => false,
                'orderby'    => 'term_order',
                'order'      => 'ASC',
            ]
        );

        $order  = array_keys(self::catalog()['categories']);
        $items  = [];

        if (is_array($terms)) {
            foreach ($terms as $term) {
                if (! $term instanceof \WP_Term || $term->slug === $exclude) {
                    continue;
                }

                $reference = self::catalog()['categories'][$term->slug] ?? [];
                $image     = Assets::image_url((string) \get_term_meta($term->term_id, '_beryon_image', true) ?: (string) ($reference['image'] ?? 'logo.png'));
                $chips     = \get_term_meta($term->term_id, '_beryon_chips', true);

                $items[] = [
                    'term_id'     => $term->term_id,
                    'slug'        => $term->slug,
                    'name'        => $term->name,
                    'link'        => (string) \get_term_link($term),
                    'count'       => (int) $term->count,
                    'image'       => $image,
                    'desc'        => (string) ($term->description ?: ($reference['desc'] ?? '')),
                    'short'       => (string) (\get_term_meta($term->term_id, '_beryon_short', true) ?: ($reference['short'] ?? $term->name)),
                    'hero'        => (string) (\get_term_meta($term->term_id, '_beryon_hero', true) ?: ($reference['hero'] ?? $term->name)),
                    'lead'        => (string) (\get_term_meta($term->term_id, '_beryon_lead', true) ?: ($reference['lead'] ?? '')),
                    'chips'       => is_array($chips) && $chips !== [] ? $chips : (array) ($reference['chips'] ?? []),
                    'chips_label' => (string) (\get_term_meta($term->term_id, '_beryon_chips_label', true) ?: ($reference['chips_label'] ?? '')),
                    'placeholder' => (string) (\get_term_meta($term->term_id, '_beryon_placeholder', true) ?: ($reference['placeholder'] ?? '')),
                    'sr'          => (string) (\get_term_meta($term->term_id, '_beryon_sr', true) ?: ($reference['sr'] ?? $term->name)),
                    'seo_html'    => (string) (\get_term_meta($term->term_id, '_beryon_seo_html', true) ?: ($reference['seo'] ?? '')),
                ];
            }
        }

        // ترتیب کاتالوگ مرجع (سوسیس، کالباس، ژامبون، انجمادی، گیاهی)
        \usort(
            $items,
            /** @param array<string,mixed> $a */
            static function (array $a, array $b) use ($order): int {
                $ai = array_search($a['slug'], $order, true);
                $bi = array_search($b['slug'], $order, true);

                return ($ai === false ? 99 : $ai) <=> ($bi === false ? 99 : $bi);
            }
        );

        \set_transient($cache_key, $items, (int) Settings::opt('performance.cache_ttl', 3600));

        return $items;
    }

    /**
     * تعداد محصولات یک دسته.
     *
     * @param string $slug اسلاگ دسته
     * @return int
     */
    public static function category_count(string $slug): int
    {
        $term = \get_term_by('slug', $slug, self::TAX);

        return $term instanceof \WP_Term ? (int) $term->count : 0;
    }

    /**
     * تعداد کل محصولات.
     *
     * @return int
     */
    public static function total_count(): int
    {
        $count = (int) \get_transient('beryon_products_total');

        if ($count > 0) {
            return $count;
        }

        $counts = \wp_count_posts(self::post_type());
        $count  = isset($counts->publish) ? (int) $counts->publish : 0;

        if ($count > 0) {
            \set_transient('beryon_products_total', $count, (int) Settings::opt('performance.cache_ttl', 3600));
        }

        return $count;
    }

    /**
     * محصولات مرتبط (هم‌دسته).
     *
     * @param \WP_Post|int $post  محصول جاری
     * @param int          $limit تعداد
     * @return array<int,array<string,mixed>>
     */
    public static function related($post, int $limit = 4): array
    {
        $post = \get_post($post);

        if (! $post instanceof \WP_Post) {
            return [];
        }

        $terms = \get_the_terms($post, self::TAX);
        $slug  = is_array($terms) && isset($terms[0]) ? $terms[0]->slug : '';

        return self::query(
            [
                'category' => $slug,
                'limit'    => $limit + 1,
                'exclude'  => [$post->ID],
                'orderby'  => 'rand',
            ]
        );
    }

    /**
     * پاک‌سازی حافظه نهان محصولات.
     *
     * @return void
     */
    public static function flush_cache(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_beryon_products_%' OR option_name LIKE '_transient_timeout_beryon_products_%' OR option_name LIKE '_transient_beryon_cats_%' OR option_name LIKE '_transient_timeout_beryon_cats_%'");

        \delete_transient('beryon_products_total');
    }

    /* ============================================================== *
     * متاباکس مشخصات محصول
     * ============================================================== */

    /**
     * ثبت متاباکس.
     *
     * @return void
     */
    public static function meta_boxes(): void
    {
        $screens = array_filter([self::woo_active() ? 'product' : '', self::CPT]);

        foreach ($screens as $screen) {
            \add_meta_box(
                'beryon_product_specs',
                \__('مشخصات محصول بریون', BERYON_TD),
                [self::class, 'meta_box_html'],
                $screen,
                'normal',
                'high'
            );
        }
    }

    /**
     * خروجی متاباکس.
     *
     * @param \WP_Post $post پست
     * @return void
     */
    public static function meta_box_html(\WP_Post $post): void
    {
        \wp_nonce_field('beryon_product_specs', 'beryon_product_specs_nonce');

        $fields = [
            self::META['meat']        => [\__('درصد گوشت (عدد)', BERYON_TD), 'number', '60'],
            self::META['meat_type']   => [\__('نوع گوشت', BERYON_TD), 'text', \__('گوشت قرمز', BERYON_TD)],
            self::META['group']       => [\__('گروه (sausage|kalbas|jambon|frozen|plant)', BERYON_TD), 'text', ''],
            self::META['unit_weight'] => [\__('وزن هر قطعه', BERYON_TD), 'text', '۵۰ گرم'],
            self::META['packaging']   => [\__('بسته‌بندی', BERYON_TD), 'text', \__('وکیوم یا توری ۱ کیلوگرمی', BERYON_TD)],
            self::META['shelf_life']  => [\__('ماندگاری', BERYON_TD), 'text', \__('۴۵ روز در دمای ۱ تا ۴ درجه سانتی‌گراد', BERYON_TD)],
            self::META['storage']     => [\__('شرایط نگهداری (کوتاه)', BERYON_TD), 'text', \__('دمای ۱ تا ۴ درجه سانتی‌گراد', BERYON_TD)],
            '_beryon_featured'        => [\__('محصول ویژه صفحه اصلی', BERYON_TD), 'checkbox', ''],
        ];

        echo '<div class="beryon-specs" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px">';

        foreach ($fields as $key => [$label, $type, $placeholder]) {
            $value = \get_post_meta($post->ID, $key, true);

            echo '<p style="margin:0"><label for="' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br>';

            if ($type === 'checkbox') {
                echo '<input type="checkbox" id="' . esc_attr($key) . '" name="beryon[' . esc_attr($key) . ']" value="1" ' . checked($value, '1', false) . '>';
            } elseif ($type === 'number') {
                echo '<input type="number" min="0" max="100" class="small-text" id="' . esc_attr($key) . '" name="beryon[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '">';
            } else {
                echo '<input type="text" class="widefat" id="' . esc_attr($key) . '" name="beryon[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '" placeholder="' . esc_attr($placeholder) . '">';
            }

            echo '</p>';
        }

        echo '</div>';

        $keep = (string) \get_post_meta($post->ID, self::META['keep'], true);

        echo '<p><label for="' . esc_attr(self::META['keep']) . '"><strong>' . esc_html__('نگهداری و مصرف (هر خط یک مورد؛ HTML مجاز: b, ul, li, p)', BERYON_TD) . '</strong></label>'
            . '<textarea class="widefat" rows="4" id="' . esc_attr(self::META['keep']) . '" name="beryon[' . esc_attr(self::META['keep']) . ']">' . esc_textarea($keep) . '</textarea></p>';

        $extra = (string) \get_post_meta($post->ID, self::META['desc_extra'], true);

        echo '<p><label for="' . esc_attr(self::META['desc_extra']) . '"><strong>' . esc_html__('توضیح تکمیلی تب «توضیحات محصول» (هر خط یک مورد)', BERYON_TD) . '</strong></label>'
            . '<textarea class="widefat" rows="4" id="' . esc_attr(self::META['desc_extra']) . '" name="beryon[' . esc_attr(self::META['desc_extra']) . ']">' . esc_textarea($extra) . '</textarea></p>';
    }

    /**
     * ذخیره مقادیر متاباکس.
     *
     * @param int      $post_id شناسه پست
     * @param \WP_Post $post    پست
     * @return void
     */
    public static function save_meta(int $post_id, \WP_Post $post): void
    {
        if (! isset($_POST['beryon_product_specs_nonce']) || ! \wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST['beryon_product_specs_nonce'])), 'beryon_product_specs')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! \current_user_can('edit_post', $post_id)) {
            return;
        }

        $values = isset($_POST['beryon']) && is_array($_POST['beryon']) ? \wp_unslash($_POST['beryon']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- در ادامه sanitize می‌شود

        foreach ($values as $key => $value) {
            if (! is_string($key) || ! str_starts_with((string) $key, '_')) {
                continue;
            }

            if (is_array($value)) {
                continue;
            }

            if (in_array($key, [self::META['keep'], self::META['desc_extra']], true)) {
                $clean = \wp_kses((string) $value, ['b' => [], 'strong' => [], 'ul' => [], 'li' => [], 'p' => [], 'br' => []]);
                \update_post_meta($post_id, $key, $clean);

                continue;
            }

            if ($key === self::META['meat']) {
                \update_post_meta($post_id, $key, \sanitize_text_field((string) $value));

                continue;
            }

            \update_post_meta($post_id, $key, \sanitize_text_field((string) $value));
        }

        if (! isset($values['_beryon_featured'])) {
            \delete_post_meta($post_id, '_beryon_featured');
        }
    }

    /**
     * خطوط متن → فهرست HTML (برای تب‌های توضیح/نگهداری).
     *
     * @param string $text متن
     * @param string $tag  نوع فهرست
     * @return string
     */
    public static function lines_to_array(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
    }

    /**
     * تبدیل متن چندخطی به فهرست HTML.
     *
     * @param string $text متن
     * @param string $tag  ul|ol
     * @return string
     */
    public static function lines_to_list(string $text, string $tag = 'ul'): string
    {
        $lines = self::lines_to_array($text);

        if ($lines === []) {
            return '';
        }

        $tag  = $tag === 'ol' ? 'ol' : 'ul';
        $html = '<' . $tag . '>';

        foreach ($lines as $line) {
            $html .= '<li>' . \wp_kses($line, ['b' => [], 'strong' => [], 'a' => ['href' => [], 'title' => []]]) . '</li>';
        }

        return $html . '</' . $tag . '>';
    }
}
