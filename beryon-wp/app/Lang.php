<?php

/**
 * لایه زبان — قالب بریون
 *
 * سایت بریان گوشت آمل سه‌زبانه است (فارسی، انگلیسی، عربی) و زبان هر نوشته و هر
 * دسته را افزونه Polylang نگه می‌دارد. این کلاس به بقیه قالب می‌گوید «زبان جاری
 * چیست» و «این دسته/نوشته به کدام زبان است» تا فهرست‌ها (دسته‌های محصول، محصولات،
 * نوشته‌ها) و حافظه نهانِ آن‌ها میان زبان‌ها قاطی نشود.
 *
 * سه حالت:
 *   polylang  افزونه Polylang (یا Polylang Pro) فعال است → از API خودِ افزونه.
 *   passive   افزونه فعال نیست ولی داده‌اش در پایگاه داده هست (مثلاً نسخه
 *             آزمایشیِ ساخته‌شده از روی سایت اصلی). زبان‌ها، زبان هر دسته/نوشته و
 *             گروه‌های ترجمه مستقیم از همان جدول‌های وردپرس خوانده می‌شود
 *             (تاکسونومی‌های language / term_language / term_translations).
 *             زبان جاری = زبان پیش‌فرض، مگر این‌که صفحه خودش به زبان دیگری باشد
 *             (نوشته، محصول یا بایگانی دسته آن زبان) یا ‎?lang=en‎ در نشانی بیاید.
 *   none      سایت تک‌زبانه؛ هیچ فیلتری اعمال نمی‌شود.
 *
 * افزونه‌های چندزبانه دیگر می‌توانند با فیلترهای beryon/lang/* وصل شوند.
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * لایه زبان.
 */
final class Lang
{
    public const MODE_POLYLANG = 'polylang';
    public const MODE_PASSIVE  = 'passive';
    public const MODE_NONE     = 'none';

    /** متغیر پرس‌وجو: «نوشته‌های زبان‌های دیگر کنار گذاشته شوند» (فقط حالت passive) */
    public const QUERY_VAR = 'beryon_lang';

    /** @var string|null */
    private static $mode = null;

    /** @var array<string,array<string,mixed>>|null */
    private static $languages = null;

    /** @var string|null زبان جاری پس از مشخص‌شدن پرس‌وجوی اصلی */
    private static $current = null;

    /** @var array<int,string> شناسه دسته → کد زبان ('' = نامشخص) */
    private static $term_lang = [];

    /** @var array<int,array<string,int>> شناسه دسته → [کد زبان → شناسه ترجمه] */
    private static $term_trans = [];

    /** @var array<int,string> شناسه نوشته → کد زبان */
    private static $post_lang = [];

    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        // فقط در حالت passive کار می‌کنند (در حالت polylang خودِ افزونه پرس‌وجوها را فیلتر می‌کند).
        \add_action('pre_get_posts', [self::class, 'filter_main_query'], 20);
        \add_filter('posts_where', [self::class, 'filter_posts_where'], 20, 2);
    }

    /**
     * حالت کار (polylang | passive | none).
     *
     * @return string
     */
    public static function mode(): string
    {
        if (self::$mode !== null) {
            return self::$mode;
        }

        $mode = self::MODE_NONE;

        if (\function_exists('pll_current_language') && \function_exists('pll_languages_list')) {
            $list = \pll_languages_list(['fields' => 'slug']);
            $mode = is_array($list) && $list !== [] ? self::MODE_POLYLANG : self::MODE_NONE;
        } elseif (is_array(\get_option('polylang')) && self::read_languages() !== []) {
            // گزینه «polylang» خودکار بارگذاری می‌شود؛ روی سایتی که هرگز Polylang نداشته
            // پرس‌وجوی اضافه‌ای اجرا نمی‌شود.
            $mode = self::MODE_PASSIVE;
        }

        /**
         * فیلتر حالت لایه زبان.
         *
         * @param string $mode polylang | passive | none
         */
        $mode = (string) \apply_filters('beryon/lang/mode', $mode);

        // پیش از بارگذاری کامل افزونه‌ها نتیجه قطعی نیست.
        if (\did_action('plugins_loaded')) {
            self::$mode = $mode;
        }

        return $mode;
    }

    /**
     * آیا سایت بیش از یک زبان دارد؟
     *
     * @return bool
     */
    public static function is_multilingual(): bool
    {
        return self::mode() !== self::MODE_NONE && count(self::languages()) > 1;
    }

    /**
     * زبان‌های سایت.
     *
     * @return array<string,array<string,mixed>> کد زبان → [slug, name, locale, rtl, tt_id]
     */
    public static function languages(): array
    {
        if (self::$languages !== null) {
            return self::$languages;
        }

        $languages = [];
        $mode      = self::mode();

        if ($mode === self::MODE_POLYLANG) {
            // API عمومی Polylang (در همه نسخه‌ها پایدار است): هر فراخوانی یک ستون را به همان ترتیب می‌دهد.
            $slugs   = array_values((array) \pll_languages_list(['fields' => 'slug']));
            $names   = array_values((array) \pll_languages_list(['fields' => 'name']));
            $locales = array_values((array) \pll_languages_list(['fields' => 'locale']));
            $rtl     = array_values((array) \pll_languages_list(['fields' => 'is_rtl']));

            foreach ($slugs as $index => $slug) {
                $slug = (string) $slug;

                if ($slug === '') {
                    continue;
                }

                $languages[$slug] = [
                    'slug'   => $slug,
                    'name'   => (string) ($names[$index] ?? $slug),
                    'locale' => (string) ($locales[$index] ?? ''),
                    'rtl'    => ! empty($rtl[$index]),
                    'tt_id'  => 0,
                ];
            }
        } elseif ($mode === self::MODE_PASSIVE) {
            $languages = self::read_languages();
        }

        /**
         * فیلتر فهرست زبان‌ها.
         *
         * @param array  $languages کد زبان → داده
         * @param string $mode      حالت
         */
        $languages = (array) \apply_filters('beryon/lang/languages', $languages, $mode);

        if (self::$mode !== null) {
            self::$languages = $languages;
        }

        return $languages;
    }

    /**
     * زبان پیش‌فرض سایت.
     *
     * @return string کد زبان یا '' روی سایت تک‌زبانه
     */
    public static function default_language(): string
    {
        $mode    = self::mode();
        $default = '';

        if ($mode === self::MODE_POLYLANG && \function_exists('pll_default_language')) {
            $default = (string) \pll_default_language('slug');
        } elseif ($mode === self::MODE_PASSIVE) {
            $options   = (array) \get_option('polylang');
            $default   = (string) ($options['default_lang'] ?? '');
            $languages = self::languages();

            if (! isset($languages[$default])) {
                $default = (string) (array_key_first($languages) ?? '');
            }
        }

        return (string) \apply_filters('beryon/lang/default', $default, $mode);
    }

    /**
     * زبان جاری.
     *
     * @return string کد زبان یا '' روی سایت تک‌زبانه
     */
    public static function current(): string
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $mode    = self::mode();
        $current = '';

        if ($mode === self::MODE_POLYLANG) {
            $current = (string) \pll_current_language('slug');

            // پیشخوان با فیلتر «همه زبان‌ها»، REST و wp-cli زبان جاری ندارند.
            if ($current === '') {
                $current = self::default_language();
            }
        } elseif ($mode === self::MODE_PASSIVE) {
            $current   = self::default_language();
            $languages = self::languages();

            // phpcs:ignore WordPress.Security.NonceVerification
            $requested = isset($_GET['lang']) && is_string($_GET['lang']) ? \sanitize_key(\wp_unslash($_GET['lang'])) : '';

            if ($requested !== '' && isset($languages[$requested])) {
                $current = $requested;
            } elseif (\did_action('parse_query') && ! \is_admin()) {
                $from_page = self::language_of_query($GLOBALS['wp_query'] ?? null);

                if ($from_page !== '') {
                    $current = $from_page;
                }
            }
        }

        /**
         * فیلتر زبان جاری.
         *
         * @param string $current کد زبان
         * @param string $mode    حالت
         */
        $current = (string) \apply_filters('beryon/lang/current', $current, $mode);

        // تا پیش از مشخص‌شدن پرس‌وجوی اصلی، نتیجه ممکن است عوض شود.
        if (self::$mode !== null && (\did_action('wp') || \is_admin())) {
            self::$current = $current;
        }

        return $current;
    }

    /**
     * کد زبان‌های دیگر (جز زبان داده‌شده یا زبان جاری).
     *
     * @param string $lang کد زبان (پیش‌فرض: زبان جاری)
     * @return array<int,string>
     */
    public static function others(string $lang = ''): array
    {
        $lang = $lang !== '' ? $lang : self::current();

        return array_values(array_filter(array_keys(self::languages()), static fn ($slug): bool => (string) $slug !== $lang));
    }

    /**
     * آیا زبان جاری همان زبان پیش‌فرض است؟ (سایت تک‌زبانه: همیشه بله)
     *
     * @return bool
     */
    public static function is_default(): bool
    {
        return ! self::is_multilingual() || self::current() === self::default_language();
    }

    /**
     * پسوند کلید حافظه نهان؛ فهرست‌های کش‌شده هر زبان جدا می‌ماند.
     *
     * @return string
     */
    public static function cache_key(): string
    {
        return self::is_multilingual() ? self::current() : '';
    }

    /**
     * مقدار ویژگی lang تگ html برای زبان جاری (fa، en، ar، …).
     *
     * روی سایت تک‌زبانه همان «fa» همیشگی قالب است (قالب فارسی است، حتی اگر زبان
     * پیشخوان وردپرس چیز دیگری باشد).
     *
     * @return string
     */
    public static function html_lang(): string
    {
        $current = self::is_multilingual() ? self::current() : '';

        return $current !== '' && preg_match('/^[a-z]{2,3}(-[a-zA-Z0-9]+)*$/', $current) ? $current : 'fa';
    }

    /**
     * آیا زبان جاری راست‌به‌چپ است؟ (سایت تک‌زبانه: همیشه بله)
     *
     * @return bool
     */
    public static function is_rtl(): bool
    {
        if (! self::is_multilingual()) {
            return true;
        }

        $languages = self::languages();
        $current   = self::current();
        $rtl       = isset($languages[$current]) ? (bool) $languages[$current]['rtl'] : true;

        /**
         * فیلتر جهت صفحه در زبان جاری (true = راست‌به‌چپ).
         *
         * @param bool   $rtl     جهت زبان جاری
         * @param string $current نامک زبان جاری
         */
        return (bool) \apply_filters('beryon/lang/is_rtl', $rtl, $current);
    }

    /**
     * خلاصه وضعیت چندزبانه برای پیشخوان (حالت تشخیص‌داده‌شده و زبان‌ها).
     *
     * @return string
     */
    public static function summary(): string
    {
        if (! self::is_multilingual()) {
            return \__('تک‌زبانه (افزونه چندزبانه یا داده زبان پیدا نشد)', BERYON_TD);
        }

        $default = self::default_language();
        $parts   = [];

        foreach (array_keys(self::languages()) as $slug) {
            $parts[] = (string) $slug . ((string) $slug === $default ? ' (' . \__('پیش‌فرض', BERYON_TD) . ')' : '');
        }

        $label = self::mode() === self::MODE_POLYLANG
            ? \__('Polylang فعال', BERYON_TD)
            : \__('Polylang خاموش؛ زبان‌ها از داده آن در پایگاه داده خوانده می‌شود', BERYON_TD);

        return $label . ' — ' . implode('، ', $parts);
    }

    /* ============================================================== *
     * زبان دسته‌ها و نوشته‌ها
     * ============================================================== */

    /**
     * زبان یک دسته (هر تاکسونومی).
     *
     * @param int $term_id شناسه دسته
     * @return string کد زبان یا '' (زبانی ثبت نشده)
     */
    public static function term_language(int $term_id): string
    {
        if ($term_id < 1) {
            return '';
        }

        if (! isset(self::$term_lang[$term_id])) {
            self::prime_terms([$term_id]);
        }

        return self::$term_lang[$term_id] ?? '';
    }

    /**
     * ترجمه‌های یک دسته.
     *
     * @param int $term_id شناسه دسته
     * @return array<string,int> کد زبان → شناسه دسته
     */
    public static function term_translations(int $term_id): array
    {
        if ($term_id < 1) {
            return [];
        }

        if (! isset(self::$term_trans[$term_id])) {
            self::prime_terms([$term_id]);
        }

        return self::$term_trans[$term_id] ?? [];
    }

    /**
     * خواندن یک‌جای زبان و ترجمه‌های چند دسته (دو پرس‌وجو به‌جای دو پرس‌وجو برای هر دسته).
     *
     * @param array<int,int> $term_ids شناسه‌ها
     * @return void
     */
    public static function prime_terms(array $term_ids): void
    {
        $term_ids = array_values(array_unique(array_filter(array_map('intval', $term_ids), static fn (int $id): bool => $id > 0 && ! isset(self::$term_lang[$id]))));

        if ($term_ids === []) {
            return;
        }

        foreach ($term_ids as $id) {
            self::$term_lang[$id]  = '';
            self::$term_trans[$id] = [];
        }

        $mode = self::mode();

        if ($mode === self::MODE_POLYLANG) {
            foreach ($term_ids as $id) {
                self::$term_lang[$id] = \function_exists('pll_get_term_language') ? (string) \pll_get_term_language($id, 'slug') : '';

                if (\function_exists('pll_get_term_translations')) {
                    self::$term_trans[$id] = array_map('intval', (array) \pll_get_term_translations($id));
                }
            }

            return;
        }

        if ($mode !== self::MODE_PASSIVE) {
            return;
        }

        global $wpdb;

        $in = implode(',', $term_ids);

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT tr.object_id, tt.taxonomy, tt.description, t.slug
               FROM {$wpdb->term_relationships} tr
               INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
               INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
              WHERE tt.taxonomy IN ('term_language', 'term_translations') AND tr.object_id IN ({$in})"
        );
        // phpcs:enable

        $languages = self::languages();

        foreach ((array) $rows as $row) {
            $id = (int) $row->object_id;

            if ($row->taxonomy === 'term_language') {
                $slug = preg_replace('/^pll_/', '', (string) $row->slug);

                if (is_string($slug) && isset($languages[$slug])) {
                    self::$term_lang[$id] = $slug;
                }

                continue;
            }

            $map = [];

            foreach (self::unserialize((string) $row->description) as $lang => $translation) {
                if (is_string($lang) && (int) $translation > 0) {
                    $map[$lang] = (int) $translation;
                }
            }

            self::$term_trans[$id] = $map;
        }
    }

    /**
     * زبان یک نوشته / برگه / محصول.
     *
     * @param int $post_id شناسه
     * @return string کد زبان یا ''
     */
    public static function post_language(int $post_id): string
    {
        if ($post_id < 1) {
            return '';
        }

        if (isset(self::$post_lang[$post_id])) {
            return self::$post_lang[$post_id];
        }

        $mode = self::mode();
        $lang = '';

        if ($mode === self::MODE_POLYLANG && \function_exists('pll_get_post_language')) {
            $lang = (string) \pll_get_post_language($post_id, 'slug');
        } elseif ($mode === self::MODE_PASSIVE) {
            global $wpdb;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $slug = (string) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT t.slug FROM {$wpdb->term_relationships} tr
                       INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                       INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
                      WHERE tt.taxonomy = 'language' AND tr.object_id = %d LIMIT 1",
                    $post_id
                )
            );

            $lang = isset(self::languages()[$slug]) ? $slug : '';
        }

        self::$post_lang[$post_id] = $lang;

        return $lang;
    }

    /* ============================================================== *
     * حالت passive: فیلتر فهرست نوشته‌ها
     * ============================================================== */

    /**
     * آرگومان‌های WP_Query برای «فقط زبان جاری».
     *
     * در حالت polylang خودِ افزونه پرس‌وجو را فیلتر می‌کند و چیزی اضافه نمی‌شود.
     *
     * @param array<string,mixed> $args آرگومان‌های پرس‌وجو
     * @return array<string,mixed>
     */
    public static function query_args(array $args): array
    {
        if (self::mode() === self::MODE_PASSIVE && self::is_multilingual()) {
            $args[self::QUERY_VAR] = self::current();
        }

        return $args;
    }

    /**
     * پرس‌وجوی اصلیِ فهرست‌ها (مجله، بایگانی‌ها، جست‌وجو، خوراک) در حالت passive.
     *
     * صفحه‌های تکی دست نمی‌خورند: نوشته یا محصولِ زبان دیگر با نشانی خودش باز می‌شود.
     *
     * @param \WP_Query $query پرس‌وجو
     * @return void
     */
    public static function filter_main_query($query): void
    {
        if (! $query instanceof \WP_Query || \is_admin() || ! $query->is_main_query() || $query->is_singular()) {
            return;
        }

        if (self::mode() !== self::MODE_PASSIVE || ! self::is_multilingual() || $query->get(self::QUERY_VAR)) {
            return;
        }

        $lang = self::language_of_query($query);

        // phpcs:ignore WordPress.Security.NonceVerification
        $requested = isset($_GET['lang']) && is_string($_GET['lang']) ? \sanitize_key(\wp_unslash($_GET['lang'])) : '';

        if ($requested !== '' && isset(self::languages()[$requested])) {
            $lang = $requested;
        }

        $query->set(self::QUERY_VAR, $lang !== '' ? $lang : self::default_language());
    }

    /**
     * افزودن شرط «نه از زبان‌های دیگر» به پرس‌وجوهایی که beryon_lang دارند.
     *
     * نوشته‌ای که زبانی برایش ثبت نشده (مثلاً ساخته‌شده پس از غیرفعال‌شدن افزونه)
     * کنار گذاشته نمی‌شود و جزو زبان پیش‌فرض به حساب می‌آید.
     *
     * @param string    $where شرط SQL
     * @param \WP_Query $query پرس‌وجو
     * @return string
     */
    public static function filter_posts_where($where, $query)
    {
        if (! $query instanceof \WP_Query) {
            return $where;
        }

        $lang = $query->get(self::QUERY_VAR);

        if (! is_string($lang) || $lang === '' || self::mode() !== self::MODE_PASSIVE) {
            return $where;
        }

        $exclude = [];

        foreach (self::languages() as $slug => $language) {
            if ((string) $slug !== $lang && (int) ($language['tt_id'] ?? 0) > 0) {
                $exclude[] = (int) $language['tt_id'];
            }
        }

        // زبان پیش‌فرض نوشته‌های بی‌زبان را هم نشان می‌دهد؛ زبان‌های دیگر فقط نوشته‌های خودشان را.
        global $wpdb;

        if ($lang !== self::default_language()) {
            $own = (int) (self::languages()[$lang]['tt_id'] ?? 0);

            return $where . ($own > 0
                ? " AND {$wpdb->posts}.ID IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = {$own})"
                : ' AND 1=0');
        }

        if ($exclude === []) {
            return $where;
        }

        return $where . " AND {$wpdb->posts}.ID NOT IN (SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode(',', $exclude) . '))';
    }

    /**
     * زبانِ خودِ صفحه در حالت passive (نوشته/محصول تکی یا بایگانی یک دسته).
     *
     * @param mixed $query پرس‌وجوی اصلی
     * @return string کد زبان یا ''
     */
    private static function language_of_query($query): string
    {
        if (! $query instanceof \WP_Query) {
            return '';
        }

        if ($query->is_singular()) {
            $object = $query->get_queried_object();

            return $object instanceof \WP_Post ? self::post_language((int) $object->ID) : '';
        }

        if ($query->is_tax() || $query->is_category() || $query->is_tag()) {
            $object = $query->get_queried_object();

            return $object instanceof \WP_Term ? self::term_language((int) $object->term_id) : '';
        }

        return '';
    }

    /**
     * خواندن زبان‌ها از داده Polylang در پایگاه داده (بدون نیاز به خودِ افزونه).
     *
     * @return array<string,array<string,mixed>>
     */
    private static function read_languages(): array
    {
        static $read = null;

        if ($read !== null) {
            return $read;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            "SELECT t.slug, t.name, t.term_group, tt.term_taxonomy_id, tt.description
               FROM {$wpdb->terms} t
               INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
              WHERE tt.taxonomy = 'language'
              ORDER BY t.term_group ASC, t.term_id ASC"
        );

        $read = [];

        foreach ((array) $rows as $row) {
            $slug = (string) $row->slug;

            if ($slug === '') {
                continue;
            }

            $meta        = self::unserialize((string) $row->description);
            $read[$slug] = [
                'slug'   => $slug,
                'name'   => (string) $row->name,
                'locale' => (string) ($meta['locale'] ?? ''),
                'rtl'    => ! empty($meta['rtl']),
                'tt_id'  => (int) $row->term_taxonomy_id,
            ];
        }

        return $read;
    }

    /**
     * بازخوانی امن آرایه سریال‌شده (بدون ساخت شیء).
     *
     * @param string $value مقدار
     * @return array<mixed>
     */
    private static function unserialize(string $value): array
    {
        if ($value === '' || ! \is_serialized($value)) {
            return [];
        }

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions, WordPress.PHP.NoSilencedErrors
        $data = @unserialize($value, ['allowed_classes' => false]);

        return is_array($data) ? $data : [];
    }

    /**
     * پاک‌کردن حافظه درون‌درخواستی (برای آزمون و پس از تغییر افزونه‌ها).
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$mode       = null;
        self::$languages  = null;
        self::$current    = null;
        self::$term_lang  = [];
        self::$term_trans = [];
        self::$post_lang  = [];
    }
}
