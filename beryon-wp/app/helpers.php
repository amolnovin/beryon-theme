<?php

/**
 * توابع قالب (Template Tags) بریون
 * ------------------------------------------------------------------
 * همه توابع با پیشوند beryon_ و در فضای نام سراسری تعریف می‌شوند تا در
 * فایل‌های Blade قابل استفاده باشند. هیچ‌کدام خروجی چاپ نمی‌کنند مگر
 * آن‌هایی که صریحاً echo دارند (beryon_icon, beryon_nav).
 *
 * @package beryon
 */

defined('ABSPATH') || exit;

use App\Assets;
use App\Jalali;
use App\Settings;

/* ================================================================== *
 * ۱) اعداد و تاریخ فارسی
 * ================================================================== */

/**
 * تبدیل ارقام لاتین به فارسی.
 *
 * @param mixed $value مقدار ورودی
 * @return string
 */
function beryon_fa($value): string
{
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    return str_replace(range(0, 9), $persian, (string) $value);
}

/**
 * تبدیل ارقام فارسی/عربی به لاتین (برای پردازش سمت سرور و JS).
 *
 * @param mixed $value مقدار ورودی
 * @return string
 */
function beryon_en_digits($value): string
{
    $from = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    return str_replace($from, range(0, 9), (string) $value);
}

/**
 * عدد با جداکننده هزارگان و ارقام فارسی (۵٬۰۰۰).
 *
 * @param int|float|string $number عدد
 * @return string
 */
function beryon_number($number): string
{
    $clean = beryon_en_digits($number);

    return $clean === '' ? '' : beryon_fa(number_format((float) $clean));
}

/**
 * درصد با ارقام فارسی (۶۰٪).
 *
 * @param int|float|string|null $percent درصد
 * @return string
 */
function beryon_percent($percent): string
{
    if ($percent === null || $percent === '') {
        return '';
    }

    return beryon_fa(beryon_en_digits($percent)) . '٪';
}

/**
 * تاریخ میلادی → شمسی (۱۴۰۵/۰۶/۱۷).
 *
 * @param string|int|null $timestamp زمان (پیش‌فرض: الان)
 * @return string
 */
function beryon_jalali_date($timestamp = null): string
{
    return Jalali::format($timestamp, Settings::opt('general.date_format', 'Y/m/d'));
}

/**
 * تاریخ انتشار پست/محصول به شمسی.
 *
 * @param int|\WP_Post|null $post پست
 * @return string
 */
function beryon_post_jalali_date($post = null): string
{
    $post = get_post($post);

    return $post ? beryon_jalali_date(get_post_timestamp($post)) : '';
}

/* ================================================================== *
 * ۲) تنظیمات قالب
 * ================================================================== */

/**
 * خواندن یک تنظیم قالب با نقطه‌مسیر (general.phone) و مقدار پیش‌فرض.
 *
 * @param string $key     کلید
 * @param mixed  $default پیش‌فرض محلی
 * @return mixed
 */
function beryon_opt(string $key, $default = null)
{
    return Settings::opt($key, $default);
}

/**
 * چند تنظیم یک‌جا.
 *
 * @param array<string,mixed> $defaults کلید => پیش‌فرض
 * @return array<string,mixed>
 */
function beryon_opts(array $defaults = []): array
{
    $out = [];

    foreach ($defaults as $key => $fallback) {
        $out[$key] = beryon_opt($key, $fallback);
    }

    return $out;
}

/**
 * فهرست تکرارشونده (آمار، مزیت‌ها، گاه‌شمار و…) با کلیدهای نرمال‌شده.
 *
 * @param string               $key     کلید تنظیم
 * @param array<int,array>     $default ردیف‌های پیش‌فرض
 * @return array<int,array<string,mixed>>
 */
function beryon_rows(string $key, array $default = []): array
{
    $rows = Settings::opt($key, null);

    if (! is_array($rows) || $rows === []) {
        return $default;
    }

    // ردیف‌های خالی (حذف‌شده در UI) کنار گذاشته می‌شوند.
    $rows = array_values(
        array_filter(
            $rows,
            /** @param mixed $row */
            static fn ($row): bool => is_array($row) && array_filter($row, static fn ($v) => $v !== '' && $v !== null) !== []
        )
    );

    return $rows === [] ? $default : $rows;
}

/* ================================================================== *
 * ۳) دارایی‌ها (Asset) و تصاویر
 * ================================================================== */

/**
 * URL یک دارایی قالب (با fallback هوشمند: build → public → resources).
 *
 * @param string $path مسیر نسبی، مثال: css/app.css یا images/hero.webp
 * @return string
 */
function beryon_asset(string $path): string
{
    return Assets::url($path);
}

/**
 * نسخه (کش‌باستینگ) یک دارایی.
 *
 * @param string $path مسیر نسبی
 * @return string|int
 */
function beryon_asset_ver(string $path)
{
    return Assets::version($path);
}

/**
 * URL یک تصویر: نخست کتابخانه رسانه، سپس پوشه آپلود، سپس دارایی قالب.
 *
 * @param string $name نام فایل، مثال: hero.webp
 * @return string
 */
function beryon_img(string $name): string
{
    return Assets::image_url($name);
}

/**
 * تگ <img> بهینه: lazy/async، width/height (بدون CLS)، srcset در صورت امکان.
 *
 * @param string               $name نام تصویر
 * @param array<string,mixed>  $args alt,class,width,height,lazy,loading,decoding,sizes,srcset,style,id,attr
 * @return string
 */
function beryon_image_tag(string $name, array $args = []): string
{
    return Assets::image_tag($name, $args);
}

/**
 * چاپ تگ تصویر (راحتی در Blade).
 *
 * @param string              $name نام تصویر
 * @param array<string,mixed> $args آرگومان‌ها
 * @return void
 */
function beryon_image(string $name, array $args = []): void
{
    echo Assets::image_tag($name, $args); // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی از پیش escape شده است
}

/**
 * URL لوگو (تنظیم قالب → custom-logo → دارایی قالب).
 *
 * @return string
 */
function beryon_logo_url(): string
{
    $custom = get_theme_mod('custom_logo');

    if ($custom) {
        $src = wp_get_attachment_image_url((int) $custom, 'full');
        if ($src) {
            return $src;
        }
    }

    $option = Settings::opt('general.logo_id', 0);

    if ($option) {
        $src = wp_get_attachment_image_url((int) $option, 'full');
        if ($src) {
            return $src;
        }
    }

    return Assets::image_url('logo.png');
}

/* ================================================================== *
 * ۴) آیکون و نویگیشن
 * ================================================================== */

/**
 * خواندن رجیستری آیکون‌های قالب (config/icons.json).
 *
 * هر آیکون = دقیقاً همان <svg> درون‌خطی قالب HTML نهایی (attrs + inner) تا
 * مارک‌آپ خروجی هیچ تغییری نکند.
 *
 * @return array<string,array<string,mixed>>
 */
function beryon_icons(): array
{
    static $icons = null;

    if ($icons !== null) {
        return $icons;
    }

    $file = BERYON_PATH . '/config/icons.json';
    $data = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;

    $icons = is_array($data) && isset($data['icons']) ? (array) $data['icons'] : [];

    /**
     * فیلتر رجیستری آیکون‌ها.
     *
     * @param array $icons آیکون‌ها
     */
    return (array) apply_filters('beryon/icons', $icons);
}

/**
 * چاپ یک آیکون SVG درون‌خطی (دقیقاً مطابق قالب HTML).
 *
 * @param string              $name نام آیکون
 * @param array<string,mixed> $args width, class, style, aria, title
 * @return void
 */
function beryon_svg(string $name, array $args = []): void
{
    $icons = beryon_icons();

    if (! isset($icons[$name])) {
        return;
    }

    $icon   = (array) $icons[$name];
    $attrs  = (array) ($icon['attrs'] ?? []);
    $inner  = (string) ($icon['inner'] ?? '');
    $width  = isset($args['width']) ? (string) $args['width'] : (string) ($icon['width'] ?? '');
    $extra  = is_array($args['attr'] ?? null) ? (array) $args['attr'] : [];

    // ترتیب صفت‌ها: viewBox، fill، stroke، stroke-width، stroke-linecap، stroke-linejoin و سپس width.
    $order = ['viewBox', 'width', 'height', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'class', 'style', 'aria-hidden', 'focusable'];
    $merged = $attrs;

    if ($width !== '') {
        $merged['width'] = $width;
    }

    foreach ($extra as $key => $value) {
        $merged[(string) $key] = (string) $value;
    }

    uksort(
        $merged,
        /** @param string $a */
        static function ($a, $b) use ($order) {
            $ai = array_search($a, $order, true);
            $bi = array_search($b, $order, true);
            $ai = $ai === false ? 99 : $ai;
            $bi = $bi === false ? 99 : $bi;

            return $ai === $bi ? strcmp($a, $b) : $ai <=> $bi;
        }
    );

    $html = '<svg';

    foreach ($merged as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }

        $html .= ' ' . $key . '="' . esc_attr((string) $value) . '"';
    }

    $html .= '>';

    if (! empty($args['title'])) {
        $html .= '<title>' . esc_html((string) $args['title']) . '</title>';
    }

    $html .= $inner . '</svg>';

    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- اجزا از رجیستری کنترل‌شده قالب‌اند
}

/**
 * چاپ یک آیکون از اسپرایت SVG (به‌جای <svg> درون‌خطی).
 *
 * @param string              $name نام آیکون
 * @param array<string,mixed> $args class,size,flip,title,attr
 * @return void
 */
function beryon_icon(string $name, array $args = []): void
{
    $class = trim('icon ' . ($args['class'] ?? ''));
    $size  = isset($args['size']) ? (int) $args['size'] : 0;
    $flip  = ! empty($args['flip']);
    $title = isset($args['title']) ? (string) $args['title'] : '';
    $extra = isset($args['attr']) && is_array($args['attr']) ? $args['attr'] : [];

    $style = [];
    if ($size > 0) {
        $style[] = 'width:' . $size . 'px;height:' . $size . 'px';
    }
    if ($flip) {
        $style[] = 'transform:scaleX(-1)';
    }

    $attributes = [
        'class'       => $class,
        'viewBox'     => '0 0 24 24',
        'aria-hidden' => $title === '' ? 'true' : 'false',
        'focusable'   => 'false',
    ];

    if ($style !== []) {
        $attributes['style'] = implode(';', $style);
    }

    foreach ($extra as $attribute => $value) {
        $attributes[$attribute] = $value;
    }

    $html = '<svg';
    foreach ($attributes as $attribute => $value) {
        $html .= ' ' . $attribute . '="' . esc_attr((string) $value) . '"';
    }
    $html .= '>';

    if ($title !== '') {
        $html .= '<title>' . esc_html($title) . '</title>';
    }

    $html .= '<use href="#i-' . esc_attr($name) . '"></use></svg>';

    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- اجزا escape شده‌اند
}

/**
 * نویگیشن اصلی: wp_nav_menu در صورت وجود، وگرنه منوی پیش‌فرض قالب.
 *
 * @param string $location مکان منو (primary|footer_quick|footer_cats)
 * @param string $active   کلید صفحه فعال برای aria-current
 * @return string
 */
function beryon_nav(string $location = 'primary', string $active = '', string $mode = 'desktop'): string
{
    $locations = (array) get_nav_menu_locations();
    $assigned  = isset($locations[$location]) ? (int) $locations[$location] : 0;

    // اگر کاربر منوی دلخواهی به این مکان نسبت داده باشد، همان منو با Walker قالب
    // رندر می‌شود؛ در غیر این‌صورت منوی پیش‌فرض قالب با مارک‌آپ دقیقاً مطابق
    // قالب HTML نهایی چاپ می‌شود (بدون کلاس‌های اضافی وردپرس).
    if ($assigned > 0 && $mode !== 'mobile') {
        $menu = wp_nav_menu(
            [
                'theme_location' => $location,
                'container'      => false,
                'menu_class'     => $location === 'primary' ? 'nav' : '',
                'fallback_cb'    => false,
                'echo'           => false,
                'depth'          => 2,
                'walker'         => new App\NavWalker(),
                'items_wrap'     => $location === 'primary' ? '%3$s' : '<ul>%3$s</ul>',
                'beryon_mobile'  => false,
            ]
        );

        if (is_string($menu) && trim($menu) !== '') {
            return $menu;
        }
    }

    return App\Nav::render(App\Nav::items($location), $mode, $active);
}

/**
 * کلید «صفحه فعال» برای هدر (home|products|about|blog|catalog|feedback|contact).
 *
 * @return string
 */
function beryon_active_nav(): string
{
    if (is_front_page()) {
        return 'home';
    }

    $post_type = is_singular() ? get_post_type() : '';

    if ($post_type === 'product' || $post_type === 'beryon_product' || is_post_type_archive('beryon_product')
        || (function_exists('is_shop') && is_shop()) || is_tax('product_cat') || is_tax('product_tag')) {
        return 'products';
    }

    if (is_singular('post') || is_home() || is_archive()) {
        return 'blog';
    }

    $template = is_singular('page') ? (string) get_page_template_slug(get_the_ID()) : '';

    // به‌جای match (فقط PHP 8) از زنجیره if استفاده می‌شود تا قالب روی
    // PHP 7.4 هم کار کند.
    if (str_contains($template, 'contact')) {
        return 'contact';
    }

    if (str_contains($template, 'feedback')) {
        return 'feedback';
    }

    if (str_contains($template, 'catalog')) {
        return 'catalog';
    }

    foreach (['about', 'ceo', 'org', 'cert'] as $beryon_hint) {
        if (str_contains($template, $beryon_hint)) {
            return 'about';
        }
    }

    return '';
}

/**
 * لینک یک صفحه قالب بر اساس نام قالب (Template Name) یا اسلاگ.
 *
 * @param string $slug     اسلاگ صفحه، مثال: about-us
 * @param string $template بخش کلیدی نام قالب، مثال: about
 * @return string
 */
function beryon_page_url(string $slug, string $template = ''): string
{
    $page = get_page_by_path($slug);

    if ($page instanceof WP_Post) {
        return (string) get_permalink($page);
    }

    if ($template !== '') {
        $by_template = get_posts(
            [
                'post_type'      => 'page',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
                    [
                        'key'   => '_wp_page_template',
                        'value' => 'views/page-' . $template . '.blade.php',
                    ],
                ],
            ]
        );

        if (! empty($by_template)) {
            return (string) get_permalink((int) $by_template[0]);
        }
    }

    return home_url('/');
}

/**
 * URL صفحه محصولات (ووکامرس: فروشگاه؛ بدون ووکامرس: آرشیو CPT).
 *
 * @return string
 */
function beryon_products_url(): string
{
    if (function_exists('wc_get_page_permalink')) {
        $shop = wc_get_page_permalink('shop');
        if ($shop) {
            return $shop;
        }
    }

    return (string) get_post_type_archive_link('beryon_product');
}

/* ================================================================== *
 * ۵) محتوا
 * ================================================================== */

/**
 * جای‌گذاری توکن‌های محتوایی در متن تنظیمات.
 *
 * توکن‌ها: {count} {year} {brand} {company} {phone_factory} {phone_factory_label}
 * {phone_sales} {phone_sales_label} {phone_admin} {phone_admin_label} {email}
 * {address} {address_full} {site_name} {site_url}
 *
 * @param string $text متن
 * @return string
 */
function beryon_tokens(string $text): string
{
    if ($text === '' || ! str_contains($text, '{')) {
        return $text;
    }

    $general = [
        'phone_factory', 'phone_factory_label', 'phone_sales', 'phone_sales_label',
        'phone_admin', 'phone_admin_label', 'email', 'address', 'address_full',
        'brand_name', 'company_name',
    ];

    $map = [
        '{count}'     => beryon_fa((string) App\Products::total_count()),
        '{year}'      => beryon_year(),
        '{brand}'     => (string) beryon_opt('general.brand_name', 'بریون'),
        '{company}'   => (string) beryon_opt('general.company_name', 'شرکت بریان گوشت آمل'),
        '{site_name}' => (string) get_bloginfo('name'),
        '{site_url}'  => home_url('/'),
    ];

    foreach ($general as $key) {
        $map['{' . $key . '}'] = (string) beryon_opt('general.' . $key, '');
    }

    /**
     * فیلتر جدول توکن‌ها.
     *
     * @param array  $map
     * @param string $text
     */
    $map = (array) apply_filters('beryon/tokens', $map, $text);

    return strtr($text, $map);
}

/**
 * حروف نخستین یک عبارت فارسی (برای آواتار نویسنده).
 *
 * @param string $text عبارت
 * @return string
 */
function beryon_initial(string $text): string
{
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    foreach ($chars as $char) {
        if (preg_match('/[\p{Arabic}\p{Latin}]/u', $char) === 1) {
            return $char;
        }
    }

    return (string) ($chars[0] ?? '');
}

/**
 * محتوای نوشته با فیلتر the_content و قالب‌بندی پاراگراف‌ها.
 *
 * @param int $post_id شناسه نوشته
 * @return string
 */
function beryon_post_content(int $post_id): string
{
    $post = get_post($post_id);

    if (! $post instanceof WP_Post) {
        return '';
    }

    $content = (string) apply_filters('the_content', $post->post_content);

    /**
     * فیلتر HTML بدنه نوشته پیش از چاپ.
     *
     * @param string $content
     * @param int    $post_id
     */
    return (string) apply_filters('beryon/post/content', $content, $post_id);
}

/**
 * داده نوشته‌های صفحه وبلاگ (از حلقه اصلی یا کوئری تازه).
 *
 * @param array<string,mixed> $args آرگومان‌های اختیاری WP_Query
 * @return array<int,array<string,mixed>>
 */
function beryon_query_posts(array $args = []): array
{
    global $wp_query;

    if ($args === [] && $wp_query instanceof WP_Query && ! empty($wp_query->posts)) {
        $source = (array) $wp_query->posts;
    } else {
        $query = new WP_Query(
            array_merge(
                [
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'posts_per_page' => (int) Settings::opt('blog.per_page', 9),
                ],
                $args
            )
        );

        $source = (array) $query->posts;
    }

    $items = [];

    foreach ($source as $item) {
        $card = beryon_post_card($item, 'excerpt');

        if ($card !== []) {
            $items[] = $card;
        }
    }

    return $items;
}

/**
 * صفحه‌بندی سازگار با طراحی قالب (بدون CSS اختصاصی).
 *
 * @return string
 */
function beryon_pagination(): string
{
    global $wp_query;

    if (! $wp_query instanceof WP_Query || (int) $wp_query->max_num_pages < 2) {
        return '';
    }

    $links = (string) paginate_links(
        [
            'total'     => (int) $wp_query->max_num_pages,
            'current'   => max(1, (int) get_query_var('paged')),
            'mid_size'  => 1,
            'end_size'  => 1,
            'type'      => 'list',
            'prev_text' => beryon_svg('chevron-left', ['width' => 16, 'attr' => ['aria-hidden' => 'true']]),
            'next_text' => beryon_svg('chevron-left', ['width' => 16, 'attr' => ['aria-hidden' => 'true', 'style' => 'transform:scaleX(-1)']]),
        ]
    );

    if ($links === '') {
        return '';
    }

    return '<nav class="pagination" aria-label="' . esc_attr__('صفحه‌بندی نوشته‌ها', BERYON_TD) . '">' . $links . '</nav>';
}

/**
 * آدرس صفحه وبلاگ.
 *
 * @return string
 */
function beryon_blog_url(): string
{
    $page_id = (int) get_option('page_for_posts');

    if ($page_id > 0) {
        return (string) get_permalink($page_id);
    }

    return App\Nav::blog_url();
}

/**
 * آدرس endpoint ثبت فرم‌ها (REST).
 *
 * @return string
 */
function beryon_form_url(): string
{
    return (string) rest_url(App\Forms::REST_NS . '/form');
}

/**
 * فهرست استان‌ها برای فرم اعطای نمایندگی.
 *
 * @return array<int,string>
 */
function beryon_provinces(): array
{
    $provinces = App\Settings::opt('forms.provinces', []);

    if (! is_array($provinces) || $provinces === []) {
        $config = App\Settings::json('provinces.json');
        $provinces = is_array($config) && isset($config['provinces']) ? (array) $config['provinces'] : [];
    }

    return array_values(array_filter(array_map('strval', $provinces)));
}

/**
 * ساخت href کارت‌های تماس از توکن‌ها (tel:{phone_sales} ، mailto:{email} و…).
 *
 * @param string $href مقدار خام
 * @return string
 */
function beryon_card_href(string $href): string
{
    $href = trim(beryon_tokens($href));

    if ($href === '' || $href === '#') {
        return '';
    }

    return $href;
}

/**
 * امن‌سازی «&» در متن‌های تنظیمات بدون ازبین‌بردن موجودیت‌های معتبر HTML.
 *
 * وردپرس در خروجی {{ }} فقط < > " ' را فرار می‌کند؛ برای مطابقت با قالب
 * HTML (مثلاً «مدیریت R&amp;D») آمپرسند خام را به &amp; تبدیل می‌کنیم.
 *
 * @param string $text متن
 * @return string
 */
function beryon_amp($text): string
{
    $text = (string) $text;

    if (strpos($text, '&') === false) {
        return $text;
    }

    return (string) preg_replace('/&(?![a-zA-Z][a-zA-Z0-9]{1,31};|#\d{1,7};|#x[0-9a-fA-F]{1,6};)/', '&amp;', $text);
}

/**
 * شماره ترتیبی فارسی (برای aria-label بندانگشتی‌ها).
 *
 * @param int $index اندیس صفرپایه
 * @return string
 */
function beryon_nth_word(int $index): string
{
    $words = ['اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم', 'هفتم', 'هشتم', 'نهم', 'دهم'];

    return $words[$index] ?? beryon_fa($index + 1);
}

/**
 * گالری تصاویر محصول (ووکامرس یا پیوست‌های وردپرس یا تصاویر پوشه قالب).
 *
 * @param int $post_id شناسه محصول
 * @return array<int,string>
 */
function beryon_product_gallery(int $post_id): array
{
    $images = [];

    if (function_exists('wc_get_product')) {
        $product = wc_get_product($post_id);

        if ($product instanceof WC_Product) {
            $main = (int) $product->get_image_id();

            if ($main > 0) {
                $url = (string) wp_get_attachment_url($main);

                if ($url !== '') {
                    $images[] = $url;
                }
            }

            foreach ((array) $product->get_gallery_image_ids() as $attachment) {
                $url = (string) wp_get_attachment_url((int) $attachment);

                if ($url !== '') {
                    $images[] = $url;
                }
            }
        }
    }

    if ($images === [] && has_post_thumbnail($post_id)) {
        $url = (string) get_the_post_thumbnail_url($post_id, 'large');

        if ($url !== '') {
            $images[] = $url;
        }
    }

    if ($images === []) {
        $meta = (string) get_post_meta($post_id, '_beryon_image', true);

        if ($meta !== '') {
            $url = App\Assets::image_url($meta);

            if ($url !== '') {
                $images[] = $url;
            }
        }
    }

    if ($images === []) {
        $terms = get_the_terms($post_id, App\Products::TAX);

        if (is_array($terms) && isset($terms[0])) {
            $reference = App\Products::catalog()['categories'][$terms[0]->slug]['image'] ?? '';

            if (is_string($reference) && $reference !== '') {
                $images[] = App\Assets::image_url($reference);
            }
        }
    }

    // تصاویر تکمیلی قالب (برای داشتن دست‌کم سه بندانگشتی مانند قالب HTML)
    if (count($images) < 3) {
        foreach ((array) apply_filters('beryon/product/gallery_fallback', ['hero.webp', 'blog-2.webp']) as $fallback) {
            $url = App\Assets::image_url((string) $fallback);

            if ($url !== '' && ! in_array($url, $images, true)) {
                $images[] = $url;
            }

            if (count($images) >= 3) {
                break;
            }
        }
    }

    return array_values(array_filter(array_unique($images)));
}

/**
 * برچسب کوتاه محصول برای breadcrumb (متن داخل پرانتز نام محصول).
 *
 * @param string $title عنوان محصول
 * @return string
 */
function beryon_product_crumb(string $title): string
{
    if (preg_match('/[\(\u{066C}\(]([^\)\)]{2,40})[\)\)]/u', $title, $match) === 1) {
        $inner = trim((string) $match[1]);

        if ($inner !== '') {
            return $inner;
        }
    }

    $clean = trim((string) preg_replace('/\s*بریون\s*$/u', '', $title));

    return beryon_excerpt_text($clean, 32);
}

/**
 * آخرین نوشته‌های وبلاگ به‌صورت آرایه آماده چاپ در نماها.
 *
 * @param int    $limit    تعداد
 * @param string $excerpt_key کلید چکیده (home_excerpt برای صفحه اصلی)
 * @return array<int,array<string,mixed>>
 */
function beryon_latest_posts(int $limit = 3, string $excerpt_key = 'home_excerpt'): array
{
    $cache_key = 'beryon_posts_' . $limit . '_' . $excerpt_key;
    $cached    = get_transient($cache_key);

    if (is_array($cached)) {
        return $cached;
    }

    $query = new WP_Query(
        [
            'post_type'              => 'post',
            'post_status'            => 'publish',
            'posts_per_page'         => max(1, $limit),
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'ignore_sticky_posts'    => true,
        ]
    );

    $items = [];

    foreach ($query->posts as $post) {
        $items[] = beryon_post_card($post, $excerpt_key);
    }

    set_transient($cache_key, $items, (int) Settings::opt('performance.cache_ttl', 3600));

    return $items;
}

/**
 * داده آماده چاپ یک نوشته (کارت وبلاگ).
 *
 * @param int|WP_Post $post        نوشته
 * @param string      $excerpt_key کلید چکیده در config/blog.json
 * @return array<string,mixed>
 */
function beryon_post_card($post, string $excerpt_key = 'home_excerpt'): array
{
    $post = get_post($post);

    if (! $post instanceof WP_Post) {
        return [];
    }

    $image = (string) get_post_meta($post->ID, '_beryon_image', true);
    $alt   = (string) get_post_meta($post->ID, '_beryon_image_alt', true);

    if ($image === '' && has_post_thumbnail($post)) {
        $image = (string) wp_get_attachment_url((int) get_post_thumbnail_id($post));
    }

    $excerpt = (string) $post->post_excerpt;

    if ($excerpt === '') {
        $excerpt = beryon_excerpt_text(wp_strip_all_tags((string) $post->post_content), 150);
    }

    return [
        'id'      => $post->ID,
        'title'   => get_the_title($post),
        'link'    => (string) get_permalink($post),
        'image'   => $image,
        'alt'     => $alt !== '' ? $alt : get_the_title($post),
        'date'    => beryon_post_jalali_date($post),
        'excerpt' => $excerpt,
        'content' => (string) $post->post_content,
        'minutes' => beryon_read_minutes((string) $post->post_content),
        'author'  => (string) Settings::opt('blog.author_label', ''),
    ];
}

/**
 * زمان مطالعه (دقیقه) بر اساس سرعت خواندن فارسی.
 *
 * @param string $content محتوا
 * @return int
 */
function beryon_read_minutes(string $content): int
{
    $text  = wp_strip_all_tags($content);
    $words = preg_split('/\s+/u', $text) ?: [];
    $wpm   = max(60, (int) Settings::opt('blog.words_per_minute', 200));

    return max(1, (int) ceil(count(array_filter($words)) / $wpm));
}

/**
 * رندر یک نمای Blade به‌صورت رشته (برای استفاده در هوک‌های ووکامرس).
 *
 * @param string              $view نام نما، مثال: components.product-card
 * @param array<string,mixed> $data داده‌ها
 * @return string
 */
function beryon_view(string $view, array $data = []): string
{
    return \App\Acorn::view($view, $data);
}

/**
 * خواندن امن یک مقدار آرایه‌ای با پیش‌فرض (برای Blade).
 *
 * @param array<string,mixed> $array   آرایه
 * @param string              $key     کلید
 * @param mixed               $default پیش‌فرض
 * @return mixed
 */
function beryon_arr(array $array, string $key, $default = '')
{
    return array_key_exists($key, $array) && $array[$key] !== null ? $array[$key] : $default;
}

/**
 * چکیده متنی امن (بدون HTML) برای meta description.
 *
 * @param string $text   متن
 * @param int    $length حداکثر نویسه
 * @return string
 */
function beryon_excerpt_text(string $text, int $length = 160): string
{
    $text = wp_strip_all_tags(str_replace(["\r", "\n", "\t"], ' ', $text));
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    $text = trim($text);

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    $cut  = mb_substr($text, 0, $length);
    $last = mb_strrpos($cut, ' ');

    return rtrim($last !== false ? mb_substr($cut, 0, $last) : $cut, ' ،,.؛;:') . '…';
}

/**
 * مسیر (Breadcrumb) صفحه جاری به‌صورت آرایه [name, url].
 *
 * @return array<int,array{name:string,url:string}>
 */
function beryon_breadcrumb(): array
{
    $items  = [['name' => __('خانه', BERYON_TD), 'url' => home_url('/')]];
    $labels = [
        'products' => __('محصولات', BERYON_TD),
        'about'    => __('درباره ما', BERYON_TD),
        'blog'     => __('وبلاگ', BERYON_TD),
    ];

    $post_type = App\Products::post_type();
    $taxonomy  = App\Products::taxonomy();

    if (is_singular($post_type)) {
        $items[] = ['name' => $labels['products'], 'url' => beryon_products_url()];
        $terms   = get_the_terms(get_the_ID(), $taxonomy);
        if (is_array($terms) && isset($terms[0])) {
            $items[] = ['name' => $terms[0]->name, 'url' => (string) get_term_link($terms[0])];
        }
        $items[] = ['name' => get_the_title(), 'url' => ''];
    } elseif (is_tax($taxonomy) || is_post_type_archive($post_type)) {
        $items[] = ['name' => $labels['products'], 'url' => beryon_products_url()];
        if (is_tax()) {
            $term    = get_queried_object();
            $items[] = ['name' => $term instanceof WP_Term ? $term->name : '', 'url' => ''];
        }
    } elseif (is_singular('post')) {
        $items[] = ['name' => $labels['blog'], 'url' => get_permalink((int) get_option('page_for_posts')) ?: home_url('/')];
        $items[] = ['name' => get_the_title(), 'url' => ''];
    } elseif (is_home() || is_archive()) {
        $items[] = ['name' => is_home() ? $labels['blog'] : get_the_archive_title(), 'url' => ''];
    } elseif (is_search()) {
        $items[] = ['name' => sprintf(__('نتایج جست‌وجو: %s', BERYON_TD), get_search_query()), 'url' => ''];
    } elseif (is_404()) {
        $items[] = ['name' => __('صفحه یافت نشد', BERYON_TD), 'url' => ''];
    } elseif (is_singular('page')) {
        $ancestors = array_reverse(get_post_ancestors(get_the_ID()));
        foreach ($ancestors as $ancestor) {
            $items[] = ['name' => get_the_title($ancestor), 'url' => (string) get_permalink($ancestor)];
        }
        $items[] = ['name' => get_the_title(), 'url' => ''];
    }

    /**
     * فیلتر مسیر صفحه.
     *
     * @param array $items
     */
    return (array) apply_filters('beryon/breadcrumb', $items);
}

/**
 * چاپ بخش مسیر (Breadcrumb) با مارک‌آپ قالب.
 *
 * @return void
 */
function beryon_breadcrumb_html(): void
{
    $items = beryon_breadcrumb();
    $last  = array_key_last($items);

    echo '<nav class="breadcrumb" aria-label="' . esc_attr__('مسیر صفحه', BERYON_TD) . '">';

    foreach ($items as $index => $item) {
        if ($index > 0) {
            beryon_icon('chevron-left', ['class' => 'bc-sep']);
        }

        if ($item['url'] !== '' && $index !== $last) {
            echo '<a href="' . esc_url($item['url']) . '">' . esc_html($item['name']) . '</a>';
        } else {
            echo '<span>' . esc_html($item['name']) . '</span>';
        }
    }

    echo '</nav>';
}

/**
 * سال شمسی جاری (برای کپی‌رایت فوتر).
 *
 * @return string
 */
function beryon_year(): string
{
    return beryon_fa((string) Jalali::currentYear());
}
