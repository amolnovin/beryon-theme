<?php

/**
 * سئوی پیشرفته قالب بریون
 * ------------------------------------------------------------------
 * - عنوان، توضیح، canonical، robots (متاباکس اختصاصی هر محتوا + پیش‌فرض هوشمند)
 * - Open Graph / Twitter Card
 * - JSON-LD: Organization(FoodEstablishment)، WebSite، SearchAction، BreadcrumbList،
 *   Product، Article/BlogPosting، FAQPage، CollectionPage
 * - سایت‌مپ XML داخلی با image:image و فیلتر robots.txt
 * - اگر Yoast/RankMath فعال باشد، متای قالب به‌صورت خودکار خاموش می‌شود (بدون تداخل).
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * کلاس سئو.
 */
final class Seo
{
    /** کلیدهای متاباکس سئو */
    public const META_KEYS = ['_beryon_seo_title', '_beryon_seo_desc', '_beryon_seo_canonical', '_beryon_seo_robots', '_beryon_seo_og_image', '_beryon_seo_noindex'];

    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        \add_action('init', [self::class, 'rewrite_rules']);
        \add_action('template_redirect', [self::class, 'maybe_sitemap']);

        if (self::plugin_active()) {
            return; // افزونه سئو فعال است؛ فقط اسکیما و سایت‌مپ اختیاری می‌ماند.
        }

        \add_filter('pre_get_document_title', [self::class, 'document_title'], 20);
        \add_filter('document_title_parts', [self::class, 'title_parts'], 20);

        \add_action('wp_head', [self::class, 'meta_description'], 1);
        \add_action('wp_head', [self::class, 'robots_meta'], 2);
        \add_action('wp_head', [self::class, 'canonical'], 3);
        \add_action('wp_head', [self::class, 'open_graph'], 5);
        \add_action('wp_head', [self::class, 'jsonld'], 8);
        \add_action('wp_head', [self::class, 'favicon_links'], 4);

        \add_filter('robots_txt', [self::class, 'robots_txt'], 20, 2);

        \add_action('add_meta_boxes', [self::class, 'meta_box']);
        \add_action('save_post', [self::class, 'save_meta_box']);
        \add_action('wp_ajax_beryon_seo_preview', [self::class, 'ajax_preview']);
    }

    /**
     * آیا یک افزونه سئوی شناخته‌شده فعال است؟
     *
     * @return bool
     */
    public static function plugin_active(): bool
    {
        $active = class_exists('\WPSEO_Frontend') || defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')
            || defined('AIOSEO_VERSION') || class_exists('\The_SEO_Framework\Bootstrap');

        /**
         * فیلتر تشخیص افزونه سئو.
         *
         * @param bool $active
         */
        return (bool) \apply_filters('beryon/seo/plugin_active', $active);
    }

    /**
     * آیا سئوی داخلی قالب فعال است؟
     *
     * @return bool
     */
    public static function enabled(): bool
    {
        return (bool) Settings::opt('seo.enabled', true) && ! self::plugin_active();
    }

    /* ============================================================== *
     * عنوان و توضیحات
     * ============================================================== */

    /**
     * عنوان سند.
     *
     * @param string $title عنوان محاسبه‌شده
     * @return string
     */
    public static function document_title(string $title): string
    {
        if (! self::enabled()) {
            return $title;
        }

        $custom = self::current_title();

        return $custom !== '' ? $custom : $title;
    }

    /**
     * اجزای عنوان (fallback).
     *
     * @param array<string,string> $parts اجزا
     * @return array<string,string>
     */
    public static function title_parts(array $parts): array
    {
        if (! self::enabled()) {
            return $parts;
        }

        $custom = self::current_title();

        if ($custom !== '') {
            return ['title' => $custom];
        }

        return $parts;
    }

    /**
     * عنوان صفحه جاری (متاباکس → خودکار).
     *
     * @return string
     */
    public static function current_title(): string
    {
        static $computed = null;

        if ($computed !== null) {
            return $computed;
        }

        $template = (string) Settings::opt('seo.title_template', '%s | بریون');
        $title    = '';

        if (\is_singular()) {
            $id    = (int) \get_the_ID();
            $title = (string) \get_post_meta($id, '_beryon_seo_title', true);

            if ($title === '') {
                $title = \get_the_title($id);

                // برای صفحاتی که عنوان کوتاه دارند، برند افزوده می‌شود.
                if ($title !== '' && ! str_contains($title, 'بریون') && \mb_strlen($title) < 60) {
                    $title = sprintf($template, $title);
                }
            }
        } elseif (\is_front_page()) {
            $title = (string) Settings::opt('seo.home_title', '');

            if ($title === '') {
                $title = \__('بریون | فرآورده‌های گوشتی بریان گوشت آمل؛ سوسیس، کالباس، ژامبون با کیفیت صادراتی', BERYON_TD);
            }
        } elseif (\is_post_type_archive() || \is_tax()) {
            $term = \get_queried_object();

            if ($term instanceof \WP_Term) {
                $seo_title = (string) \get_term_meta($term->term_id, '_beryon_seo_title', true);
                $title     = $seo_title !== '' ? $seo_title : self::term_title($term);
            } else {
                $title = \post_type_archive_title('', false);
            }
        } elseif (\is_home()) {
            $title = (string) Settings::opt('blog.title', \__('مجله بریان گوشت', BERYON_TD));
        } elseif (\is_search()) {
            $title = sprintf(\__('نتایج جست‌وجو برای «%s»', BERYON_TD), \get_search_query());
        } elseif (\is_404()) {
            $title = \__('صفحه مورد نظر یافت نشد (۴۰۴)', BERYON_TD);
        }

        $computed = trim((string) $title);

        return $computed;
    }

    /**
     * عنوان خودکار یک دسته محصول (برگرفته از کاتالوگ مرجع).
     *
     * @param \WP_Term $term دسته
     * @return string
     */
    public static function term_title(\WP_Term $term): string
    {
        $catalog = Products::catalog()['categories'][$term->slug] ?? [];

        if (! empty($catalog['seo_title'])) {
            return (string) $catalog['seo_title'];
        }

        $name = $catalog['name'] ?? $term->name;

        return sprintf('%s بریون | استعلام قیمت عمده و پخش', $name);
    }

    /**
     * توضیحات متا.
     *
     * @return void
     */
    public static function meta_description(): void
    {
        if (! self::enabled()) {
            return;
        }

        $description = self::current_description();

        if ($description === '') {
            return;
        }

        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }

    /**
     * توضیح صفحه جاری.
     *
     * @return string
     */
    public static function current_description(): string
    {
        static $computed = null;

        if ($computed !== null) {
            return $computed;
        }

        $description = '';

        if (\is_singular()) {
            $id          = (int) \get_the_ID();
            $description = (string) \get_post_meta($id, '_beryon_seo_desc', true);

            if ($description === '') {
                $post = \get_post($id);

                if ($post instanceof \WP_Post) {
                    $description = $post->post_excerpt !== ''
                        ? \beryon_excerpt_text($post->post_excerpt, 165)
                        : \beryon_excerpt_text((string) $post->post_content, 165);
                }
            }

            if ($description === '' && Products::post_type() === \get_post_type($id)) {
                $data        = Products::normalize($id);
                $description = \beryon_excerpt_text((string) ($data['short'] ?? ''), 165);
            }
        } elseif (\is_tax()) {
            $term = \get_queried_object();

            if ($term instanceof \WP_Term) {
                $description = (string) \get_term_meta($term->term_id, '_beryon_seo_desc', true);

                if ($description === '') {
                    $catalog     = Products::catalog()['categories'][$term->slug] ?? [];
                    $description = (string) ($catalog['desc'] ?? \beryon_excerpt_text($term->description, 165));
                }
            }
        } elseif (\is_post_type_archive()) {
            $description = (string) Settings::opt('products.archive_desc', '');
        } elseif (\is_home() || \is_archive()) {
            $description = (string) Settings::opt('blog.lead', '');
        }

        if ($description === '') {
            $description = (string) Settings::opt('seo.default_description', '');
        }

        $description = \beryon_tokens((string) $description);
        $computed    = \beryon_excerpt_text($description, 165);

        return $computed;
    }

    /**
     * متای robots.
     *
     * @return void
     */
    public static function robots_meta(): void
    {
        if (! self::enabled()) {
            return;
        }

        $directives = [];
        $custom     = '';

        if (\is_singular()) {
            $custom = (string) \get_post_meta((int) \get_the_ID(), '_beryon_seo_robots', true);
        }

        if ($custom !== '') {
            $directives = array_map('trim', explode(',', $custom));
        } else {
            $directives[] = 'index';
            $directives[] = 'follow';
            $directives[] = 'max-image-preview:large';
            $directives[] = 'max-snippet:-1';

            if (\is_search() && Settings::opt('seo.noindex_search', true)) {
                $directives = ['noindex', 'follow'];
            }

            if ((\is_archive() && ! \is_post_type_archive() && ! \is_tax()) && Settings::opt('seo.noindex_archives', false)) {
                $directives = ['noindex', 'follow'];
            }

            if (\is_singular() && (bool) \get_post_meta((int) \get_the_ID(), '_beryon_seo_noindex', true)) {
                $directives = ['noindex', 'follow'];
            }

            if (\is_404() || \is_paged() && \is_singular()) {
                $directives = ['noindex', 'follow'];
            }
        }

        /**
         * فیلتر دستورهای robots.
         *
         * @param array $directives
         */
        $directives = (array) \apply_filters('beryon/seo/robots', $directives);

        echo '<meta name="robots" content="' . esc_attr(implode(', ', array_unique($directives))) . '">' . "\n";
    }

    /**
     * لینک canonical.
     *
     * @return void
     */
    public static function canonical(): void
    {
        if (! self::enabled()) {
            return;
        }

        $url = '';

        if (\is_singular()) {
            $url = (string) \get_post_meta((int) \get_the_ID(), '_beryon_seo_canonical', true);

            if ($url === '') {
                $url = (string) \get_permalink();
            }
        } elseif (\is_front_page()) {
            $url = \home_url('/');
        } elseif (\is_tax() || \is_post_type_archive() || \is_home() || \is_archive()) {
            $url = (string) self::queried_url();
        }

        if ($url === '' || \is_search() || \is_404()) {
            return;
        }

        echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
    }

    /**
     * آدرس صفحه جاری (بدون پارامترهای صفحه‌بندی غیرضروری).
     *
     * @return string
     */
    public static function queried_url(): string
    {
        if (\is_tax() || \is_category() || \is_tag()) {
            $term = \get_queried_object();

            return $term instanceof \WP_Term ? (string) \get_term_link($term) : \home_url('/');
        }

        if (\is_post_type_archive()) {
            return (string) \get_post_type_archive_link(\get_query_var('post_type'));
        }

        if (\is_home()) {
            $page = (int) \get_option('page_for_posts');

            return $page ? (string) \get_permalink($page) : \home_url('/');
        }

        return (string) \home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''));
    }

    /**
     * Open Graph + Twitter Card.
     *
     * @return void
     */
    public static function open_graph(): void
    {
        $title       = self::enabled() ? self::current_title() : \wp_get_document_title();
        $description = self::enabled() ? self::current_description() : \get_bloginfo('description');
        $url         = \is_singular() ? (string) \get_permalink() : self::queried_url();
        $type        = 'website';
        $image       = (string) Settings::opt('seo.og_image', 'hero.webp');

        if (\is_singular('post')) {
            $type = 'article';
        }

        if (\is_singular()) {
            $og = (int) \get_post_meta((int) \get_the_ID(), '_beryon_seo_og_image', true);

            if ($og > 0) {
                $src = \wp_get_attachment_image_url($og, 'beryon-wide');

                if ($src) {
                    $image = $src;
                }
            } elseif (\has_post_thumbnail()) {
                $src = \get_the_post_thumbnail_url(null, 'beryon-wide');

                if ($src) {
                    $image = $src;
                }
            }
        }

        if (! preg_match('#^https?://#', $image)) {
            $image = Assets::image_url($image);
        }

        $site_name = (string) Settings::opt('seo.og_site_name', \get_bloginfo('name'));
        $locale    = (string) Settings::opt('seo.og_locale', 'fa_IR');

        $tags = [
            ['property' => 'og:type', 'content' => $type],
            ['property' => 'og:site_name', 'content' => $site_name],
            ['property' => 'og:title', 'content' => $title],
            ['property' => 'og:description', 'content' => $description],
            ['property' => 'og:url', 'content' => $url],
            ['property' => 'og:image', 'content' => $image],
            ['property' => 'og:image:alt', 'content' => $title],
            ['property' => 'og:locale', 'content' => $locale],
            ['name' => 'twitter:card', 'content' => (string) Settings::opt('seo.twitter_card', 'summary_large_image')],
            ['name' => 'twitter:title', 'content' => $title],
            ['name' => 'twitter:description', 'content' => $description],
            ['name' => 'twitter:image', 'content' => $image],
        ];

        if ($type === 'article' && \is_singular()) {
            $tags[] = ['property' => 'article:published_time', 'content' => \get_the_date('c')];
            $tags[] = ['property' => 'article:modified_time', 'content' => \get_the_modified_date('c')];
        }

        /**
         * فیلتر تگ‌های اجتماعی.
         *
         * @param array $tags
         */
        foreach ((array) \apply_filters('beryon/seo/social_tags', $tags) as $tag) {
            $key = isset($tag['property']) ? 'property' : 'name';

            echo '<meta ' . esc_attr($key) . '="' . esc_attr((string) $tag[$key]) . '" content="' . esc_attr((string) $tag['content']) . '">' . "\n";
        }
    }

    /**
     * آیکون‌ها و لینک‌های سرصفحه.
     *
     * @return void
     */
    public static function favicon_links(): void
    {
        $logo = \beryon_logo_url();

        if ($logo === '') {
            return;
        }

        echo '<link rel="icon" type="image/png" href="' . esc_url($logo) . '">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . esc_url($logo) . '">' . "\n";
    }

    /* ============================================================== *
     * داده ساخت‌یافته (JSON-LD)
     * ============================================================== */

    /**
     * چاپ بلوک‌های JSON-LD.
     *
     * @return void
     */
    public static function jsonld(): void
    {
        $blocks = [];

        if (Settings::opt('seo.schema_org', true)) {
            $blocks[] = self::organization();
        }

        if (Settings::opt('seo.schema_website', true) && \is_front_page()) {
            $blocks[] = self::website();
        }

        if (Settings::opt('seo.schema_breadcrumb', true)) {
            $crumbs = self::breadcrumb_schema();

            if ($crumbs !== []) {
                $blocks[] = $crumbs;
            }
        }

        $post_type = Products::post_type();

        if (Settings::opt('seo.schema_product', true) && \is_singular($post_type)) {
            $product = self::product_schema();

            if ($product !== []) {
                $blocks[] = $product;
            }
        }

        if (Settings::opt('seo.schema_product', true) && (\is_tax(Products::TAX) || \is_post_type_archive($post_type))) {
            $collection = self::collection_schema();

            if ($collection !== []) {
                $blocks[] = $collection;
            }
        }

        if (Settings::opt('seo.schema_article', true) && \is_singular('post')) {
            $blocks[] = self::article_schema();
        }

        if (Settings::opt('seo.schema_faq', true) && str_contains((string) \get_page_template_slug(), 'feedback')) {
            $faq = self::faq_schema();

            if ($faq !== []) {
                $blocks[] = $faq;
            }
        }

        /**
         * فیلتر بلوک‌های اسکیما.
         *
         * @param array $blocks
         */
        $blocks = array_values(array_filter((array) \apply_filters('beryon/seo/jsonld', $blocks)));

        foreach ($blocks as $block) {
            echo '<script type="application/ld+json">' . self::encode($block) . '</script>' . "\n";
        }
    }

    /**
     * @param array<string,mixed> $data داده اسکیما
     * @return string
     */
    private static function encode(array $data): string
    {
        $json = (string) \wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return str_replace(['<', '>', '&'], ['\u003c', '\u003e', '\u0026'], $json);
    }

    /**
     * اسکیما سازمان.
     *
     * @return array<string,mixed>
     */
    public static function organization(): array
    {
        $general = [
            'company_name' => (string) Settings::opt('general.company_name', \__('شرکت بریان گوشت آمل', BERYON_TD)),
            'brand_name'   => (string) Settings::opt('general.brand_name', \__('بریون', BERYON_TD)),
        ];

        $data = [
            '@context'      => 'https://schema.org',
            '@type'         => (string) Settings::opt('seo.org_type', 'FoodEstablishment'),
            '@id'           => \home_url('/#organization'),
            'name'          => $general['brand_name'] . ' | ' . $general['company_name'],
            'alternateName' => $general['company_name'],
            'url'           => \home_url('/'),
            'logo'          => [
                '@type'  => 'ImageObject',
                'url'    => \beryon_logo_url(),
                'width'  => 148,
                'height' => 112,
            ],
            'image'         => Assets::image_url((string) Settings::opt('general.og_image', 'hero.webp')),
            'telephone'     => '+' . preg_replace('/\D/', '', (string) Settings::opt('general.phone_factory', '')),
            'email'         => (string) Settings::opt('general.email', ''),
            'description'   => self::current_description(),
            'foundingDate'  => (string) Settings::opt('seo.org_founding_year', '2005'),
            'brand'         => ['@type' => 'Brand', 'name' => $general['brand_name']],
            'address'       => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => (string) Settings::opt('general.address', ''),
                'addressLocality' => \__('آمل', BERYON_TD),
                'addressRegion'   => \__('مازندران', BERYON_TD),
                'addressCountry'  => 'IR',
            ],
            'areaServed'    => ['@type' => 'Country', 'name' => \__('ایران', BERYON_TD)],
            'knowsAbout'    => [\__('سوسیس', BERYON_TD), \__('کالباس', BERYON_TD), \__('ژامبون', BERYON_TD), \__('فرآورده‌های گوشتی', BERYON_TD), \__('محصولات انجمادی', BERYON_TD), \__('محصولات گیاهی', BERYON_TD)],
            'sameAs'        => self::same_as(),
        ];

        $data = array_filter($data, static fn ($value) => $value !== '' && $value !== null && $value !== []);

        return $data;
    }

    /**
     * لینک‌های شبکه‌های اجتماعی (sameAs).
     *
     * @return array<int,string>
     */
    public static function same_as(): array
    {
        $links = [];

        foreach ((array) Settings::opt('general.socials', []) as $social) {
            if (is_array($social) && ! empty($social['url'])) {
                $links[] = (string) $social['url'];
            }
        }

        return array_values(array_filter($links));
    }

    /**
     * اسکیما وب‌سایت + جست‌وجو.
     *
     * @return array<string,mixed>
     */
    public static function website(): array
    {
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            '@id'             => \home_url('/#website'),
            'name'            => (string) Settings::opt('general.brand_name', 'بریون'),
            'alternateName'   => (string) Settings::opt('general.company_name', ''),
            'url'             => \home_url('/'),
            'inLanguage'      => 'fa-IR',
            'publisher'       => ['@id' => \home_url('/#organization')],
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => \home_url('/?s={search_term_string}')],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * اسکیما مسیر (BreadcrumbList).
     *
     * @return array<string,mixed>
     */
    public static function breadcrumb_schema(): array
    {
        $items = \beryon_breadcrumb();

        if (count($items) < 2) {
            return [];
        }

        $elements = [];

        foreach ($items as $index => $item) {
            $element = [
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'name'     => (string) $item['name'],
            ];

            if (! empty($item['url'])) {
                $element['item'] = (string) $item['url'];
            }

            $elements[] = $element;
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * اسکیما محصول (بدون Offer در حالت کاتالوگ/استعلام).
     *
     * @return array<string,mixed>
     */
    public static function product_schema(): array
    {
        $data = Products::normalize((int) \get_the_ID());

        if ($data === []) {
            return [];
        }

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => (string) $data['title'],
            'image'       => (string) $data['image'],
            'description' => (string) $data['short'],
            'sku'         => (string) \get_post_meta($data['id'], '_sku', true),
            'brand'       => ['@type' => 'Brand', 'name' => (string) Settings::opt('general.brand_name', 'بریون')],
            'category'    => (string) $data['cat_name'],
            'url'         => (string) $data['link'],
        ];

        if (! empty($data['unit_weight'])) {
            $number = (int) preg_replace('/\D/u', '', \beryon_en_digits((string) $data['unit_weight']));

            if ($number > 0) {
                $schema['weight'] = [
                    '@type'   => 'QuantitativeValue',
                    'value'   => $number,
                    'unitCode' => str_contains((string) $data['unit_weight'], 'کیلو') ? 'KGM' : 'GRM',
                ];
            }
        }

        $props = [];

        if (! empty($data['meat'])) {
            $props[] = ['@type' => 'PropertyValue', 'name' => \__('درصد گوشت', BERYON_TD), 'value' => $data['meat'] . '%'];
        }

        foreach ([
            'meat_type'   => \__('نوع گوشت', BERYON_TD),
            'packaging'   => \__('بسته‌بندی', BERYON_TD),
            'shelf_life'  => \__('ماندگاری', BERYON_TD),
            'storage'     => \__('شرایط نگهداری', BERYON_TD),
        ] as $key => $label) {
            if (! empty($data[$key])) {
                $props[] = ['@type' => 'PropertyValue', 'name' => $label, 'value' => (string) $data[$key]];
            }
        }

        if ($props !== []) {
            $schema['additionalProperty'] = $props;
        }

        // حالت فروشگاه: اگر قیمت وجود داشته باشد، Offer افزوده می‌شود.
        if (Products::woo_active() && \function_exists('wc_get_product') && ! Settings::opt('products.catalog_mode', true)) {
            $product = \wc_get_product($data['id']);

            if ($product instanceof \WC_Product && $product->get_price() !== '') {
                $schema['offers'] = [
                    '@type'         => 'Offer',
                    'price'         => \wc_format_decimal($product->get_price()),
                    'priceCurrency' => \get_woocommerce_currency(),
                    'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url'           => (string) $data['link'],
                ];
            }
        }

        return array_filter($schema, static fn ($value) => $value !== '' && $value !== null && $value !== []);
    }

    /**
     * اسکیما مجموعه محصولات (آرشیو/دسته).
     *
     * @return array<string,mixed>
     */
    public static function collection_schema(): array
    {
        $items = [];
        $term  = \get_queried_object();
        $name  = \is_tax() && $term instanceof \WP_Term ? $term->name : \__('محصولات بریون', BERYON_TD);

        foreach (Products::query(['category' => $term instanceof \WP_Term ? $term->slug : '', 'limit' => 20]) as $product) {
            $items[] = [
                '@type' => 'Product',
                'name'  => (string) $product['title'],
                'url'   => (string) $product['link'],
                'image' => (string) $product['image'],
            ];
        }

        if ($items === []) {
            return [];
        }

        return [
            '@context'          => 'https://schema.org',
            '@type'             => 'CollectionPage',
            'name'              => $name,
            'url'               => self::queried_url(),
            'isPartOf'          => ['@id' => \home_url('/#website')],
            'hasPart'           => $items,
            'mainEntity'        => [
                '@type'               => 'ItemList',
                'numberOfItems'       => count($items),
                'itemListElement'     => array_map(
                    /**
                     * @param array<string,mixed> $item  آیتم
                     * @param int                 $index شماره
                     * @return array<string,mixed>
                     */
                    static fn (array $item, int $index): array => ['@type' => 'ListItem', 'position' => $index + 1, 'url' => $item['url'], 'name' => $item['name']],
                    $items,
                    array_keys($items)
                ),
            ],
        ];
    }

    /**
     * اسکیما مقاله.
     *
     * @return array<string,mixed>
     */
    public static function article_schema(): array
    {
        $id    = (int) \get_the_ID();
        $post  = \get_post($id);
        $image = \has_post_thumbnail($id) ? (string) \get_the_post_thumbnail_url($id, 'beryon-wide') : Assets::image_url((string) Settings::opt('seo.og_image', 'hero.webp'));

        if (! $post instanceof \WP_Post) {
            return [];
        }

        $author_name = (string) Settings::opt('blog.author_label', \__('تحریریه مجله بریان گوشت', BERYON_TD));
        $author      = \get_the_author_meta('display_name', $post->post_author);

        if ($author !== '' && $author !== 'admin') {
            $author_name = $author;
        }

        return [
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => (string) \get_permalink($id)],
            'headline'         => \beryon_excerpt_text(\get_the_title($id), 110),
            'description'      => self::current_description(),
            'image'            => $image,
            'datePublished'    => \get_the_date('c', $id),
            'dateModified'     => \get_the_modified_date('c', $id),
            'inLanguage'       => 'fa-IR',
            'author'           => ['@type' => 'Organization', 'name' => $author_name],
            'publisher'        => [
                '@type' => 'Organization',
                'name'  => (string) Settings::opt('general.brand_name', 'بریون'),
                'logo'  => ['@type' => 'ImageObject', 'url' => \beryon_logo_url()],
            ],
        ];
    }

    /**
     * اسکیما پرسش‌های پرتکرار.
     *
     * @return array<string,mixed>
     */
    public static function faq_schema(): array
    {
        $items = [];

        foreach ((array) Settings::opt('feedback.faqs', []) as $faq) {
            if (! is_array($faq) || empty($faq['q']) || empty($faq['a'])) {
                continue;
            }

            $items[] = [
                '@type'          => 'Question',
                'name'           => \beryon_tokens((string) $faq['q']),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => \wp_strip_all_tags(\beryon_tokens((string) $faq['a'])),
                ],
            ];
        }

        if ($items === []) {
            return [];
        }

        return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items];
    }

    /* ============================================================== *
     * سایت‌مپ و robots.txt
     * ============================================================== */

    /**
     * ثبت قاعده بازنویسی سایت‌مپ.
     *
     * @return void
     */
    public static function rewrite_rules(): void
    {
        if (! (bool) Settings::opt('seo.sitemap_enabled', true)) {
            return;
        }

        $slug = (string) Settings::opt('seo.sitemap_slug', 'sitemap.xml');

        \add_rewrite_rule('^' . preg_quote($slug, '/') . '?$', 'index.php?beryon_sitemap=1', 'top');
        \add_rewrite_rule('^' . preg_quote($slug, '/') . '/([0-9]+)/?$', 'index.php?beryon_sitemap=1&beryon_sitemap_page=$matches[1]', 'top');

    }

    /**
     * پاسخ به درخواست سایت‌مپ.
     *
     * @return void
     */
    public static function maybe_sitemap(): void
    {
        if (! \get_query_var('beryon_sitemap')) {
            return;
        }

        if (! (bool) Settings::opt('seo.sitemap_enabled', true)) {
            \wp_die(esc_html__('سایت‌مپ غیرفعال است.', BERYON_TD), 404, ['response' => 404]);
        }

        $page = max(1, (int) \get_query_var('beryon_sitemap_page', 1));

        \nocache_headers();
        \header('Content-Type: application/xml; charset=UTF-8');
        \header('X-Robots-Tag: noindex');

        echo self::sitemap_xml($page); // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی XML کنترل‌شده
        exit;
    }

    /**
     * ساخت XML سایت‌مپ (با حافظه نهان Transient).
     *
     * @param int $page شماره صفحه
     * @return string
     */
    public static function sitemap_xml(int $page = 1): string
    {
        $cache_key = 'beryon_sitemap_' . $page;
        $cached    = \get_transient($cache_key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $per_page = (int) Settings::opt('seo.sitemap_per_page', 200);
        $offset   = ($page - 1) * $per_page;
        $urls     = [];

        // صفحه اصلی
        if ($page === 1) {
            $urls[] = ['loc' => \home_url('/'), 'lastmod' => \gmdate('c'), 'priority' => '1.0', 'changefreq' => 'weekly'];
        }

        $post_types = array_merge(['page', 'post'], [Products::post_type()]);
        $post_types = array_values(array_unique(array_filter($post_types)));

        $query = new \WP_Query(
            [
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'offset'         => $offset,
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'no_found_rows'  => false,
                'fields'         => 'ids',
            ]
        );

        foreach ($query->posts as $post_id) {
            $entry = [
                'loc'        => (string) \get_permalink($post_id),
                'lastmod'    => \get_the_modified_date('c', $post_id),
                'priority'   => \get_post_type($post_id) === Products::post_type() ? '0.8' : '0.7',
                'changefreq' => 'monthly',
            ];

            if (\has_post_thumbnail($post_id)) {
                $entry['image'] = [
                    'loc'   => (string) \get_the_post_thumbnail_url($post_id, 'large'),
                    'title' => \beryon_excerpt_text(\get_the_title($post_id), 70),
                ];
            }

            $urls[] = $entry;
        }

        // دسته‌های محصولات
        if ($page === 1) {
            foreach (Products::categories() as $category) {
                $urls[] = ['loc' => (string) $category['link'], 'priority' => '0.8', 'changefreq' => 'weekly'];
            }
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . esc_url($url['loc']) . "</loc>\n";

            if (! empty($url['lastmod'])) {
                $xml .= '    <lastmod>' . esc_html((string) $url['lastmod']) . "</lastmod>\n";
            }

            $xml .= '    <changefreq>' . esc_html((string) ($url['changefreq'] ?? 'monthly')) . "</changefreq>\n";
            $xml .= '    <priority>' . esc_html((string) ($url['priority'] ?? '0.6')) . "</priority>\n";

            if (! empty($url['image']['loc'])) {
                $xml .= "    <image:image>\n";
                $xml .= '      <image:loc>' . esc_url((string) $url['image']['loc']) . "</image:loc>\n";
                $xml .= '      <image:title>' . esc_html((string) ($url['image']['title'] ?? '')) . "</image:title>\n";
                $xml .= "    </image:image>\n";
            }

            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>' . "\n";

        if ($query->max_num_pages > $page) {
            $next = \home_url('/' . Settings::opt('seo.sitemap_slug', 'sitemap.xml') . '/' . ($page + 1) . '/');
            $xml  = str_replace('<urlset ', '<!-- next: ' . esc_url($next) . ' -->' . "\n" . '<urlset ', $xml);
        }

        \set_transient($cache_key, $xml, 12 * HOUR_IN_SECONDS);

        return $xml;
    }

    /**
     * پاک‌سازی حافظه نهان سایت‌مپ.
     *
     * @return void
     */
    public static function flush_sitemap(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_beryon_sitemap_%' OR option_name LIKE '_transient_timeout_beryon_sitemap_%'");
    }

    /**
     * robots.txt سفارشی.
     *
     * @param string $output خروجی
     * @param bool   $public عمومی بودن سایت
     * @return string
     */
    public static function robots_txt(string $output, bool $public): string
    {
        if (! $public) {
            return "User-agent: *\nDisallow: /\n";
        }

        $sitemap = (bool) Settings::opt('seo.sitemap_enabled', true)
            ? \home_url('/' . Settings::opt('seo.sitemap_slug', 'sitemap.xml'))
            : '';

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /wp-admin/',
            'Disallow: /wp-includes/',
            'Disallow: /?s=',
            'Disallow: /search/',
            'Disallow: /cart/',
            'Disallow: /checkout/',
            'Disallow: /my-account/',
            'Allow: /wp-admin/admin-ajax.php',
            '',
        ];

        if ($sitemap !== '') {
            $lines[] = 'Sitemap: ' . $sitemap;
        }

        /**
         * فیلتر خطوط robots.txt.
         *
         * @param array $lines
         */
        return implode("\n", (array) \apply_filters('beryon/seo/robots_lines', $lines)) . "\n";
    }

    /* ============================================================== *
     * متاباکس سئو
     * ============================================================== */

    /**
     * ثبت متاباکس.
     *
     * @return void
     */
    public static function meta_box(): void
    {
        if (! self::enabled()) {
            return;
        }

        $screens = array_values(array_unique(array_filter(['post', 'page', Products::post_type(), Products::CPT])));

        foreach ($screens as $screen) {
            \add_meta_box('beryon_seo', \__('سئو — قالب بریون', BERYON_TD), [self::class, 'meta_box_html'], $screen, 'normal', 'default');
        }

        \add_action('product_cat_add_form_fields', [self::class, 'term_fields']);
        \add_action('product_cat_edit_form_fields', [self::class, 'term_fields_edit']);
        \add_action('created_product_cat', [self::class, 'save_term']);
        \add_action('edited_product_cat', [self::class, 'save_term']);
    }

    /**
     * خروجی متاباکس سئو.
     *
     * @param \WP_Post $post پست
     * @return void
     */
    public static function meta_box_html(\WP_Post $post): void
    {
        \wp_nonce_field('beryon_seo', 'beryon_seo_nonce');

        $values = [];

        foreach (self::META_KEYS as $key) {
            $values[$key] = \get_post_meta($post->ID, $key, true);
        }

        $suggested_title = \beryon_excerpt_text(\get_the_title($post), 60);
        $suggested_desc  = \beryon_excerpt_text($post->post_excerpt !== '' ? $post->post_excerpt : (string) $post->post_content, 160);

        echo '<div class="beryon-seo" style="direction:rtl">';
        echo '<p class="description">' . esc_html__('پیشنهاد: عنوان تا ۶۰ نویسه و توضیحات ۱۲۰ تا ۱۶۰ نویسه. خالی بگذارید تا مقدار خودکار استفاده شود.', BERYON_TD) . '</p>';

        echo '<p><label for="_beryon_seo_title"><strong>' . esc_html__('عنوان سئو', BERYON_TD) . '</strong></label>'
            . '<input type="text" class="widefat" id="_beryon_seo_title" name="beryon_seo[_beryon_seo_title]" value="' . esc_attr((string) $values['_beryon_seo_title']) . '" placeholder="' . esc_attr($suggested_title) . '">'
            . '<span class="description" data-beryon-count="_beryon_seo_title"></span></p>';

        echo '<p><label for="_beryon_seo_desc"><strong>' . esc_html__('توضیحات متا', BERYON_TD) . '</strong></label>'
            . '<textarea class="widefat" rows="3" id="_beryon_seo_desc" name="beryon_seo[_beryon_seo_desc]" placeholder="' . esc_attr($suggested_desc) . '">' . esc_textarea((string) $values['_beryon_seo_desc']) . '</textarea>'
            . '<span class="description" data-beryon-count="_beryon_seo_desc"></span></p>';

        echo '<p><label for="_beryon_seo_canonical"><strong>' . esc_html__('آدرس canonical (اختیاری)', BERYON_TD) . '</strong></label>'
            . '<input type="url" class="widefat" id="_beryon_seo_canonical" name="beryon_seo[_beryon_seo_canonical]" value="' . esc_attr((string) $values['_beryon_seo_canonical']) . '" placeholder="' . esc_url((string) \get_permalink($post)) . '"></p>';

        echo '<p><label for="_beryon_seo_robots"><strong>' . esc_html__('دستور robots (اختیاری)', BERYON_TD) . '</strong></label>'
            . '<input type="text" class="widefat" id="_beryon_seo_robots" name="beryon_seo[_beryon_seo_robots]" value="' . esc_attr((string) $values['_beryon_seo_robots']) . '" placeholder="index, follow, max-image-preview:large"></p>';

        echo '<p><label for="_beryon_seo_og_image"><strong>' . esc_html__('شناسه تصویر OG (اختیاری)', BERYON_TD) . '</strong></label>'
            . '<input type="number" class="small-text" id="_beryon_seo_og_image" name="beryon_seo[_beryon_seo_og_image]" value="' . esc_attr((string) $values['_beryon_seo_og_image']) . '"></p>';

        echo '<p><label><input type="checkbox" name="beryon_seo[_beryon_seo_noindex]" value="1" ' . checked($values['_beryon_seo_noindex'], '1', false) . '> '
            . esc_html__('این صفحه ایندکس نشود (noindex)', BERYON_TD) . '</label></p>';

        echo '</div>';
    }

    /**
     * ذخیره متاباکس سئو.
     *
     * @param int $post_id شناسه پست
     * @return void
     */
    public static function save_meta_box(int $post_id): void
    {
        if (! isset($_POST['beryon_seo_nonce']) || ! \wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST['beryon_seo_nonce'])), 'beryon_seo')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! \current_user_can('edit_post', $post_id)) {
            return;
        }

        $values = isset($_POST['beryon_seo']) && is_array($_POST['beryon_seo']) ? \wp_unslash($_POST['beryon_seo']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        foreach (self::META_KEYS as $key) {
            $value = $values[$key] ?? '';

            if (is_array($value)) {
                continue;
            }

            $value = (string) $value;

            if ($key === '_beryon_seo_canonical') {
                $value = $value === '' ? '' : \esc_url_raw($value);
            } elseif ($key === '_beryon_seo_og_image') {
                $value = $value === '' ? '' : (string) absint($value);
            } else {
                $value = \sanitize_text_field($value);
            }

            if ($value === '') {
                \delete_post_meta($post_id, $key);

                continue;
            }

            \update_post_meta($post_id, $key, $value);
        }
    }

    /**
     * فیلدهای سئوی دسته محصول (افزودن).
     *
     * @return void
     */
    public static function term_fields(): void
    {
        \wp_nonce_field('beryon_seo_term', 'beryon_seo_term_nonce');

        echo '<div class="form-field"><label for="beryon_term_title">' . esc_html__('عنوان سئو دسته', BERYON_TD) . '</label>'
            . '<input type="text" id="beryon_term_title" name="beryon_seo_term[_beryon_seo_title]" value="">'
            . '<p class="description">' . esc_html__('خالی = عنوان خودکار', BERYON_TD) . '</p></div>';

        echo '<div class="form-field"><label for="beryon_term_desc">' . esc_html__('توضیحات متا دسته', BERYON_TD) . '</label>'
            . '<textarea id="beryon_term_desc" name="beryon_seo_term[_beryon_seo_desc]" rows="3"></textarea></div>';

        echo '<div class="form-field"><label for="beryon_term_image">' . esc_html__('نام تصویر دسته (از دارایی قالب یا رسانه)', BERYON_TD) . '</label>'
            . '<input type="text" id="beryon_term_image" name="beryon_image" value="" placeholder="cat-sausage.webp"></div>';
    }

    /**
     * فیلدهای سئوی دسته محصول (ویرایش).
     *
     * @param \WP_Term $term دسته
     * @return void
     */
    public static function term_fields_edit(\WP_Term $term): void
    {
        \wp_nonce_field('beryon_seo_term', 'beryon_seo_term_nonce');

        $title = (string) \get_term_meta($term->term_id, '_beryon_seo_title', true);
        $desc  = (string) \get_term_meta($term->term_id, '_beryon_seo_desc', true);
        $image = (string) \get_term_meta($term->term_id, '_beryon_image', true);

        echo '<tr class="form-field"><th scope="row"><label for="beryon_term_title">' . esc_html__('عنوان سئو دسته', BERYON_TD) . '</label></th>'
            . '<td><input type="text" id="beryon_term_title" name="beryon_seo_term[_beryon_seo_title]" value="' . esc_attr($title) . '"></td></tr>';

        echo '<tr class="form-field"><th scope="row"><label for="beryon_term_desc">' . esc_html__('توضیحات متا دسته', BERYON_TD) . '</label></th>'
            . '<td><textarea id="beryon_term_desc" name="beryon_seo_term[_beryon_seo_desc]" rows="3">' . esc_textarea($desc) . '</textarea></td></tr>';

        echo '<tr class="form-field"><th scope="row"><label for="beryon_term_image">' . esc_html__('نام تصویر دسته', BERYON_TD) . '</label></th>'
            . '<td><input type="text" id="beryon_term_image" name="beryon_image" value="' . esc_attr($image) . '"></td></tr>';
    }

    /**
     * ذخیره فیلدهای دسته.
     *
     * @param int $term_id شناسه دسته
     * @return void
     */
    public static function save_term(int $term_id): void
    {
        if (! isset($_POST['beryon_seo_term_nonce']) || ! \wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST['beryon_seo_term_nonce'])), 'beryon_seo_term')) {
            return;
        }

        if (! \current_user_can('manage_options')) {
            return;
        }

        $values = isset($_POST['beryon_seo_term']) && is_array($_POST['beryon_seo_term']) ? \wp_unslash($_POST['beryon_seo_term']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        foreach ($values as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, '_beryon_seo_') || is_array($value)) {
                continue;
            }

            $value = \sanitize_text_field((string) $value);

            if ($value === '') {
                \delete_term_meta($term_id, $key);
            } else {
                \update_term_meta($term_id, $key, $value);
            }
        }

        if (isset($_POST['beryon_image'])) {
            $image = \sanitize_text_field(\wp_unslash($_POST['beryon_image'])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

            if ($image === '') {
                \delete_term_meta($term_id, '_beryon_image');
            } else {
                \update_term_meta($term_id, '_beryon_image', $image);
            }
        }

        Products::flush_cache();
    }

    /**
     * پیش‌نمایش عنوان/توضیح (AJAX پنل).
     *
     * @return void
     */
    public static function ajax_preview(): void
    {
        \check_ajax_referer('beryon_seo', 'nonce');

        if (! \current_user_can('edit_posts')) {
            \wp_send_json_error(['message' => \__('اجازه دسترسی ندارید.', BERYON_TD)], 403);
        }

        \wp_send_json_success(
            [
                'title_limit' => 60,
                'desc_min'    => 120,
                'desc_max'    => 165,
            ]
        );
    }
}
