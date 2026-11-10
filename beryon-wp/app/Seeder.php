<?php

/**
 * درج داده‌های نمونه قالب بریون (Seeder)
 * ------------------------------------------------------------------
 * منبع داده: config/catalog.json (محصولات/دسته‌ها/برچسب‌ها) و config/blog.json (مقالات).
 * کاملاً idempotent: اجرای مجدد، محتوای ویرایش‌شده کاربر را از بین نمی‌برد مگر با force=true.
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * کلاس درج داده نمونه.
 */
final class Seeder
{
    /** نگاشت اسلاگ صفحه → قالب Blade */
    private const PAGE_TEMPLATES = [
        'about-us'                   => 'page-about',
        'about-us/introduction-of-ceo' => 'page-ceo',
        'about-us/organizational-chart' => 'page-org-chart',
        'about-us/certificates'      => 'page-certificates',
        'catalog'                    => 'page-catalog',
        'feedback'                   => 'page-feedback',
        'agency'                     => 'page-agency',
        'contact-us'                 => 'page-contact-us',
    ];

    /**
     * تاریخ نادرستی که نسخه‌های ≤ ۱.۰.۳ برای نوشته‌های نمونه ثبت می‌کردند
     * (gmmktime با ماه/روز/سال صفر)؛ فقط برای ترمیم همان نوشته‌ها استفاده می‌شود.
     */
    private const BROKEN_SEED_DATE = '1999-11-30 07:30:00';

    /**
     * اجرای کامل عملیات درج.
     *
     * @param array<string,mixed> $args گزینه‌ها: force, skip_pages, skip_posts, skip_media
     * @return array<string,int>
     */
    public static function run(array $args = []): array
    {
        $force = (bool) ($args['force'] ?? false);

        $result = [
            'categories' => 0,
            'products'   => 0,
            'pages'      => 0,
            'posts'      => 0,
            'media'      => 0,
            'menus'      => 0,
        ];

        // ثبت نوع محتوا پیش از درج (در صورت فراخوانی خارج از init)
        if (! \post_type_exists(Products::CPT)) {
            Products::register();
        }

        if (! empty($args['media'])) {
            $result['media'] = self::media();
        }

        $result['categories'] = self::categories($force);
        $result['products']   = self::products($force);

        if (empty($args['skip_pages'])) {
            $result['pages'] = self::pages($force);
        }

        if (empty($args['skip_posts'])) {
            $result['posts'] = self::posts($force);
        }

        $result['menus'] = self::menus();
        self::reading_settings();

        Products::flush_cache();
        Seo::flush_sitemap();
        Assets::flush_image_cache();

        \flush_rewrite_rules();

        /**
         * پس از اتمام درج داده‌ها.
         *
         * @param array $result شمارش‌ها
         * @param array $args   گزینه‌ها
         */
        \do_action('beryon/seeded', $result, $args);

        return $result;
    }

    /* ------------------------------------------------------------------ *
     * دسته‌ها
     * ------------------------------------------------------------------ */

    /**
     * درج دسته‌های محصولات.
     *
     * @param bool $force بازنویسی
     * @return int
     */
    private static function categories(bool $force = false): int
    {
        $taxonomy = Products::TAX;
        $count    = 0;
        $order    = 0;

        foreach (Products::catalog()['categories'] as $slug => $cat) {
            $order++;
            $existing = \term_exists((string) $slug, $taxonomy);

            if (is_array($existing) && isset($existing['term_id'])) {
                $term_id = (int) $existing['term_id'];
            } else {
                $inserted = \wp_insert_term((string) $cat['name'], $taxonomy, [
                    'slug'        => (string) $slug,
                    'description' => (string) ($cat['desc'] ?? ''),
                ]);

                if (\is_wp_error($inserted)) {
                    continue;
                }

                $term_id = (int) $inserted['term_id'];
            }

            if ($force) {
                \wp_update_term($term_id, $taxonomy, [
                    'name'        => (string) $cat['name'],
                    'description' => (string) ($cat['desc'] ?? ''),
                ]);
            }

            $meta = [
                '_beryon_short'       => (string) ($cat['short'] ?? $cat['name']),
                '_beryon_hero'        => (string) ($cat['hero'] ?? $cat['name']),
                '_beryon_lead'        => (string) ($cat['lead'] ?? ''),
                '_beryon_image'       => (string) ($cat['image'] ?? ''),
                '_beryon_chips'       => (array) ($cat['chips'] ?? []),
                '_beryon_chips_label' => (string) ($cat['chips_label'] ?? ''),
                '_beryon_placeholder' => (string) ($cat['placeholder'] ?? ''),
                '_beryon_sr'          => (string) ($cat['sr'] ?? ''),
                '_beryon_seo_html'    => (string) ($cat['seo'] ?? ''),
                '_beryon_order'       => $order,
            ];

            foreach ($meta as $key => $value) {
                if ($force || \get_term_meta($term_id, $key, true) === '') {
                    \update_term_meta($term_id, $key, $value);
                }
            }

            // تصویر دسته از کتابخانه رسانه (در صورت وجود)
            $attachment = Assets::attachment_by_name((string) ($cat['image'] ?? ''));

            if ($attachment > 0 && ($force || (int) \get_term_meta($term_id, 'thumbnail_id', true) === 0)) {
                \update_term_meta($term_id, 'thumbnail_id', $attachment);
            }

            $count++;
        }

        return $count;
    }

    /* ------------------------------------------------------------------ *
     * محصولات
     * ------------------------------------------------------------------ */

    /**
     * درج محصولات.
     *
     * @param bool $force بازنویسی
     * @return int
     */
    private static function products(bool $force = false): int
    {
        $post_type = Products::post_type();
        $count     = 0;

        foreach (Products::catalog()['products'] as $product) {
            $slug = (string) ($product['slug'] ?? '');

            if ($slug === '') {
                continue;
            }

            $existing = \get_page_by_path($slug, OBJECT, $post_type);

            if ($existing instanceof \WP_Post && ! $force) {
                $post_id = $existing->ID;
            } else {
                $post_id = self::upsert_product($post_type, $slug, $product, $existing instanceof \WP_Post ? $existing->ID : 0);
            }

            if ($post_id <= 0) {
                continue;
            }

            self::product_meta($post_id, $product, $force);
            self::product_terms($post_id, $product, $force);
            self::product_image($post_id, $product, $force);

            if (Products::woo_active() && ! $force) {
                self::woo_defaults($post_id);
            }

            $count++;
        }

        return $count;
    }

    /**
     * درج/به‌روزرسانی یک محصول.
     *
     * @param string               $post_type نوع محتوا
     * @param string               $slug      اسلاگ
     * @param array<string,mixed>  $data      داده
     * @param int                  $post_id   شناسه موجود
     * @return int
     */
    private static function upsert_product(string $post_type, string $slug, array $data, int $post_id = 0): int
    {
        $args = [
            'post_type'    => $post_type,
            'post_title'  => (string) $data['name'],
            'post_name'   => $slug,
            'post_status' => 'publish',
            'post_excerpt' => (string) ($data['short'] ?? ''),
            'menu_order'  => (int) ($data['menu_order'] ?? 0),
            'post_content' => (string) ($data['short'] ?? ''),
        ];

        if ($post_id > 0) {
            $args['ID'] = $post_id;

            return (int) \wp_update_post($args, true);
        }

        return (int) \wp_insert_post($args, true);
    }

    /**
     * متاهای محصول.
     *
     * @param int                  $post_id شناسه
     * @param array<string,mixed>  $data    داده
     * @param bool                 $force   بازنویسی
     * @return void
     */
    private static function product_meta(int $post_id, array $data, bool $force): void
    {
        $meta = [
            Products::META['meat']        => isset($data['meat']) ? (int) $data['meat'] : '',
            Products::META['meat_type']   => (string) ($data['meat_type'] ?? ''),
            Products::META['unit_weight'] => (string) ($data['unit_weight'] ?? ''),
            Products::META['packaging']   => (string) ($data['packaging'] ?? ''),
            Products::META['shelf_life']  => (string) ($data['shelf_life'] ?? ''),
            Products::META['storage']     => (string) ($data['storage'] ?? ''),
            Products::META['group']       => (string) ($data['group'] ?? ''),
            Products::META['keep']        => (string) ($data['keep_html'] ?? ''),
            '_beryon_featured'            => (bool) ($data['featured'] ?? false) ? '1' : '',
        ];

        foreach ($meta as $key => $value) {
            if ($force || \get_post_meta($post_id, $key, true) === '') {
                if ($value === '') {
                    continue;
                }

                \update_post_meta($post_id, $key, $value);
            }
        }
    }

    /**
     * دسته و برچسب‌های محصول.
     *
     * @param int                  $post_id شناسه
     * @param array<string,mixed>  $data    داده
     * @param bool                 $force   بازنویسی
     * @return void
     */
    private static function product_terms(int $post_id, array $data, bool $force): void
    {
        $category = (string) ($data['category'] ?? '');

        if ($category !== '' && \term_exists($category, Products::TAX)) {
            \wp_set_object_terms($post_id, [$category], Products::TAX, false);
        }

        $tags = (array) ($data['tags'] ?? []);

        if ($tags !== [] && \taxonomy_exists('product_tag')) {
            // هر برچسب با «اسلاگ لاتین» (برای فیلتر زنده: data-tags) و «نام فارسی»
            // (برای نشان روی کارت و «نوع محصول») ساخته می‌شود.
            $labels   = (array) (Products::catalog()['tags'] ?? []);
            $term_ids = [];

            foreach ($tags as $tag_slug) {
                $tag_slug = (string) $tag_slug;

                if ($tag_slug === '') {
                    continue;
                }

                $label = isset($labels[$tag_slug]) && is_string($labels[$tag_slug]) && $labels[$tag_slug] !== ''
                    ? $labels[$tag_slug]
                    : $tag_slug;
                $term  = \get_term_by('slug', $tag_slug, 'product_tag');

                if ($term instanceof \WP_Term) {
                    // برچسبی که نسخه‌های ≤ ۱.۰.۳ با نام لاتین (همان اسلاگ) ساخته‌اند ترمیم می‌شود؛
                    // نامی که مدیر سایت تغییر داده دست‌نخورده می‌ماند (مگر با --force).
                    if ($label !== $tag_slug && (string) $term->name !== $label && ($force || (string) $term->name === $tag_slug)) {
                        \wp_update_term((int) $term->term_id, 'product_tag', ['name' => $label, 'slug' => $tag_slug]);
                    }

                    $term_ids[] = (int) $term->term_id;

                    continue;
                }

                $inserted = \wp_insert_term($label, 'product_tag', ['slug' => $tag_slug]);

                if (\is_wp_error($inserted)) {
                    $existing = (int) $inserted->get_error_data('term_exists');

                    if ($existing > 0) {
                        $term_ids[] = $existing;
                    }

                    continue;
                }

                $term_ids[] = (int) $inserted['term_id'];
            }

            if ($term_ids !== []) {
                \wp_set_object_terms($post_id, array_values(array_unique($term_ids)), 'product_tag', false);
            }
        }
    }

    /**
     * تصویر شاخص محصول.
     *
     * @param int                  $post_id شناسه
     * @param array<string,mixed>  $data    داده
     * @param bool                 $force   بازنویسی
     * @return void
     */
    private static function product_image(int $post_id, array $data, bool $force): void
    {
        if (! $force && \has_post_thumbnail($post_id)) {
            return;
        }

        // تصویر اختصاصی محصول در کاتالوگ وجود ندارد؛ تصویر دسته استفاده می‌شود.
        $category = Products::catalog()['categories'][(string) ($data['category'] ?? '')] ?? [];
        $name     = (string) ($category['image'] ?? Settings::opt('products.default_image', 'cat-sausage.webp'));

        $attachment = Assets::attachment_by_name($name);

        if ($attachment > 0) {
            \set_post_thumbnail($post_id, $attachment);

            return;
        }

        // در نبود رسانه، نام تصویر به‌صورت متا ذخیره می‌شود تا Assets آن را از پوشه قالب بخواند.
        \update_post_meta($post_id, '_beryon_image', $name);
    }

    /**
     * تنظیمات پیش‌فرض ووکامرس برای حالت کاتالوگ.
     *
     * @param int $post_id شناسه
     * @return void
     */
    private static function woo_defaults(int $post_id): void
    {
        \update_post_meta($post_id, '_visibility', 'visible');
        \update_post_meta($post_id, '_stock_status', 'instock');
        \update_post_meta($post_id, '_manage_stock', 'no');
        \update_post_meta($post_id, '_backorders', 'no');
        \update_post_meta($post_id, '_sold_individually', 'no');
        \update_post_meta($post_id, '_virtual', 'yes');
        \update_post_meta($post_id, '_downloadable', 'no');
        \update_post_meta($post_id, '_regular_price', '');
        \update_post_meta($post_id, '_price', '');
    }

    /* ------------------------------------------------------------------ *
     * صفحات
     * ------------------------------------------------------------------ */

    /**
     * درج صفحات قالب.
     *
     * @param bool $force بازنویسی
     * @return int
     */
    private static function pages(bool $force = false): int
    {
        $pages = [
            ['slug' => 'about-us', 'title' => \__('درباره بریون | بریان گوشت آمل', BERYON_TD), 'parent' => ''],
            ['slug' => 'introduction-of-ceo', 'title' => \__('معرفی مدیرعامل بریان گوشت آمل', BERYON_TD), 'parent' => 'about-us'],
            ['slug' => 'organizational-chart', 'title' => \__('چارت سازمانی بریان گوشت آمل', BERYON_TD), 'parent' => 'about-us'],
            ['slug' => 'certificates', 'title' => \__('گواهینامه‌ها و افتخارات بریون', BERYON_TD), 'parent' => 'about-us'],
            ['slug' => 'catalog', 'title' => \__('کاتالوگ محصولات بریون', BERYON_TD), 'parent' => ''],
            ['slug' => 'feedback', 'title' => \__('نظرات و شکایات', BERYON_TD), 'parent' => ''],
            ['slug' => 'agency', 'title' => \__('اعطای نمایندگی فروش بریون', BERYON_TD), 'parent' => ''],
            ['slug' => 'contact-us', 'title' => \__('تماس با بریون', BERYON_TD), 'parent' => ''],
            ['slug' => 'blog', 'title' => \__('مجله بریان گوشت', BERYON_TD), 'parent' => ''],
        ];

        $ids   = [];
        $count = 0;

        foreach ($pages as $page) {
            $parent_id = 0;

            if ($page['parent'] !== '' && isset($ids[$page['parent']])) {
                $parent_id = (int) $ids[$page['parent']];
            }

            // مسیر کامل (والد/فرزند): get_page_by_path صفحه فرزند را فقط با مسیر کامل
            // پیدا می‌کند؛ با اسلاگ تنها، هر بار اجرا یک رونوشت «-2» می‌ساخت.
            $slug     = $page['parent'] !== '' ? $page['parent'] . '/' . $page['slug'] : $page['slug'];
            $existing = \get_page_by_path($slug, OBJECT, 'page');

            if ($existing instanceof \WP_Post) {
                $page_id = $existing->ID;
            } else {
                $page_id = (int) \wp_insert_post([
                    'post_type'   => 'page',
                    'post_title'  => $page['title'],
                    'post_name'   => $page['slug'],
                    'post_status' => 'publish',
                    'post_parent' => $parent_id,
                    'post_content' => '',
                ], true);
            }

            if ($page_id <= 0) {
                continue;
            }

            $ids[$page['slug']] = $page_id;

            $template = self::PAGE_TEMPLATES[$slug] ?? '';

            if ($template !== '' && is_readable(BERYON_PATH . '/resources/views/' . $template . '.blade.php')) {
                $stored = (string) \get_post_meta($page_id, '_wp_page_template', true);

                if ($force || $stored === '' || $stored === 'default') {
                    \update_post_meta($page_id, '_wp_page_template', 'views/' . $template . '.blade.php');
                }
            }

            $count++;
        }

        return $count;
    }

    /**
     * تنظیمات خواندن (صفحه اصلی ثابت).
     *
     * @return void
     */
    private static function reading_settings(): void
    {
        if ((int) \get_option('page_on_front') > 0) {
            return;
        }

        $home = \get_page_by_path('home');

        if (! $home instanceof \WP_Post) {
            $created = (int) \wp_insert_post([
                'post_type'    => 'page',
                'post_title'   => \__('صفحه اصلی', BERYON_TD),
                'post_name'    => 'home',
                'post_status'  => 'publish',
                'post_content' => '',
            ], true);

            if ($created > 0) {
                \update_post_meta($created, '_beryon_front_page', '1');
                \update_option('show_on_front', 'page');
                \update_option('page_on_front', $created);
            }

            return;
        }

        \update_option('show_on_front', 'page');
        \update_option('page_on_front', $home->ID);
    }

    /* ------------------------------------------------------------------ *
     * مقالات
     * ------------------------------------------------------------------ */

    /**
     * درج مقالات وبلاگ.
     *
     * @param bool $force بازنویسی
     * @return int
     */
    private static function posts(bool $force = false): int
    {
        $file = BERYON_PATH . '/config/blog.json';
        $data = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (! is_array($data) || empty($data['posts'])) {
            return 0;
        }

        $blog_page = \get_page_by_path('blog');
        $count     = 0;
        $offset    = 0;

        foreach ((array) $data['posts'] as $post) {
            $slug     = (string) ($post['slug'] ?? '');
            $existing = $slug !== '' ? \get_page_by_path($slug, OBJECT, 'post') : null;

            // تاریخ آینده نوشته را «زمان‌بندی‌شده» می‌کند (۴۰۴ تا آن روز)؛ در آن حالت
            // تاریخ خالی می‌ماند تا وردپرس زمان فعلی را بگذارد.
            $timestamp = self::jalali_to_timestamp((string) ($post['jalali'] ?? ''));
            $date      = $timestamp > 0 && $timestamp <= time() ? \gmdate('Y-m-d H:i:s', $timestamp) : '';

            if ($existing instanceof \WP_Post && ! $force) {
                // ترمیم نوشته‌هایی که نسخه‌های ≤ ۱.۰.۳ با تاریخ نادرست درج کرده‌اند.
                if ($date !== '' && (string) $existing->post_date_gmt === self::BROKEN_SEED_DATE) {
                    \wp_update_post([
                        'ID'            => $existing->ID,
                        'post_date_gmt' => $date,
                        'post_date'     => \get_date_from_gmt($date),
                    ]);
                }

                $count++;

                continue;
            }

            $args = [
                'post_type'    => 'post',
                'post_title'   => (string) $post['title'],
                'post_name'    => $slug,
                'post_status'  => 'publish',
                'post_content' => (string) ($post['body'] ?? ''),
                'post_excerpt' => (string) ($post['excerpt'] ?? ''),
                'menu_order'   => $offset,
            ];

            if ($date !== '') {
                $args['post_date_gmt'] = $date;
                $args['post_date']     = \get_date_from_gmt($date);
            }

            if ($existing instanceof \WP_Post) {
                $args['ID']  = $existing->ID;
                $post_id     = (int) \wp_update_post($args, true);
            } else {
                $post_id = (int) \wp_insert_post($args, true);
            }

            if ($post_id <= 0) {
                continue;
            }

            if ((string) ($post['category'] ?? '') !== '') {
                \wp_set_object_terms($post_id, [(string) $post['category']], 'category', false);
            }

            if ((array) ($post['tags'] ?? []) !== []) {
                \wp_set_object_terms($post_id, (array) $post['tags'], 'post_tag', false);
            }

            \update_post_meta($post_id, '_beryon_image', (string) ($post['image'] ?? ''));
            \update_post_meta($post_id, '_beryon_image_alt', (string) ($post['alt'] ?? ''));
            \update_post_meta($post_id, '_beryon_jalali', (string) ($post['jalali'] ?? ''));

            $attachment = Assets::attachment_by_name((string) ($post['image'] ?? ''));

            if ($attachment > 0 && ($force || ! \has_post_thumbnail($post_id))) {
                \set_post_thumbnail($post_id, $attachment);
            }

            $count++;
            $offset++;
        }

        if ($blog_page instanceof \WP_Post && (int) \get_option('page_for_posts') !== $blog_page->ID) {
            \update_option('show_on_front', 'page');
            \update_option('page_for_posts', $blog_page->ID);
        }

        return $count;
    }

    /**
     * تبدیل تاریخ شمسی (YYYY-MM-DD) به timestamp.
     *
     * @param string $jalali تاریخ شمسی
     * @return int
     */
    private static function jalali_to_timestamp(string $jalali): int
    {
        $parts = array_map('intval', explode('-', $jalali));

        if (count($parts) !== 3 || $parts[0] < 1300 || $parts[1] < 1 || $parts[1] > 12 || $parts[2] < 1 || $parts[2] > 31) {
            return 0;
        }

        // Jalali::toGregorian آرایه انجمنی ['gy','gm','gd'] برمی‌گرداند (نه عددی).
        $gregorian = Jalali::toGregorian($parts[0], $parts[1], $parts[2]);

        if (! is_array($gregorian) || ! isset($gregorian['gy'], $gregorian['gm'], $gregorian['gd'])) {
            return 0;
        }

        $timestamp = \gmmktime(7, 30, 0, (int) $gregorian['gm'], (int) $gregorian['gd'], (int) $gregorian['gy']);

        return is_int($timestamp) ? $timestamp : 0;
    }

    /* ------------------------------------------------------------------ *
     * رسانه و منوها
     * ------------------------------------------------------------------ */

    /**
     * بارگذاری تصاویر پوشه قالب به کتابخانه رسانه.
     *
     * @return int
     */
    private static function media(): int
    {
        $dir = BERYON_PATH . '/public/assets/images';

        if (! is_dir($dir)) {
            return 0;
        }

        // GLOB_BRACE روی همه سکوها تعریف نشده است (مثلاً Alpine/musl)؛ هر پسوند جدا پیمایش می‌شود.
        $files = [];

        foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $extension) {
            foreach ((array) glob($dir . '/*.' . $extension) as $file) {
                if (is_string($file) && is_file($file)) {
                    $files[] = $file;
                }
            }
        }

        sort($files);

        if ($files === []) {
            return 0;
        }

        if (! \function_exists('media_handle_sideload') || ! \function_exists('wp_tempnam')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $count = 0;

        foreach ($files as $file) {
            $name = basename($file);

            if (Assets::attachment_by_name($name) > 0) {
                continue;
            }

            // media_handle_sideload فایل tmp_name را به پوشه آپلود «منتقل» می‌کند
            // (copy + unlink)؛ پس همیشه روی یک رونوشت موقت کار می‌کنیم تا فایل
            // اصلی قالب در public/assets/images حذف نشود.
            $temporary = (string) \wp_tempnam($name);

            if ($temporary === '' || ! @copy($file, $temporary)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
                if ($temporary !== '' && is_file($temporary)) {
                    @unlink($temporary); // phpcs:ignore WordPress.PHP.NoSilencedErrors
                }

                continue;
            }

            $id = \media_handle_sideload(['name' => $name, 'tmp_name' => $temporary], 0);

            if (\is_wp_error($id) || (int) $id <= 0) {
                if (is_file($temporary)) {
                    @unlink($temporary); // phpcs:ignore WordPress.PHP.NoSilencedErrors
                }

                continue;
            }

            // _beryon_asset کلیدی است که Assets::attachment_by_name با آن جست‌وجو می‌کند.
            \update_post_meta((int) $id, '_beryon_asset', $name);
            \update_post_meta((int) $id, '_beryon_source_file', $name);

            // نتیجه «یافت نشد» همین نام پیش‌تر کش شده است؛ نگاشت تازه جایگزین می‌شود تا
            // در همین اجرا تصویر شاخص دسته‌ها، محصولات و نوشته‌ها به پیوست وصل شود.
            Assets::remember_attachment($name, (int) $id);
            $count++;
        }

        return $count;
    }

    /**
     * ساخت منوهای قالب.
     *
     * @return int
     */
    private static function menus(): int
    {
        $count    = 0;
        $created  = [];
        $defines  = [
            'primary'       => \__('منوی اصلی بریون', BERYON_TD),
            'footer_quick'  => \__('پانوشت — دسترسی سریع', BERYON_TD),
            'footer_cats'   => \__('پانوشت — دسته‌های محصولات', BERYON_TD),
        ];

        foreach ($defines as $location => $name) {
            $existing = \wp_get_nav_menu_object($name);
            $menu_id  = $existing instanceof \WP_Term ? (int) $existing->term_id : (int) \wp_create_nav_menu($name);

            if ($menu_id <= 0) {
                continue;
            }

            $created[$location] = $menu_id;
            $count++;

            // منویی که آیتم دارد دوباره پر نمی‌شود (تابع wp_get_nav_menu_item_count
            // در وردپرس وجود ندارد و فراخوانی‌اش درج را با خطای مرگبار متوقف می‌کرد).
            $items = \wp_get_nav_menu_items($menu_id, ['post_status' => 'any']);

            if (is_array($items) && $items !== []) {
                continue;
            }

            self::fill_menu($menu_id, $location);
        }

        $locations = \get_theme_mod('nav_menu_locations');
        $locations = is_array($locations) ? $locations : [];

        foreach ($created as $location => $menu_id) {
            if (empty($locations[$location])) {
                $locations[$location] = $menu_id;
            }
        }

        \set_theme_mod('nav_menu_locations', $locations);

        return $count;
    }

    /**
     * پرکردن آیتم‌های یک منو.
     *
     * @param int    $menu_id  شناسه منو
     * @param string $location مکان
     * @return void
     */
    private static function fill_menu(int $menu_id, string $location): void
    {
        // هر سه منو از همان فهرستی پر می‌شوند که منوی پیش‌فرض قالب را می‌سازد تا
        // سایت پس از درج داده دقیقاً همان پیوندهای پیش از آن را داشته باشد (پیش‌تر
        // «دسته‌های پانوشت» جداگانه پر می‌شد و پیوند «همه محصولات» را جا می‌انداخت).
        $items = Nav::items($location);

        // شماره ترتیبِ پیوسته (والد، سپس فرزندانش) تا ترتیب تختِ آیتم‌ها در پایگاه‌داده
        // همان ترتیب درختی منو باشد.
        $position = 0;

        foreach ($items as $item) {
            $object = self::menu_object((string) $item['url']);

            $id = (int) \wp_update_nav_menu_item($menu_id, 0, [
                'menu-item-title'     => (string) $item['title'],
                'menu-item-url'       => (string) $item['url'],
                'menu-item-object'    => $object['object'],
                'menu-item-object-id' => $object['id'],
                'menu-item-type'      => $object['type'],
                'menu-item-status'    => 'publish',
                'menu-item-position'  => ++$position,
            ]);

            if ($id <= 0) {
                continue;
            }

            $parent = $id;

            foreach ((array) ($item['children'] ?? []) as $child) {
                $child_object = self::menu_object((string) $child['url']);

                \wp_update_nav_menu_item($menu_id, 0, [
                    'menu-item-title'     => (string) $child['title'],
                    'menu-item-url'       => (string) $child['url'],
                    'menu-item-object'    => $child_object['object'],
                    'menu-item-object-id' => $child_object['id'],
                    'menu-item-type'      => $child_object['type'],
                    'menu-item-status'    => 'publish',
                    'menu-item-parent-id' => $parent,
                    'menu-item-position'  => ++$position,
                ]);
            }
        }
    }

    /**
     * تشخیص نوع آیتم منو از روی آدرس.
     *
     * @param string $url آدرس
     * @return array{type:string,object:string,id:int}
     */
    private static function menu_object(string $url): array
    {
        $parsed = \wp_parse_url($url);
        $path   = isset($parsed['path']) ? trim((string) $parsed['path'], '/') : '';

        // نشانی‌های دارای query (پیوند یکتای ساده: ‎?page_id=…‎ یا ‎?post_type=…‎) از
        // روی مسیر قابل تشخیص نیستند؛ همان نشانی به‌صورت پیوند دلخواه ثبت می‌شود تا
        // با «صفحه نخست» اشتباه گرفته نشود.
        if (! empty($parsed['query'])) {
            return ['type' => 'custom', 'object' => '', 'id' => 0];
        }

        if ($path === '') {
            $front = (int) \get_option('page_on_front');

            return $front > 0
                ? ['type' => 'post_type', 'object' => 'page', 'id' => $front]
                : ['type' => 'custom', 'object' => '', 'id' => 0];
        }

        // صفحه‌ها
        $segments = explode('/', $path);
        $page     = \get_page_by_path($path, OBJECT, 'page');

        if ($page instanceof \WP_Post) {
            return ['type' => 'post_type', 'object' => 'page', 'id' => $page->ID];
        }

        // دسته محصولات
        $term = \get_term_by('slug', end($segments), Products::TAX);

        if ($term instanceof \WP_Term) {
            return ['type' => 'taxonomy', 'object' => Products::TAX, 'id' => $term->term_id];
        }

        // آرشیو محصولات
        $archive = \wp_parse_url((string) \get_post_type_archive_link(Products::post_type()));
        $archive_path = isset($archive['path']) ? trim((string) $archive['path'], '/') : '';

        if ($archive_path !== '' && $archive_path === $path) {
            return ['type' => 'post_type_archive', 'object' => Products::post_type(), 'id' => 0];
        }

        // نوشته‌ها (وبلاگ)
        $post = \get_page_by_path($path, OBJECT, 'post');

        if ($post instanceof \WP_Post) {
            return ['type' => 'post_type', 'object' => 'post', 'id' => $post->ID];
        }

        return ['type' => 'custom', 'object' => '', 'id' => 0];
    }
}
