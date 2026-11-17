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

    /** گزینه نشانگر «دسته‌های پیش‌فرض یک بار ساخته شده‌اند» */
    public const TERMS_FLAG = 'beryon_terms_ready';

    /** گزینه نشانگر «دسته‌های پیش‌فرضِ تکراری یک بار پاک‌سازی شده‌اند» (مقدار = نسخه پاک‌سازی) */
    public const CLEANUP_FLAG = 'beryon_terms_cleanup';

    /** گزینه گزارش پاک‌سازی (برای اعلان یک‌باره در پیشخوان) */
    public const CLEANUP_LOG = 'beryon_terms_cleanup_log';

    /** نام‌های رایج دسته پیش‌فرض («دسته‌بندی نشده») که جزو دسته‌های اصلی به حساب نمی‌آید */
    private const UNCATEGORIZED = ['uncategorized', 'دسته بندی نشده', 'بدون دسته', 'بدون دسته بندی', 'غیر مصنف'];

    /** @var array<string,array<int,array<string,mixed>>> حافظه درون‌درخواستی فهرست دسته‌ها */
    private static $memo = [];

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
        \add_action('admin_notices', [self::class, 'cleanup_notice']);

        // پاک‌سازی حافظه نهان هنگام تغییر محصولات و نوشته‌ها (save_post برای همه انواع محتوا اجرا می‌شود)
        foreach (['save_post', 'deleted_post', 'wp_insert_term', 'edited_term', 'delete_term'] as $hook) {
            \add_action($hook, [self::class, 'flush_cache']);
        }

        // تصویر شاخص در ویرایشگر کلاسیک و ویرایش سریع بدون ذخیره نوشته عوض می‌شود (فقط متا).
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            \add_action($hook, [self::class, 'flush_on_thumbnail'], 10, 3);
        }
    }

    /**
     * پاک‌سازی حافظه نهان با تغییر تصویر شاخص (متای _thumbnail_id).
     *
     * @param mixed $meta_id   شناسه (یا شناسه‌های) ردیف متا
     * @param mixed $object_id شناسه نوشته
     * @param mixed $meta_key  کلید متا
     * @return void
     */
    public static function flush_on_thumbnail($meta_id, $object_id, $meta_key): void
    {
        if ($meta_key === '_thumbnail_id') {
            self::flush_cache();
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

        // ثبت اصطلاحات پیش‌فرض گروه‌ها — فقط یک بار. پیش‌تر در هر درخواست اجرا
        // می‌شد (۵ پرس‌وجوی اضافه) و دسته‌ای را که مدیر حذف کرده بود دوباره می‌ساخت.
        // درون‌ریز داده نمونه (Seeder) هر زمان لازم باشد دسته‌ها را از نو می‌سازد.
        if (\taxonomy_exists(self::TAX) && ! \get_option(self::TERMS_FLAG)) {
            self::ensure_terms();
            \update_option(self::TERMS_FLAG, 1, true);
        }

        // نسخه‌های ≤ ۱.۰.۴ روی سایتی که دسته‌های خودش را داشت هم دسته‌های پیش‌فرض را
        // می‌ساختند (دسته‌های خالیِ هم‌نام). یک بار پاک می‌شوند.
        if (\taxonomy_exists(self::TAX) && \get_option(self::CLEANUP_FLAG) !== '1') {
            /**
             * فیلتر پاک‌سازی یک‌باره دسته‌های پیش‌فرضِ تکراری (false = چیزی حذف نمی‌شود).
             *
             * @param bool $enabled
             */
            $removed = \apply_filters('beryon/products/cleanup_seed_terms', true) ? self::cleanup_seed_terms() : [];
            \update_option(self::CLEANUP_FLAG, '1', true);

            if ($removed !== []) {
                \update_option(self::CLEANUP_LOG, $removed, false);
            }
        }
    }

    /**
     * ساخت ۵ گروه محصولی پیش‌فرض — فقط روی سایتی که هنوز دسته محصولی ندارد.
     *
     * سایتی که دسته‌های خودش را دارد (مثلاً فروشگاه ووکامرسِ موجود، در هر چند زبان)
     * دست نمی‌خورد؛ پیش‌تر کنار دسته‌های واقعی، دسته‌های خالیِ هم‌نام ساخته می‌شد.
     *
     * @return void
     */
    public static function ensure_terms(): void
    {
        if (self::has_own_terms()) {
            return;
        }

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
     * اعلان یک‌باره پیشخوان: دسته‌های پیش‌فرضِ تکراری که پاک‌سازی حذف کرده است.
     *
     * @return void
     */
    public static function cleanup_notice(): void
    {
        $removed = \get_option(self::CLEANUP_LOG);

        if (! is_array($removed) || $removed === [] || ! \current_user_can('manage_options')) {
            return;
        }

        \delete_option(self::CLEANUP_LOG);

        $names = [];

        foreach ($removed as $row) {
            $names[] = (string) ($row['name'] ?? '') . ' (' . (string) ($row['slug'] ?? '') . ')';
        }

        printf(
            '<div class="notice notice-info is-dismissible"><p><strong>%s</strong> %s</p><p>%s</p></div>',
            \esc_html__('قالب بریون:', BERYON_TD),
            \esc_html(sprintf(
                /* translators: %s: تعداد دسته‌ها */
                \__('%s دسته خالیِ تکراری که نسخه پیشین قالب کنار دسته‌های واقعی سایت ساخته بود حذف شد:', BERYON_TD),
                \beryon_fa((string) count($names))
            )),
            \esc_html(implode('، ', $names))
        );
    }

    /**
     * آیا سایت دسته محصولِ خودش را دارد؟ (هر دسته‌ای جز «دسته‌بندی نشده» و جز
     * دسته‌های پیش‌فرض خودِ قالب)
     *
     * @return bool
     */
    public static function has_own_terms(): bool
    {
        $catalog = self::catalog()['categories'];

        foreach (self::raw_terms() as $term) {
            if (! isset($catalog[$term->slug]) && ! self::is_uncategorized($term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * همه دسته‌های محصول، در همه زبان‌ها.
     *
     * @return array<int,\WP_Term>
     */
    private static function raw_terms(): array
    {
        if (! \taxonomy_exists(self::TAX)) {
            return [];
        }

        // lang = '' فیلتر زبان Polylang را کنار می‌گذارد (در نبود افزونه بی‌اثر است).
        $terms = \get_terms(['taxonomy' => self::TAX, 'hide_empty' => false, 'lang' => '']);

        return is_array($terms) ? array_values(array_filter($terms, static fn ($term): bool => $term instanceof \WP_Term)) : [];
    }

    /**
     * نشانی بایگانی همه دسته‌های محصول در همه زبان‌ها (برای نقشه سایت، که به زبان
     * جاری وابسته نیست).
     *
     * @return array<int,string>
     */
    public static function category_links(): array
    {
        $links = [];

        foreach (self::raw_terms() as $term) {
            $link = \get_term_link($term);

            if (is_string($link) && $link !== '') {
                $links[] = $link;
            }
        }

        return array_values(array_unique($links));
    }

    /**
     * دسته‌ای که درون‌ریز داده نمونه باید برای یک گروه کاتالوگ به کار ببرد.
     *
     * اگر دسته‌ای با همان نامک هست همان؛ وگرنه روی سایتی که دسته‌های خودش را دارد،
     * دسته هم‌گروهِ زبان پیش‌فرض (مثلاً «کالباس» واقعی سایت برای گروه kalbas) تا
     * کنار آن دسته تکراری ساخته نشود. صفر یعنی باید دسته تازه ساخته شود.
     *
     * @param string $slug نامک گروه در کاتالوگ قالب
     * @return int
     */
    public static function seed_term_id(string $slug): int
    {
        $existing = \term_exists($slug, self::TAX);

        if (is_array($existing) && isset($existing['term_id'])) {
            return (int) $existing['term_id'];
        }

        if ($slug === '' || ! self::has_own_terms()) {
            return 0;
        }

        $terms = self::raw_terms();

        Lang::prime_terms(array_map(static fn ($term): int => (int) $term->term_id, $terms));

        $multilingual = Lang::is_multilingual();
        $default      = Lang::default_language();
        $best         = null;

        foreach ($terms as $term) {
            if (self::is_uncategorized($term) || self::group_key($term) !== $slug) {
                continue;
            }

            $lang = $multilingual ? Lang::term_language((int) $term->term_id) : '';

            if ($lang !== '' && $lang !== $default) {
                continue;
            }

            if ($best === null || (int) $term->count > (int) $best->count) {
                $best = $term;
            }
        }

        return $best instanceof \WP_Term ? (int) $best->term_id : 0;
    }

    /**
     * پاک‌سازی دسته‌های پیش‌فرضی که قالب (نسخه ≤ ۱.۰.۴) کنار دسته‌های واقعی سایت ساخته بود.
     *
     * فقط دسته‌ای حذف می‌شود که همه این شرط‌ها را دارد: نامکش یکی از نامک‌های
     * پیش‌فرض قالب است؛ هیچ محصولی ندارد؛ زیردسته ندارد؛ زبانی برایش ثبت نشده؛
     * دسته پیش‌فرض فروشگاه نیست؛ و دسته دیگری با همان نام هست که محصول یا والد دارد.
     *
     * @return array<int,array<string,mixed>> دسته‌های حذف‌شده [term_id, name, slug]
     */
    public static function cleanup_seed_terms(): array
    {
        $terms   = self::raw_terms();
        $catalog = self::catalog()['categories'];
        $default = (int) \get_option('default_' . self::TAX);
        $removed = [];

        if ($terms === [] || $catalog === []) {
            return $removed;
        }

        $parents = [];
        $names   = [];

        foreach ($terms as $term) {
            $parents[(int) $term->parent]            = true;
            $names[self::normalize_name($term->name)][] = $term;
        }

        Lang::prime_terms(array_map(static fn ($term): int => (int) $term->term_id, $terms));

        foreach ($terms as $term) {
            $id = (int) $term->term_id;

            if (! isset($catalog[$term->slug]) || (int) $term->count > 0 || (int) $term->parent > 0 || isset($parents[$id]) || $id === $default) {
                continue;
            }

            if (Lang::term_language($id) !== '') {
                continue;
            }

            $objects = \get_objects_in_term($id, self::TAX);

            if (! is_array($objects) || $objects !== []) {
                continue;
            }

            $twin = false;

            foreach ($names[self::normalize_name($term->name)] ?? [] as $other) {
                if ((int) $other->term_id !== $id && ((int) $other->count > 0 || (int) $other->parent > 0)) {
                    $twin = true;

                    break;
                }
            }

            if (! $twin) {
                continue;
            }

            $deleted = \wp_delete_term($id, self::TAX);

            if ($deleted === true) {
                $removed[] = ['term_id' => $id, 'name' => (string) $term->name, 'slug' => (string) $term->slug];
            }
        }

        if ($removed !== []) {
            self::flush_cache();
        }

        return $removed;
    }

    /**
     * کاتالوگ مرجع (داده اولیه قالب که از config/catalog.json خوانده می‌شود).
     *
     * @return array{categories:array<string,array<string,mixed>>,products:array<int,array<string,mixed>>,tags:array<string,string>,archive:array<string,mixed>}
     */
    public static function catalog(): array
    {
        static $catalog = null;

        if ($catalog !== null) {
            return $catalog;
        }

        $file = BERYON_PATH . '/config/catalog.json';
        $data = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;

        // tags (اسلاگ → برچسب فارسی) و archive هم در فایل هستند و مصرف‌کننده دارند
        // (درون‌ریز محصولات و نمای بایگانی)؛ پیش‌تر دور ریخته می‌شدند.
        $catalog = is_array($data) && isset($data['products'])
            ? [
                'categories' => (array) ($data['categories'] ?? []),
                'products'   => (array) $data['products'],
                'tags'       => (array) ($data['tags'] ?? []),
                'archive'    => (array) ($data['archive'] ?? []),
            ]
            : ['categories' => [], 'products' => [], 'tags' => [], 'archive' => []];

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

        // نسخه قالب در کلید: ساختار ردیف‌های کش‌شده بین نسخه‌ها عوض می‌شود و کش
        // نسخه پیشین نباید پس از به‌روزرسانی خوانده شود.
        $cache_key = 'beryon_products_' . substr(md5(BERYON_VERSION . '|' . Lang::cache_key() . '|' . wp_json_encode($args)), 0, 20);
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
        $query_args = (array) \apply_filters('beryon/products/query_args', Lang::query_args($query_args), $args);

        $query = new \WP_Query($query_args);
        $ids   = (array) $query->posts;

        // «محصولات ویژه»: ۱) متای قالب (_beryon_featured)؛ ۲) محصول «ویژه» ووکامرس؛
        // ۳) اگر هیچ محصولی ویژه نشده، تازه‌ترین محصولات (بخش صفحه اصلی خالی نمی‌ماند).
        if ($args['featured'] && $ids === []) {
            $fallback = $query_args;

            unset($fallback['meta_query']);

            if (self::woo_active() && \taxonomy_exists('product_visibility')) {
                $woo                = $fallback;
                $woo['tax_query']   = (array) ($woo['tax_query'] ?? []); // phpcs:ignore WordPress.DB.SlowDBQuery
                $woo['tax_query'][] = ['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => ['featured']];
                $ids                = (array) (new \WP_Query($woo))->posts;
            }

            /**
             * آیا در نبود محصول ویژه، تازه‌ترین محصولات نمایش داده شود؟
             *
             * @param bool  $enabled پیش‌فرض: بله
             * @param array $args    آرگومان‌های Products::query()
             */
            if ($ids === [] && \apply_filters('beryon/products/featured_fallback', true, $args)) {
                $fallback['orderby'] = ['date' => 'DESC', 'ID' => 'DESC'];
                $ids                 = (array) (new \WP_Query($fallback))->posts;
            }
        }

        $items = array_map([self::class, 'normalize'], $ids);
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

        $term      = self::primary_term($post);
        $term_slug = $term ? (string) $term->slug : '';
        $category  = $term ? self::category($term) : [];
        $group     = (string) ($category['group'] ?? '');
        $cat_short = (string) ($category['short'] ?? ($term ? $term->name : ''));

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
        $type_label = $tag_names !== [] ? (string) $tag_names[0] : (string) ($category['reference']['short'] ?? '');

        if ($meat === '' || $meat === null) {
            $meta_group = (string) \get_post_meta($post->ID, self::META['group'], true);

            if ($meta_group === 'frozen' || $group === 'frozen') {
                array_unshift($badges, \__('آماده طبخ', BERYON_TD));
            } elseif ($meta_group === 'plant' || $group === 'plant') {
                array_unshift($badges, \__('گیاهی', BERYON_TD));
            }
        }

        return [
            'id'          => $post->ID,
            'title'       => get_the_title($post),
            'link'        => (string) get_permalink($post),
            'image'       => $image,
            // data-cat کارت محصول: چیپ‌های آرشیو با کلید گروه کاتالوگ فیلتر می‌کنند
            // (sausage، kalbas، …)؛ دسته ناشناخته با نامک خودش می‌آید.
            'category'    => $group !== '' ? $group : $term_slug,
            'cat_slug'    => $term_slug,
            'group'       => $group,
            'cat_name'    => $term ? (string) $term->name : '',
            'cat_short'   => $cat_short,
            'cat_link'    => $term ? (string) \get_term_link($term) : '',
            'type_label'  => $type_label,
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

        $term = self::primary_term($post);

        if ($term instanceof \WP_Term) {
            $thumb_id = (int) \get_term_meta($term->term_id, 'thumbnail_id', true);

            if ($thumb_id > 0) {
                $url = \wp_get_attachment_image_url($thumb_id, 'beryon-card');

                if ($url) {
                    return (string) $url;
                }
            }

            $group = self::group_key($term);
            $image = $group !== '' ? (string) (self::catalog()['categories'][$group]['image'] ?? '') : '';

            if ($image !== '') {
                return Assets::image_url($image);
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
     * دسته‌های محصولِ زبان جاری، با تعداد و تصویر.
     *
     * روی سایت چندزبانه فقط دسته‌های زبان جاری برمی‌گردد (دسته بی‌زبان جزو زبان
     * پیش‌فرض است). دسته خالیِ پیش‌فرض قالب که هم‌نامِ یک دسته واقعی است حذف می‌شود.
     * برای کارت‌های صفحه اصلی، منوها و پابرگ از main_categories() استفاده کنید.
     *
     * @param string $exclude اسلاگ دسته‌ای که باید حذف شود
     * @return array<int,array<string,mixed>>
     */
    public static function categories(string $exclude = ''): array
    {
        $cache_key = 'beryon_cats_' . md5(BERYON_VERSION . '|' . Lang::cache_key() . '|' . $exclude);

        if (isset(self::$memo[$cache_key])) {
            return self::$memo[$cache_key];
        }

        $cached = \get_transient($cache_key);

        if (is_array($cached)) {
            self::$memo[$cache_key] = $cached;

            return $cached;
        }

        $args = [
            'taxonomy'   => self::TAX,
            'hide_empty' => false,
            'orderby'    => 'term_order',
            'order'      => 'ASC',
        ];

        // Polylang فهرست را به زبان جاری محدود می‌کند؛ زبان صریح داده می‌شود تا در
        // پیشخوان، REST و wp-cli (که زبان جاری ندارند) هم نتیجه یکی باشد.
        if (Lang::mode() === Lang::MODE_POLYLANG) {
            $args['lang'] = Lang::current();
        }

        $terms = \taxonomy_exists(self::TAX) ? \get_terms($args) : [];
        $terms = is_array($terms) ? array_values(array_filter($terms, static fn ($term): bool => $term instanceof \WP_Term)) : [];

        Lang::prime_terms(array_map(static fn ($term): int => (int) $term->term_id, $terms));

        $multilingual = Lang::is_multilingual();
        $current      = Lang::current();
        $is_default   = Lang::is_default();
        $defaults     = self::default_term_ids($terms);
        $items        = [];
        $has_children = [];

        foreach ($terms as $term) {
            $has_children[(int) $term->parent] = true;
        }

        foreach ($terms as $term) {
            if ($term->slug === $exclude) {
                continue;
            }

            if ($multilingual) {
                $lang = Lang::term_language((int) $term->term_id);

                // دسته زبان دیگر کنار می‌رود؛ دسته بی‌زبان فقط در زبان پیش‌فرض می‌آید.
                if (($lang !== '' && $lang !== $current) || ($lang === '' && ! $is_default)) {
                    continue;
                }
            }

            $item             = self::category_item($term);
            $item['default']  = in_array((int) $term->term_id, $defaults, true);
            $item['children'] = isset($has_children[(int) $term->term_id]);
            $items[]          = $item;
        }

        $items = self::drop_seed_twins($items);
        $items = self::sort_by_group($items);

        self::$memo[$cache_key] = $items;

        \set_transient($cache_key, $items, (int) Settings::opt('performance.cache_ttl', 3600));

        return $items;
    }

    /**
     * دسته‌های اصلیِ زبان جاری — کارت‌های صفحه اصلی، زیرمنوی «محصولات» و ستون
     * «گروه‌های محصولات» پابرگ.
     *
     * قاعده: دسته‌های سطح نخستِ زبان جاری، بدون دسته پیش‌فرض («دسته‌بندی نشده»).
     * اگر فقط یک دسته سطح نخست بماند و زیردسته داشته باشد (مثل «محصولات» که خودش
     * فقط ظرفِ دسته‌های واقعی است)، زیردسته‌هایش دسته‌های اصلی‌اند.
     * تنظیم «products.main_parent» (شناسه یا نامک یک دسته) این انتخاب خودکار را
     * کنار می‌گذارد: زیردسته‌های همان دسته (یا ترجمه‌اش در زبان جاری) نمایش داده می‌شود.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function main_categories(): array
    {
        $override  = trim((string) Settings::opt('products.main_parent', ''));
        $cache_key = 'beryon_cats_main_' . md5(BERYON_VERSION . '|' . Lang::cache_key() . '|' . $override);

        if (isset(self::$memo[$cache_key])) {
            return self::$memo[$cache_key];
        }

        $cached = \get_transient($cache_key);

        if (is_array($cached)) {
            self::$memo[$cache_key] = $cached;

            return $cached;
        }

        $all      = self::categories();
        $ids      = [];
        $children = [];

        foreach ($all as $item) {
            $ids[(int) $item['term_id']] = true;
        }

        foreach ($all as $item) {
            if (empty($item['default'])) {
                $children[(int) $item['parent']][] = $item;
            }
        }

        $main = [];

        if ($override !== '') {
            $parent = self::resolve_main_parent($override, $ids);

            if ($parent > 0 && ! empty($children[$parent])) {
                $main = $children[$parent];
            }
        }

        if ($main === []) {
            // سطح نخست: بی‌والد، یا والدش در فهرست زبان جاری نیست.
            foreach ($all as $item) {
                if (empty($item['default']) && ((int) $item['parent'] === 0 || ! isset($ids[(int) $item['parent']]))) {
                    $main[] = $item;
                }
            }

            // چند ریشه هم‌سطح (سایت بدون داده زبان): ریشه‌ای که زیردسته‌هایش همان گروه‌های
            // کاتالوگ قالب‌اند (نام یا نامک یکسان) ظرف دسته‌های اصلی است.
            if (count($main) > 1) {
                $best  = null;
                $score = 1;
                $tie   = false;

                foreach ($main as $item) {
                    $matched = 0;

                    foreach ($children[(int) $item['term_id']] ?? [] as $child) {
                        $matched += ! empty($child['exact']) ? 1 : 0;
                    }

                    if ($matched > $score) {
                        $best  = $item;
                        $score = $matched;
                        $tie   = false;
                    } elseif ($matched === $score && $best !== null) {
                        $tie = true;
                    }
                }

                $own_groups = count(array_filter($main, static fn (array $item): bool => ! empty($item['exact'])));

                if ($best !== null && ! $tie && $own_groups < $score) {
                    $main = [$best];
                }
            }

            // تنها ریشه‌ای که فقط ظرف است → زیردسته‌هایش (حداکثر سه سطح).
            for ($depth = 0; $depth < 3; $depth++) {
                if (count($main) !== 1 || empty($children[(int) $main[0]['term_id']])) {
                    break;
                }

                $main = $children[(int) $main[0]['term_id']];
            }
        }

        $main = self::sort_by_group(array_values($main));

        /**
         * فیلتر دسته‌های اصلی زبان جاری.
         *
         * @param array $main دسته‌های اصلی
         * @param array $all  همه دسته‌های زبان جاری
         */
        $main = array_values((array) \apply_filters('beryon/products/main_categories', $main, $all));

        self::$memo[$cache_key] = $main;

        \set_transient($cache_key, $main, (int) Settings::opt('performance.cache_ttl', 3600));

        return $main;
    }

    /**
     * تراشه‌های فیلتر صفحه «همه محصولات» (تنظیم products.archive_chips).
     *
     * برچسب‌های این تنظیم فارسی‌اند؛ در زبان‌های دیگرِ سایت چندزبانه، برچسب تراشه هر
     * گروه با نام دسته اصلیِ همان گروه در زبان جاری جایگزین می‌شود (مقدار فیلتر —
     * کلید گروه — تغییری نمی‌کند).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function archive_chips(): array
    {
        $chips = (array) Settings::opt('products.archive_chips', []);

        if (! Lang::is_multilingual() || Lang::is_default()) {
            return $chips;
        }

        $labels = [];

        foreach (self::main_categories() as $item) {
            $group = (string) ($item['group'] ?? '');

            if ($group !== '' && ! isset($labels[$group])) {
                $labels[$group] = (string) ($item['short'] ?? $item['name']);
            }
        }

        foreach ($chips as $index => $chip) {
            $value = is_array($chip) ? (string) ($chip['value'] ?? '') : '';

            if ($value !== '' && isset($labels[$value])) {
                $chips[$index]['label'] = $labels[$value];
            }
        }

        return $chips;
    }

    /**
     * داده یک دسته (همان ساختار ردیف‌های categories()).
     *
     * @param \WP_Term|int $term دسته
     * @return array<string,mixed> آرایه خالی اگر دسته پیدا نشود
     */
    public static function category($term): array
    {
        $term = $term instanceof \WP_Term ? $term : \get_term((int) $term, self::TAX);

        if (! $term instanceof \WP_Term) {
            return [];
        }

        foreach (self::categories() as $item) {
            if ((int) $item['term_id'] === (int) $term->term_id) {
                return $item;
            }
        }

        $item             = self::category_item($term);
        $item['default']  = in_array((int) $term->term_id, self::default_term_ids([$term]), true);
        $item['children'] = false;

        return $item;
    }

    /**
     * گروه کاتالوگ یک دسته (sausage | kalbas | jambon | frozen | plant) یا ''.
     *
     * دسته‌های واقعی سایت نامک‌های خودشان را دارند (مثلاً «کالباس»، «cold-cuts»،
     * «مرتديلا»)؛ تصویر و ترتیب کارت و فیلتر آرشیو از روی گروه تعیین می‌شود:
     * ۱) نامک یا نام برابر کاتالوگ؛ ۲) واژه‌های کلیدی سه‌زبانه (aliases در
     * config/catalog.json)؛ ۳) ترجمه‌ای که خودش با نام/نامک شناخته شود.
     *
     * @param \WP_Term $term دسته
     * @return string
     */
    public static function group_key(\WP_Term $term): string
    {
        static $memo = [];

        $id = (int) $term->term_id;

        if (isset($memo[$id])) {
            return $memo[$id];
        }

        $key = self::exact_group($term);

        if ($key === '') {
            $key = self::alias_group($term);
        }

        if ($key === '') {
            foreach (Lang::term_translations($id) as $translation) {
                if ((int) $translation === $id) {
                    continue;
                }

                $other = \get_term((int) $translation, self::TAX);

                // ترجمه فقط وقتی معتبر است که هر دو هم‌سطح باشند (هر دو ریشه یا هر دو
                // زیردسته)؛ پیوند اشتباهِ «دسته ↔ ریشه زبان دیگر» نادیده گرفته می‌شود.
                if ($other instanceof \WP_Term && ((int) $other->parent === 0) === ((int) $term->parent === 0)) {
                    $key = self::exact_group($other) ?: self::alias_group($other);

                    if ($key !== '') {
                        break;
                    }
                }
            }
        }

        /**
         * فیلتر گروه کاتالوگ یک دسته.
         *
         * @param string   $key  کلید گروه یا ''
         * @param \WP_Term $term دسته
         */
        $memo[$id] = (string) \apply_filters('beryon/products/group', $key, $term);

        return $memo[$id];
    }

    /**
     * گروه کاتالوگ از روی نامک یا نام دقیق.
     *
     * @param \WP_Term $term دسته
     * @return string
     */
    private static function exact_group(\WP_Term $term): string
    {
        $catalog = self::catalog()['categories'];

        if (isset($catalog[$term->slug])) {
            return (string) $term->slug;
        }

        $name = self::normalize_name((string) $term->name);

        foreach ($catalog as $key => $reference) {
            foreach (['name', 'short', 'crumb'] as $field) {
                if ($name !== '' && $name === self::normalize_name((string) ($reference[$field] ?? ''))) {
                    return (string) $key;
                }
            }
        }

        return '';
    }

    /**
     * گروه کاتالوگ از روی واژه‌های کلیدی (فارسی، انگلیسی، عربی).
     *
     * @param \WP_Term $term دسته
     * @return string
     */
    private static function alias_group(\WP_Term $term): string
    {
        $haystack = ' ' . self::normalize_name((string) $term->name) . ' ' . self::normalize_name(str_replace('-', ' ', rawurldecode((string) $term->slug))) . ' ';
        $found    = '';
        $weight   = -1;

        foreach (self::catalog()['categories'] as $key => $reference) {
            foreach ((array) ($reference['aliases'] ?? []) as $alias) {
                $alias = self::normalize_name((string) $alias);

                if ($alias === '') {
                    continue;
                }

                // واژه لاتین فقط به‌صورت واژه کامل (ham نباید در hamburger پیدا شود).
                $hit = preg_match('/^[a-z0-9 ]+$/', $alias)
                    ? (bool) preg_match('/[^a-z0-9]' . preg_quote($alias, '/') . 's?[^a-z0-9]/', $haystack)
                    : mb_strpos($haystack, $alias) !== false;

                // «qualifier» (گیاهی، انجمادی) بر نوع فرآورده مقدم است: «سوسیس گیاهی» گیاهی است.
                $priority = (int) ($reference['alias_priority'] ?? 0);

                if ($hit && $priority > $weight) {
                    $found  = (string) $key;
                    $weight = $priority;
                }
            }
        }

        return $found;
    }

    /**
     * یکسان‌سازی نام برای مقایسه (حروف عربی/فارسی، نیم‌فاصله، فاصله‌ها، حروف کوچک).
     *
     * @param string $name نام
     * @return string
     */
    public static function normalize_name(string $name): string
    {
        $name = \wp_strip_all_tags($name);
        $name = strtr($name, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', "\u{200C}" => ' ', "\u{200F}" => '', "\u{200E}" => '', 'ـ' => '', '،' => ' ', ',' => ' ']);
        $name = preg_replace('/[\x{064B}-\x{0652}]/u', '', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim(function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name));
    }

    /**
     * آیا دسته، دسته پیش‌فرضِ «دسته‌بندی نشده» است؟ (از روی نام یا نامک)
     *
     * @param \WP_Term $term دسته
     * @return bool
     */
    private static function is_uncategorized(\WP_Term $term): bool
    {
        if (in_array(self::normalize_name((string) $term->name), self::UNCATEGORIZED, true)) {
            return true;
        }

        return strpos((string) $term->slug, 'uncategorized') === 0;
    }

    /**
     * شناسه دسته‌های پیش‌فرض: گزینه default_product_cat، دسته‌های «دسته‌بندی نشده»
     * و ترجمه‌های هر دو (ترجمه فارسیِ «دسته‌بندی نشده» می‌تواند نام دیگری داشته باشد).
     *
     * @param array<int,\WP_Term> $terms دسته‌های در حال بررسی
     * @return array<int,int>
     */
    private static function default_term_ids(array $terms): array
    {
        $ids     = [];
        $default = (int) \get_option('default_' . self::TAX);

        if ($default > 0) {
            $ids[] = $default;
        }

        foreach ($terms as $term) {
            if (self::is_uncategorized($term)) {
                $ids[] = (int) $term->term_id;
            }
        }

        foreach ($ids as $id) {
            foreach (Lang::term_translations($id) as $translation) {
                $ids[] = (int) $translation;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * ردیف داده یک دسته.
     *
     * @param \WP_Term $term دسته
     * @return array<string,mixed>
     */
    private static function category_item(\WP_Term $term): array
    {
        $id      = (int) $term->term_id;
        $group   = self::group_key($term);
        $lang    = Lang::term_language($id);
        $catalog = self::catalog()['categories'];

        // متن‌های کاتالوگ قالب فارسی‌اند؛ برای دسته زبان دیگر فقط تصویر گروه به‌کار می‌رود.
        $native    = ! Lang::is_multilingual() || $lang === '' || $lang === Lang::default_language();
        $reference = $group !== '' && $native ? (array) ($catalog[$group] ?? []) : [];
        $chips     = \get_term_meta($id, '_beryon_chips', true);

        return [
            'term_id'     => $id,
            'slug'        => (string) $term->slug,
            'name'        => (string) $term->name,
            'parent'      => (int) $term->parent,
            'lang'        => $lang,
            'group'       => $group,
            'exact'       => self::exact_group($term) !== '',
            'link'        => (string) \get_term_link($term),
            'count'       => (int) $term->count,
            'image'       => self::category_image($term, $group),
            'desc'        => (string) ($term->description ?: ($reference['desc'] ?? '')),
            'short'       => (string) (\get_term_meta($id, '_beryon_short', true) ?: ($reference['short'] ?? $term->name)),
            'hero'        => (string) (\get_term_meta($id, '_beryon_hero', true) ?: ($reference['hero'] ?? $term->name)),
            'lead'        => (string) (\get_term_meta($id, '_beryon_lead', true) ?: ($reference['lead'] ?? '')),
            'chips'       => is_array($chips) && $chips !== [] ? $chips : (array) ($reference['chips'] ?? []),
            'chips_label' => (string) (\get_term_meta($id, '_beryon_chips_label', true) ?: ($reference['chips_label'] ?? '')),
            'placeholder' => (string) (\get_term_meta($id, '_beryon_placeholder', true) ?: ($reference['placeholder'] ?? '')),
            'sr'          => (string) (\get_term_meta($id, '_beryon_sr', true) ?: ($reference['sr'] ?? $term->name)),
            'seo_html'    => (string) (\get_term_meta($id, '_beryon_seo_html', true) ?: ($reference['seo'] ?? '')),
            'reference'   => $reference,
        ];
    }

    /**
     * تصویر کارت دسته: ۱) تصویر تعیین‌شده برای قالب (متای _beryon_image)؛
     * ۲) تصویر دسته در ووکامرس (اگر مدیر انتخاب کرده باشد، نه تصویر نمونه قالب)؛
     * ۳) تصویر گروه کاتالوگ؛ ۴) لوگو.
     *
     * @param \WP_Term $term  دسته
     * @param string   $group کلید گروه کاتالوگ
     * @return string
     */
    private static function category_image(\WP_Term $term, string $group): string
    {
        $id   = (int) $term->term_id;
        $own  = (string) \get_term_meta($id, '_beryon_image', true);

        if ($own !== '') {
            return Assets::image_url($own);
        }

        $thumbnail = (int) \get_term_meta($id, 'thumbnail_id', true);

        if ($thumbnail > 0 && (string) \get_post_meta($thumbnail, '_beryon_asset', true) === '') {
            $url = \wp_get_attachment_image_url($thumbnail, 'beryon-cat') ?: \wp_get_attachment_image_url($thumbnail, 'large');

            if ($url) {
                return (string) $url;
            }
        }

        $image = $group !== '' ? (string) (self::catalog()['categories'][$group]['image'] ?? '') : '';

        return Assets::image_url($image !== '' ? $image : 'logo.png');
    }

    /**
     * حذف دسته خالیِ پیش‌فرض قالب که هم‌نام یک دسته واقعی است (تا پیش از پاک‌سازی
     * یک‌باره، یا اگر پاک‌سازی ممکن نبوده باشد).
     *
     * @param array<int,array<string,mixed>> $items دسته‌ها
     * @return array<int,array<string,mixed>>
     */
    private static function drop_seed_twins(array $items): array
    {
        $catalog = self::catalog()['categories'];
        $names   = [];

        foreach ($items as $index => $item) {
            $names[self::normalize_name((string) $item['name'])][] = $index;
        }

        foreach ($items as $index => $item) {
            if (! isset($catalog[$item['slug']]) || (int) $item['count'] > 0 || ! empty($item['children']) || (int) $item['parent'] > 0) {
                continue;
            }

            foreach ($names[self::normalize_name((string) $item['name'])] as $other) {
                if ($other !== $index && isset($items[$other]) && ((int) $items[$other]['count'] > 0 || (int) $items[$other]['parent'] > 0)) {
                    unset($items[$index]);

                    break;
                }
            }
        }

        return array_values($items);
    }

    /**
     * ترتیب: گروه‌های کاتالوگ به ترتیب قالب (سوسیس، کالباس، ژامبون، انجمادی، گیاهی)،
     * سپس بقیه به همان ترتیبی که وردپرس/ووکامرس داده است.
     *
     * @param array<int,array<string,mixed>> $items دسته‌ها
     * @return array<int,array<string,mixed>>
     */
    private static function sort_by_group(array $items): array
    {
        $order = array_flip(array_keys(self::catalog()['categories']));

        foreach ($items as $index => $item) {
            $items[$index]['_i'] = $index;
        }

        \usort(
            $items,
            /** @param array<string,mixed> $a */
            static function (array $a, array $b) use ($order): int {
                $ai = $order[$a['group']] ?? 99;
                $bi = $order[$b['group']] ?? 99;

                return $ai === $bi ? $a['_i'] <=> $b['_i'] : $ai <=> $bi;
            }
        );

        foreach ($items as $index => $item) {
            unset($items[$index]['_i']);
        }

        return $items;
    }

    /**
     * دسته والدِ دسته‌های اصلی از تنظیم products.main_parent (شناسه یا نامک)،
     * در زبان جاری.
     *
     * @param string         $setting مقدار تنظیم
     * @param array<int,bool> $ids    شناسه دسته‌های زبان جاری
     * @return int شناسه دسته یا ۰
     */
    private static function resolve_main_parent(string $setting, array $ids): int
    {
        $slug = \sanitize_title($setting);
        $id   = 0;

        foreach (self::raw_terms() as $term) {
            if ((ctype_digit($setting) && (int) $term->term_id === (int) $setting) || (string) $term->slug === $slug || (string) $term->slug === $setting) {
                $id = (int) $term->term_id;

                break;
            }
        }

        if ($id < 1) {
            return 0;
        }

        if (isset($ids[$id])) {
            return $id;
        }

        $translation = (int) (Lang::term_translations($id)[Lang::current()] ?? 0);

        return isset($ids[$translation]) ? $translation : 0;
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
     * تعداد کل محصولاتِ زبان جاری.
     *
     * @return int
     */
    public static function total_count(): int
    {
        $cache_key = 'beryon_products_total' . (Lang::cache_key() !== '' ? '_' . Lang::cache_key() : '');
        $count     = (int) \get_transient($cache_key);

        if ($count > 0) {
            return $count;
        }

        if (Lang::is_multilingual()) {
            // wp_count_posts زبان نمی‌شناسد؛ پرس‌وجو را Polylang (یا در نبودش، Lang) به زبان جاری محدود می‌کند.
            $query = new \WP_Query(
                Lang::query_args(
                    [
                        'post_type'              => self::post_type(),
                        'post_status'            => 'publish',
                        'posts_per_page'         => 1,
                        'fields'                 => 'ids',
                        'update_post_meta_cache' => false,
                        'update_post_term_cache' => false,
                    ]
                )
            );

            $count = (int) $query->found_posts;
        } else {
            $counts = \wp_count_posts(self::post_type());
            $count  = isset($counts->publish) ? (int) $counts->publish : 0;
        }

        if ($count > 0) {
            \set_transient($cache_key, $count, (int) Settings::opt('performance.cache_ttl', 3600));
        }

        return $count;
    }

    /**
     * محصولات مرتبط (هم‌دسته).
     *
     * دقیقاً $limit کارت برمی‌گرداند. پیش‌تر «$limit + 1» درخواست می‌شد، در حالی که محصول
     * جاری با exclude از پرس‌وجو کنار رفته است؛ در نتیجه با تنظیم پیش‌فرض ۴، پنج کارت چاپ
     * می‌شد (یک کارت تنها در ردیف دوم؛ طرح مرجع یک ردیف چهارتایی است) و با مقدار ۰ که در
     * پنل مجاز است، به‌جای پنهان‌شدن بخش، یک کارت دیده می‌شد.
     *
     * @param \WP_Post|int $post  محصول جاری
     * @param int          $limit تعداد (۰ = بخش «محصولات مرتبط» نمایش داده نشود)
     * @return array<int,array<string,mixed>>
     */
    public static function related($post, int $limit = 4): array
    {
        $post = \get_post($post);

        if (! $post instanceof \WP_Post || $limit < 1) {
            return [];
        }

        $term = self::primary_term($post);
        $slug = $term ? $term->slug : '';

        return self::query(
            [
                'category' => $slug,
                'limit'    => $limit,
                'exclude'  => [$post->ID],
                'orderby'  => 'rand',
            ]
        );
    }

    /**
     * پاک‌سازی حافظه نهان محصولات و «آخرین نوشته‌ها».
     *
     * @return void
     */
    public static function flush_cache(): void
    {
        global $wpdb;

        self::$memo = [];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_beryon_products_%' OR option_name LIKE '_transient_timeout_beryon_products_%' OR option_name LIKE '_transient_beryon_cats_%' OR option_name LIKE '_transient_timeout_beryon_cats_%'");

        // کش «آخرین نوشته‌ها» (beryon_latest_posts) هم با همین هوک‌ها (ذخیره/حذف نوشته، تغییر
        // تصویر شاخص) پاک می‌شود؛ پیش‌تر هیچ‌جا پاک نمی‌شد و نوشته یا تصویر شاخص تازه تا یک ساعت
        // در صفحه اصلی دیده نمی‌شد.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_beryon_posts_%' OR option_name LIKE '_transient_timeout_beryon_posts_%'");

        // تعداد کل محصولات برای هر زبان جدا کش می‌شود (beryon_products_total_<lang>)؛ با الگوی beryon_products_% بالا پاک شده است.
        \delete_transient('beryon_products_total');
    }

    /**
     * دسته اصلی یک محصول.
     *
     * محصول فروشگاه معمولاً چند دسته دارد (دسته واقعی + ریشه «محصولات» + گاهی
     * «دسته‌بندی نشده»)؛ get_the_terms به ترتیب نام برمی‌گرداند و اولی ممکن است
     * ریشه باشد. ترتیب انتخاب: دسته‌ای که گروه کاتالوگ دارد، سپس عمیق‌ترین دسته،
     * سپس هر دسته‌ای جز پیش‌فرض.
     *
     * @param \WP_Post|int $post محصول
     * @return \WP_Term|null
     */
    public static function primary_term($post): ?\WP_Term
    {
        $terms = \get_the_terms($post, self::TAX);

        if (! is_array($terms) || $terms === []) {
            return null;
        }

        $terms = array_values(array_filter($terms, static fn ($term): bool => $term instanceof \WP_Term));

        if (count($terms) < 2) {
            return $terms[0] ?? null;
        }

        $defaults = self::default_term_ids($terms);
        $best     = null;
        $rank     = -1;

        foreach ($terms as $term) {
            $score = (self::group_key($term) !== '' ? 100 : 0)
                + ((int) $term->parent > 0 ? 10 + count(\get_ancestors((int) $term->term_id, self::TAX, 'taxonomy')) : 0)
                + (in_array((int) $term->term_id, $defaults, true) ? 0 : 5);

            if ($score > $rank) {
                $best = $term;
                $rank = $score;
            }
        }

        return $best;
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
