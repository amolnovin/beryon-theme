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
 * مقدار یک تنظیم بدون جایگزینی «خالی → پیش‌فرض» (فیلدی که مدیر می‌تواند خالی کند).
 *
 * @param string $key     نقطه‌مسیر
 * @param mixed  $default مقدار جایگزین برای کلید ناموجود
 * @return mixed
 */
function beryon_opt_raw(string $key, $default = '')
{
    return Settings::raw($key, $default);
}

/**
 * شماره تماس بدون خط تیره (۰۱۱-۴۴۴۴۸۰۷۱ → ۰۱۱۴۴۴۴۸۰۷۱) برای متن‌هایی که از تنظیمات نمی‌آیند.
 *
 * @param string $text متن
 * @return string
 */
function beryon_phone_plain(string $text): string
{
    return Settings::plain_phone($text);
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

    // ردیف‌های خالی (حذف‌شده در UI) کنار گذاشته می‌شوند. ردیف می‌تواند آرایه
    // (تکرارشونده) یا رشته ساده (فهرست‌های «هر خط یک مورد») باشد؛ پیش‌تر رشته‌ها
    // هم دور ریخته می‌شدند و فهرست‌هایی مثل «انواع فروش» همیشه خالی بود.
    $rows = array_values(
        array_filter(
            $rows,
            /** @param mixed $row */
            static function ($row): bool {
                if (is_array($row)) {
                    return array_filter($row, static fn ($v) => $v !== '' && $v !== null) !== [];
                }

                return is_scalar($row) && trim((string) $row) !== '';
            }
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
 * شناسه پیوست لوگوی انتخاب‌شده: «هویت سایت» سفارشی‌ساز (custom_logo) و سپس
 * تنظیم قالب (general.logo_id). صفر یعنی لوگوی پیش‌فرض قالب استفاده شود.
 *
 * @return int
 */
function beryon_logo_id(): int
{
    $candidates = [
        (int) get_theme_mod('custom_logo'),
        (int) Settings::opt('general.logo_id', 0),
    ];

    foreach ($candidates as $id) {
        if ($id > 0 && wp_attachment_is_image($id) && wp_get_attachment_image_url($id, 'full')) {
            return $id;
        }
    }

    return 0;
}

/**
 * URL لوگو (custom-logo → تنظیم قالب → فایل لوگوی قالب).
 *
 * @return string
 */
function beryon_logo_url(): string
{
    $id = beryon_logo_id();

    if ($id > 0) {
        return (string) wp_get_attachment_image_url($id, 'full');
    }

    return Assets::image_url((string) Settings::opt('general.logo_file', 'logo.png'));
}

/**
 * تگ <img> لوگو برای سربرگ، کشوی موبایل، پابرگ و جلد کاتالوگ.
 *
 * ابعاد چاپ‌شده همیشه با نسبت تصویر واقعی محاسبه می‌شود — چه لوگو از کتابخانه
 * رسانه انتخاب شده باشد، چه فایل پیش‌فرض قالب (general.logo_file) باشد:
 *  - height داده شده  ← width از روی نسبت واقعی (مقدار width ورودی فقط وقتی به‌کار
 *    می‌رود که ابعاد واقعی تصویر معلوم نباشد)؛
 *  - فقط width         ← height از روی نسبت واقعی؛
 *  - هیچ‌کدام          ← ابعاد واقعی تصویر.
 *
 * فراخوان باید «ارتفاع نمایش» همان جایگاه را بدهد (سربرگ ۵۶، کشو ۴۶، پابرگ ۵۴، جلد
 * کاتالوگ ۸۸ پیکسل؛ مطابق beryon.css). بدون آن، تگ با ابعاد کامل فایل (مثلاً ۵۰۴×۳۸۰)
 * چاپ می‌شد و در جایگاه‌هایی که CSS فقط height می‌دهد لوگو به پهنای ردیف کشیده می‌شد.
 *
 * @param array<string,mixed> $args alt,width,height,lazy,class,…
 * @return string
 */
function beryon_logo_tag(array $args = []): string
{
    $id  = beryon_logo_id();
    $src = $id > 0 ? wp_get_attachment_image_src($id, 'full') : false;

    if (is_array($src) && ! empty($src[0])) {
        $name        = (string) $src[0];
        $real_width  = (int) ($src[1] ?? 0);
        $real_height = (int) ($src[2] ?? 0);

        $args['attachment'] = $id;
    } else {
        $name = (string) Settings::opt('general.logo_file', 'logo.png');
        $real = Assets::dimensions($name, Assets::is_absolute_url(trim($name)) ? 0 : Assets::attachment_by_name($name));

        $real_width  = (int) $real[0];
        $real_height = (int) $real[1];
    }

    if ($real_width > 0 && $real_height > 0) {
        if (! empty($args['height'])) {
            $args['height'] = (int) $args['height'];
            $args['width']  = max(1, (int) round($args['height'] * $real_width / $real_height));
        } elseif (! empty($args['width'])) {
            $args['width']  = (int) $args['width'];
            $args['height'] = max(1, (int) round($args['width'] * $real_height / $real_width));
        } else {
            $args['width']  = $real_width;
            $args['height'] = $real_height;
        }
    }

    return Assets::image_tag($name, $args);
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

    $file = BERYON_THEME_PATH . '/config/icons.json';
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
 * همان beryon_svg ولی به‌صورت رشته (برای جاهایی که HTML باید برگردانده شود).
 *
 * @param string              $name نام آیکون
 * @param array<string,mixed> $args گزینه‌ها
 * @return string
 */
function beryon_svg_html(string $name, array $args = []): string
{
    ob_start();
    beryon_svg($name, $args);

    return (string) ob_get_clean();
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
 * نویگیشن قالب: منوی وردپرس در صورت وجود، وگرنه منوی پیش‌فرض قالب.
 *
 * هر سه حالت (سربرگ دسکتاپ، کشوی موبایل، پانوشت) از همان منویی ساخته می‌شوند که
 * در «نمایش ← فهرست‌ها» به مکان موردنظر نسبت داده شده است؛ خروجی فقط خودِ آیتم‌هاست
 * (بدون <ul>) چون ظرف را خودِ نما چاپ می‌کند.
 *
 * @param string $location مکان منو (primary|footer_quick|footer_cats)
 * @param string $active   کلید صفحه فعال برای برجسته‌سازی
 * @param string $mode     desktop|mobile|footer
 * @return string
 */
function beryon_nav(string $location = 'primary', string $active = '', string $mode = 'desktop'): string
{
    $locations = (array) get_nav_menu_locations();
    $assigned  = isset($locations[$location]) ? (int) $locations[$location] : 0;

    // اگر کاربر منویی به این مکان نسبت داده باشد، همان منو با Walker قالب رندر
    // می‌شود؛ در غیر این‌صورت (یا اگر منو خالی باشد) منوی پیش‌فرض قالب با مارک‌آپ
    // دقیقاً مطابق قالب HTML نهایی چاپ می‌شود.
    if ($assigned > 0) {
        $menu = wp_nav_menu(
            [
                'theme_location'    => $location,
                'container'         => false,
                'fallback_cb'       => false,
                'echo'              => false,
                'depth'             => $mode === 'footer' ? 1 : 2,
                'walker'            => new App\NavWalker(),
                'items_wrap'        => '%3$s',
                'beryon_mode'       => $mode,
                'beryon_active_url' => $mode === 'footer' ? '' : App\Nav::section_url($active),
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

    // برگه‌ای که نمایش از روی نامکش انتخاب می‌شود (مثل contact-us ← page-contact-us)
    // متای قالب ندارد؛ بدون این، «تماس با ما» روی صفحه خودش برجسته نمی‌شد.
    if ($template === '' && is_singular('page')) {
        $beryon_slug = (string) get_post_field('post_name', get_queried_object_id());

        if ($beryon_slug !== '' && is_readable(BERYON_THEME_PATH . '/resources/views/page-' . $beryon_slug . '.blade.php')) {
            $template = 'page-' . $beryon_slug;
        }
    }

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
 * نامک‌های جایگزین هر برگه قالب (کلید = بخش کلیدی نام قالب).
 *
 * سایت‌هایی که از پیش محتوا دارند برگه‌ها را با نامک دیگری ساخته‌اند (مثلاً
 * ‎/organizational-chart/‎ بدون والد «about-us»، یا «فرم-درخواست-نمایندگی»). همین
 * فهرست هم برای پیدا کردن نشانی برگه و هم برای انتخاب نمای آن به‌کار می‌رود.
 *
 * @return array<string,array<int,string>>
 */
function beryon_page_slugs(): array
{
    $slugs = [
        'about'        => ['about-us', 'about'],
        'ceo'          => ['about-us/introduction-of-ceo', 'introduction-of-ceo', 'about-us/ceo', 'ceo'],
        'org-chart'    => ['about-us/organizational-chart', 'organizational-chart', 'about-us/org-chart', 'org-chart'],
        'certificates' => ['about-us/certificates', 'certificates'],
        'contact-us'   => ['contact-us', 'contact'],
        'catalog'      => ['catalog'],
        'feedback'     => ['feedback'],
        'agency'       => ['agency', 'فرم-درخواست-نمایندگی', 'درخواست-نمایندگی', 'agency-request'],
    ];

    /**
     * فیلتر نامک‌های جایگزین برگه‌های قالب.
     *
     * @param array<string,array<int,string>> $slugs کلید نما (page-<کلید>) → مسیرها
     */
    return (array) apply_filters('beryon/page_slugs', $slugs);
}

/**
 * لینک یک صفحه قالب بر اساس نام قالب (Template Name) یا اسلاگ.
 *
 * ترتیب: برگه «درخواست نمایندگی» افزونه نمایندگان (برای agency) ← مسیر داده‌شده ←
 * برگه‌ای که قالب برگه را دارد ← نامک‌های جایگزین (beryon_page_slugs) ← صفحه اصلی.
 * نتیجه در طول همان درخواست نگه داشته می‌شود (سربرگ، منو و پابرگ بارها صدا می‌زنند).
 *
 * @param string $slug     اسلاگ صفحه، مثال: about-us
 * @param string $template بخش کلیدی نام قالب، مثال: about
 * @return string
 */
function beryon_page_url(string $slug, string $template = ''): string
{
    static $memo = [];

    $key = $slug . '|' . $template;

    if (isset($memo[$key])) {
        return $memo[$key];
    }

    $url = '';

    if ($template === 'agency') {
        $url = App\Agents::page_url('application');
    }

    if ($url === '') {
        $page = get_page_by_path($slug);

        if ($page instanceof WP_Post && $page->post_status === 'publish') {
            $url = (string) get_permalink($page);
        }
    }

    // کلید نما: «org» و «contact» در فراخوانی‌های قدیمی همان page-org-chart و page-contact-us هستند.
    $view = ['org' => 'org-chart', 'contact' => 'contact-us'][$template] ?? $template;

    if ($url === '' && $template !== '') {
        $by_template = get_posts(
            [
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery
                    [
                        'key'     => '_wp_page_template',
                        'value'   => array_unique(['views/page-' . $view . '.blade.php', 'views/page-' . $template . '.blade.php']),
                        'compare' => 'IN',
                    ],
                ],
            ]
        );

        if (! empty($by_template)) {
            $url = (string) get_permalink((int) $by_template[0]);
        }
    }

    if ($url === '' && $template !== '') {
        foreach ((array) (beryon_page_slugs()[$view] ?? []) as $alias) {
            if ((string) $alias === $slug) {
                continue;
            }

            $page = get_page_by_path((string) $alias);

            if ($page instanceof WP_Post && $page->post_status === 'publish') {
                $url = (string) get_permalink($page);

                break;
            }
        }
    }

    /**
     * فیلتر نشانی برگه قالب.
     *
     * @param string $url      نشانی پیداشده (یا صفحه اصلی)
     * @param string $slug     مسیر درخواستی
     * @param string $template کلید قالب
     */
    $memo[$key] = (string) apply_filters('beryon/page_url', $url !== '' ? $url : home_url('/'), $slug, $template);

    return $memo[$key];
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
 * توکن‌ها: {count} {year} (سال شمسی) {gyear} (سال میلادی) {brand} {company} {phone_factory} {phone_factory_label}
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
        '{gyear}'     => beryon_fa((string) wp_date('Y')),
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

    // محتوای رمزدار: تا وارد نشدن رمز فقط فرم رمز وردپرس نمایش داده می‌شود
    // (خواندن مستقیم post_content این محافظت را دور می‌زد).
    if (post_password_required($post)) {
        return (string) get_the_password_form($post);
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
            // beryon_svg خروجی را چاپ می‌کند و چیزی برنمی‌گرداند؛ پیش‌تر آیکون‌ها
            // بیرون از صفحه‌بندی چاپ می‌شدند و پیوندهای قبلی/بعدی خالی می‌ماندند.
            'prev_text' => beryon_svg_html('chevron-left', ['width' => 16, 'attr' => ['aria-hidden' => 'true']])
                . '<span class="sr-only">' . esc_html__('صفحه قبلی', BERYON_TD) . '</span>',
            'next_text' => beryon_svg_html('chevron-left', ['width' => 16, 'attr' => ['aria-hidden' => 'true', 'style' => 'transform:scaleX(-1)']])
                . '<span class="sr-only">' . esc_html__('صفحه بعدی', BERYON_TD) . '</span>',
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
 * نشانیِ مجازِ یک کد جاسازی نقشه (کد ‎<iframe>‎ یا خودِ نشانی).
 *
 * فقط نشانی https روی دامنه‌های همان سرویس پذیرفته می‌شود؛ هر چیز دیگری رشته خالی
 * برمی‌گرداند تا هیچ iframe دلخواهی از راه تنظیمات وارد صفحه نشود.
 *
 * @param string $code    کد ‎<iframe …>‎ یا نشانی
 * @param string $service google | neshan
 * @return string
 */
function beryon_embed_src(string $code, string $service): string
{
    $code = trim($code);

    if ($code === '') {
        return '';
    }

    if (stripos($code, '<iframe') !== false) {
        if (preg_match('/<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/is', $code, $match) !== 1) {
            return '';
        }

        $code = $match[2];
    }

    $url   = trim(html_entity_decode($code, ENT_QUOTES, 'UTF-8'));
    $parts = wp_parse_url($url);

    if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
        return '';
    }

    $host = strtolower((string) $parts['host']);
    $path = (string) ($parts['path'] ?? '');

    $allowed = [
        'google' => ['www.google.com', 'google.com', 'maps.google.com'],
        'neshan' => ['neshan.org', 'www.neshan.org'],
    ];

    /**
     * فیلتر دامنه‌های مجاز کد جاسازی نقشه.
     *
     * @param array<string,array<int,string>> $allowed سرویس → دامنه‌ها
     */
    $allowed = (array) apply_filters('beryon/map/embed_hosts', $allowed);

    if (! isset($allowed[$service]) || ! in_array($host, (array) $allowed[$service], true) || strpos($path, '/maps') !== 0) {
        return '';
    }

    return (string) esc_url_raw($url, ['https']);
}

/**
 * نقشه کارت «موقعیت کارخانه» در برگه تماس (تنظیم contact.map_provider).
 *
 * google : کد جاسازی ذخیره‌شده، وگرنه نقشه ساخته‌شده از مختصات (بدون کلید API).
 * neshan : فقط کد جاسازی‌ای که از neshan.org گرفته شده است.
 * اگر نشانیِ معتبری به‌دست نیاید، کارت ساده (بدون نقشه) نمایش داده می‌شود.
 *
 * @return array{provider:string,src:string,height:int}
 */
function beryon_contact_map(): array
{
    $provider = (string) beryon_opt('contact.map_provider', 'google');
    $provider = in_array($provider, ['google', 'neshan'], true) ? $provider : 'none';
    $src      = '';

    if ($provider === 'google') {
        $src = beryon_embed_src((string) beryon_opt_raw('contact.map_google_embed', ''), 'google');

        if ($src === '') {
            $lat  = trim((string) beryon_opt('contact.map_lat', ''));
            $lng  = trim((string) beryon_opt('contact.map_lng', ''));
            $zoom = max(3, min(20, (int) beryon_opt('contact.map_zoom', 15)));

            if (preg_match('/^-?\d{1,2}(\.\d+)?$/', $lat) === 1 && preg_match('/^-?\d{1,3}(\.\d+)?$/', $lng) === 1
                && abs((float) $lat) <= 90 && abs((float) $lng) <= 180) {
                // نشانی جاسازیِ بدون کلید API گوگل‌مپ (همان چیزی که «Embed a map» می‌سازد، با مختصات).
                $src = 'https://maps.google.com/maps?q=' . $lat . ',' . $lng . '&z=' . $zoom . '&hl=fa&output=embed';
            }
        }
    } elseif ($provider === 'neshan') {
        $src = beryon_embed_src((string) beryon_opt_raw('contact.map_neshan_embed', ''), 'neshan');
    }

    $map = [
        'provider' => $src !== '' ? $provider : 'none',
        'src'      => $src,
        'height'   => max(200, min(640, (int) beryon_opt('contact.map_height', 320))),
    ];

    /**
     * فیلتر نقشه برگه تماس.
     *
     * @param array $map provider | src | height
     */
    return (array) apply_filters('beryon/contact/map', $map);
}

/**
 * آیا شورت‌کدِ نوشته‌شده در یک متن قابل اجراست (افزونه‌اش فعال است)؟
 *
 * @param string $text متنی که با یک شورت‌کد شروع می‌شود، مثل ‎[dflip id="1"][/dflip]‎
 * @return bool
 */
function beryon_shortcode_ready(string $text): bool
{
    return preg_match('/\[([A-Za-z][A-Za-z0-9_\-]*)/', $text, $match) === 1 && shortcode_exists($match[1]);
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
    // پرانتز لاتین یا تمام‌عرض (U+FF08/U+FF09). الگوی پیشین «\u{…}» داشت که در
    // PCRE نامعتبر است و در PHP 8 هشدار «Compilation failed» می‌داد.
    if (preg_match('/[(\x{FF08}]([^)\x{FF09}]{2,40})[)\x{FF09}]/u', $title, $match) === 1) {
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
    // نسخه قالب در کلید: ساختار کارت‌های کش‌شده بین نسخه‌ها عوض می‌شود. این کش با
    // ذخیره/حذف هر نوشته و تغییر تصویر شاخص در Products::flush_cache() پاک می‌شود.
    // زبان جاری هم جزو کلید است: روی سایت چندزبانه نوشته‌های هر زبان جدا کش می‌شود.
    $cache_key = 'beryon_posts_' . $limit . '_' . $excerpt_key . '_' . BERYON_THEME_VERSION . (App\Lang::cache_key() !== '' ? '_' . App\Lang::cache_key() : '');
    $cached    = get_transient($cache_key);

    if (is_array($cached)) {
        return $cached;
    }

    // فقط نوشته‌های زبان جاری (Polylang خودش فیلتر می‌کند؛ در نبود آن App\Lang).
    $query = new WP_Query(
        App\Lang::query_args(
            [
                'post_type'              => 'post',
                'post_status'            => 'publish',
                'posts_per_page'         => max(1, $limit),
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
                'ignore_sticky_posts'    => true,
            ]
        )
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

    // ۱) تصویر شاخص نوشته (انتخاب مدیر سایت) مقدم است؛ ۲) در نبود آن، نام فایل
    // تصویر نمونه در پوشه قالب (متای درون‌ریز). مقدار «image» یا نشانی کامل است
    // یا نام فایل؛ نماها آن را با beryon_img()/beryon_image_tag() چاپ می‌کنند.
    $image    = '';
    $image_id = 0;
    $alt      = (string) get_post_meta($post->ID, '_beryon_image_alt', true);

    if (has_post_thumbnail($post)) {
        $thumbnail = (int) get_post_thumbnail_id($post);
        $image     = (string) wp_get_attachment_image_url($thumbnail, 'large');

        if ($image === '') {
            $image = (string) wp_get_attachment_url($thumbnail);
        }

        if ($image !== '') {
            $image_id      = $thumbnail;
            $thumbnail_alt = trim((string) get_post_meta($thumbnail, '_wp_attachment_image_alt', true));

            if ($thumbnail_alt !== '') {
                $alt = $thumbnail_alt;
            }
        }
    }

    if ($image === '') {
        $image = (string) get_post_meta($post->ID, '_beryon_image', true);
    }

    // نوشته رمزدار: متن آن نباید در کارت‌ها، جست‌وجو یا سربرگ صفحه دیده شود.
    $locked  = post_password_required($post);
    $excerpt = $locked ? '' : beryon_plain_excerpt((string) $post->post_excerpt);

    if ($excerpt === '' && ! $locked) {
        $excerpt = beryon_excerpt_text(wp_strip_all_tags((string) $post->post_content), 150);
    }

    return [
        'id'       => $post->ID,
        'title'    => get_the_title($post),
        'link'     => (string) get_permalink($post),
        'image'    => $image,
        'image_id' => $image_id,
        'alt'      => $alt !== '' ? $alt : get_the_title($post),
        'date'     => beryon_post_jalali_date($post),
        'excerpt'  => $excerpt,
        'content'  => $locked ? '' : (string) $post->post_content,
        'minutes'  => beryon_read_minutes((string) $post->post_content),
        'author'   => (string) Settings::opt('blog.author_label', ''),
    ];
}

/**
 * چکیده دستیِ نوشته به‌صورت متن ساده.
 *
 * چکیده‌ای که مدیر نوشته همان‌طور که هست چاپ می‌شود؛ ولی اگر در فیلد چکیده HTML
 * گذاشته شده باشد (مثلاً بخش آغازین مقاله با دکمه و نشان)، تگ‌ها به‌شکل متن روی
 * کارت دیده می‌شدند. در این حالت متن نخستین بند (‎<p>‎) برداشته و کوتاه می‌شود.
 *
 * @param string $excerpt مقدار post_excerpt
 * @param int    $length  حداکثر نویسه برای چکیده‌ای که HTML داشته
 * @return string
 */
function beryon_plain_excerpt(string $excerpt, int $length = 220): string
{
    $excerpt = trim($excerpt);

    if ($excerpt === '' || strpos($excerpt, '<') === false) {
        return $excerpt;
    }

    $text = '';

    if (preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $excerpt, $paragraphs)) {
        foreach ($paragraphs[1] as $paragraph) {
            $candidate = trim(html_entity_decode(wp_strip_all_tags($paragraph), ENT_QUOTES, 'UTF-8'));

            if (mb_strlen($candidate) >= 40) {
                $text = $candidate;

                break;
            }
        }
    }

    if ($text === '') {
        // تگ‌ها با فاصله جایگزین می‌شوند تا متن دو عنصر کنار هم به هم نچسبد.
        $text = html_entity_decode(wp_strip_all_tags((string) preg_replace('/<[^>]+>/', ' ', $excerpt)), ENT_QUOTES, 'UTF-8');
    }

    return beryon_excerpt_text($text, $length);
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
