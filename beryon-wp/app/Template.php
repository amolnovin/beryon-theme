<?php

/**
 * انتخاب و رندر قالب — قالب بریون
 * ------------------------------------------------------------------
 * سلسله‌مراتب قالب وردپرس را به نام نماهای Blade نگاشت می‌کند و داده مشترک
 * (تنظیمات، منوها، محصولات، سئو) را برای نما فراهم می‌آورد.
 * اگر Acorn نصب نباشد، MiniBlade داخلی قالب رندر را برعهده می‌گیرد.
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * کلاس انتخاب/رندر قالب.
 */
final class Template
{
    /**
     * آیا صفحه اضطراری یک‌بار ساخته شده است؟ (جلوگیری از فراخوانی دوباره wp_head)
     *
     * @var bool
     */
    private static $emergency = false;

    /**
     * نام نمای انتخاب‌شده.
     *
     * @var string
     */
    private static $resolved = '';

    /**
     * رندر کامل صفحه.
     *
     * @return string
     */
    public static function render(): string
    {
        $view = 'index';

        try {
            $view = self::resolve();

            if ($view === '') {
                $view = 'index';
            }

            /**
             * فیلتر نام نمای نهایی.
             *
             * @param string $view نام نما
             */
            $view = (string) \apply_filters('beryon/template', $view);

            $html = Acorn::page($view, self::data($view));

            if (trim($html) === '') {
                throw new \RuntimeException('خروجی نما «' . $view . '» خالی بود.');
            }

            return $html;
        } catch (\Throwable $error) {
            error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions
                sprintf('[beryon] رندر نما «%s» ناموفق: %s (%s:%d)', $view, $error->getMessage(), $error->getFile(), $error->getLine())
            );

            return self::emergency($error, $view);
        }
    }

    /**
     * صفحه اضطراری: به‌جای صفحه سفید، یک سند معتبر HTML با پیام خوانا.
     *
     * جزئیات فنی فقط برای مدیر سایت یا وقتی WP_DEBUG روشن است نمایش داده
     * می‌شود؛ برای بازدیدکننده پیام کوتاه و محترمانه است. هیچ‌وقت رشته خالی
     * برنمی‌گردد.
     *
     * @param \Throwable $error خطا
     * @param string     $view  نمای درخواستی
     * @return string
     */
    private static function emergency(\Throwable $error, string $view): string
    {
        $debug = (defined('WP_DEBUG') && WP_DEBUG)
            || (function_exists('current_user_can') && current_user_can('manage_options'));

        if (! headers_sent() && function_exists('status_header')) {
            \status_header(500);
            \nocache_headers();
        }

        $title = function_exists('get_bloginfo') ? (string) \get_bloginfo('name') : 'Beryon';

        $head = '';
        $foot = '';

        if (! self::$emergency) {
            self::$emergency = true;

            try {
                if (function_exists('wp_head')) {
                    ob_start();
                    \wp_head();
                    $head = (string) ob_get_clean();
                }
            } catch (\Throwable $ignored) {
                unset($ignored);
            }

            try {
                if (function_exists('wp_footer')) {
                    ob_start();
                    \wp_footer();
                    $foot = (string) ob_get_clean();
                }
            } catch (\Throwable $ignored) {
                unset($ignored);
            }
        }

        $details = '';

        if ($debug) {
            $details = '<details open style="margin-top:18px"><summary style="cursor:pointer;font-weight:700">'
                . 'جزئیات فنی (فقط برای مدیر)</summary>'
                . '<pre dir="ltr" style="text-align:left;direction:ltr;background:#fff;border:1px solid #e6d9d2;'
                . 'border-radius:10px;padding:12px;overflow:auto;font:12px/1.8 monospace;white-space:pre-wrap">'
                . esc_html($error->getMessage() . "\n" . $error->getFile() . ':' . $error->getLine()
                    . "\nview: " . $view . "\n\n" . $error->getTraceAsString())
                . '</pre></details>';
        }

        $css = 'body{margin:0;background:#fffaf7;color:#181a1e;font-family:Vazirmatn,Tahoma,sans-serif;'
            . 'direction:rtl;text-align:center}.bx{max-width:640px;margin:12vh auto;padding:28px 24px;'
            . 'background:#fff;border:1px solid #f1e3dc;border-radius:20px;box-shadow:0 18px 44px rgba(24,26,30,.08)}'
            . 'h1{font-size:22px;margin:0 0 10px;color:#a4121b}p{margin:0 0 8px;line-height:2;color:#3f424a;font-size:15px}'
            . 'code{background:#fdeceb;color:#a4121b;padding:2px 8px;border-radius:8px;font-size:13px}'
            . 'a.btn{display:inline-block;margin-top:16px;padding:11px 22px;background:#d5232c;color:#fff;'
            . 'border-radius:12px;text-decoration:none;font-weight:700}';

        return '<!DOCTYPE html>' . "\n"
            . '<html lang="fa" dir="rtl">' . "\n"
            . '<head>' . "\n"
            . '<meta charset="utf-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<meta name="robots" content="noindex,nofollow">' . "\n"
            . '<title>' . esc_html($title) . '</title>' . "\n"
            . '<style>' . $css . '</style>' . "\n"
            . $head
            . '</head>' . "\n"
            . '<body class="beryon-error">' . "\n"
            . '<main class="bx">' . "\n"
            . '<h1>' . esc_html__('صفحه قابل نمایش نیست', 'beryon') . '</h1>' . "\n"
            . '<p>' . esc_html__('رندر نمای قالب با خطا مواجه شد. اگر مدیر سایت هستید، جزئیات فنی در ادامه و در فایل error_log سرور ثبت شده است.', 'beryon') . '</p>' . "\n"
            . '<p>' . esc_html__('نمای درخواستی:', 'beryon') . ' <code>' . esc_html($view) . '</code></p>' . "\n"
            . '<a class="btn" href="' . esc_url(function_exists('home_url') ? \home_url('/') : '/') . '">'
            . esc_html__('بازگشت به صفحه اصلی', 'beryon') . '</a>' . "\n"
            . $details . "\n"
            . '</main>' . "\n"
            . $foot
            . '</body>' . "\n"
            . '</html>' . "\n";

    }

    /**
     * تعیین نام نما بر اساس سلسله‌مراتب وردپرس.
     *
     * @return string
     */
    public static function resolve(): string
    {
        if (self::$resolved !== '') {
            return self::$resolved;
        }

        $candidates = self::candidates();

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate === '' || $candidate === 'views/') {
                continue;
            }

            $name = str_replace('.blade', '', $candidate);
            $name = str_replace('views/', '', $name);

            if (Acorn::available()) {
                $found = \locate_template(["views/{$name}.blade.php", "resources/views/{$name}.blade.php"]);

                if ($found !== '') {
                    self::$resolved = $name;

                    return $name;
                }

                continue;
            }

            if (MiniBlade::find($name) !== '') {
                self::$resolved = $name;

                return $name;
            }
        }

        self::$resolved = 'index';

        return 'index';
    }

    /**
     * آیا درخواست فعلی یکی از مسیرهای ووکامرس با نمای Blade است؟
     *
     * @return bool
     */
    public static function is_woo_route(): bool
    {
        $woo_page = \function_exists('is_cart') && (\is_cart() || \is_checkout() || \is_account_page());

        if ($woo_page) {
            return true;
        }

        if (\function_exists('is_product') && \is_product()) {
            return true;
        }

        if (\function_exists('is_shop') && \is_shop()) {
            return true;
        }

        if (\function_exists('is_product_taxonomy') && \is_product_taxonomy()) {
            return true;
        }

        return \is_singular(['product', Products::CPT])
            || \is_post_type_archive(['product', Products::CPT])
            || \is_tax(Products::TAX);
    }

    /**
     * فهرست نامزدها بر اساس سلسله‌مراتب وردپرس.
     *
     * @return array<int,string>
     */
    public static function candidates(): array
    {
        $candidates = [];

        // قالب صفحه دلخواه (Template Name)
        if (\is_singular()) {
            $template = (string) \get_page_template_slug();

            if ($template !== '' && $template !== 'default') {
                $candidates[] = basename($template, '.blade.php');
                $candidates[] = str_replace('.blade.php', '', basename($template));
            }
        }

        // صفحه اصلی
        if (\is_front_page()) {
            $candidates[] = 'front-page';
        }

        // صفحه نوشته‌ها (وبلاگ)
        if (\is_home() && ! \is_front_page()) {
            $candidates[] = 'home';
            $candidates[] = 'blog';
        }

        // محصول تکی (ووکامرس یا CPT داخلی)
        if (\is_singular(['product', Products::CPT])) {
            $candidates[] = 'single-product';
            $candidates[] = 'product-single';
        }

        // نوشته تکی
        if (\is_singular('post')) {
            $candidates[] = 'single-post';
            $candidates[] = 'blog-single';
        }

        // هر محتوای تکی دیگر
        if (\is_singular()) {
            $post_type = (string) \get_post_type();

            if ($post_type === 'page') {
                $slug = (string) \get_post_field('post_name', \get_the_ID());

                if ($slug !== '') {
                    $candidates[] = 'page-' . $slug;
                }

                $candidates[] = 'page';
            } else {
                $candidates[] = 'single-' . $post_type;
            }
        }

        // نوشته تکی (نام عمومی)
        if (\is_singular('post')) {
            $candidates[] = 'single';
        }

        // دسته محصول
        if (\is_tax(Products::TAX)) {
            $term = \get_queried_object();

            if ($term instanceof \WP_Term) {
                $candidates[] = 'taxonomy-product-cat-' . $term->slug;
                $candidates[] = 'taxonomy-product_cat-' . $term->slug;
            }

            $candidates[] = 'category-product';
            $candidates[] = 'taxonomy-product_cat';
        }

        // آرشیو محصولات
        if (\is_post_type_archive(['product', Products::CPT])) {
            $candidates[] = 'archive-product';
            $candidates[] = 'products';
        }

        // آرشیو وبلاگ
        if (\is_category() || \is_tag() || \is_date()) {
            $candidates[] = 'archive';
        }

        // جست‌وجو
        if (\is_search()) {
            $candidates[] = 'search';
        }

        // خطای ۴۰۴
        if (\is_404()) {
            $candidates[] = '404';
        }

        // آرشیو عمومی
        if (\is_archive()) {
            $candidates[] = 'archive';
        }

        // صفحه‌های ووکامرس (سبد خرید، پرداخت، حساب کاربری)
        if (\function_exists('is_cart') && \is_cart()) {
            $candidates[] = 'woocommerce/cart';
        }

        if (\function_exists('is_checkout') && \is_checkout()) {
            $candidates[] = 'woocommerce/checkout';
        }

        if (\function_exists('is_account_page') && \is_account_page()) {
            $candidates[] = 'woocommerce/myaccount';
            $candidates[] = 'woocommerce/myaccount/my-account';
        }

        // نمای عمومی محتوای تکی فقط برای درخواست‌های تکی نامزد می‌شود؛ در غیر
        // این صورت یک بایگانی بدون نما به‌جای index به singular می‌افتاد.
        if (\is_singular()) {
            $candidates[] = 'singular';
        }

        $candidates[] = 'index';

        /**
         * فیلتر نامزدهای قالب.
         *
         * @param array $candidates نامزدها
         */
        return (array) \apply_filters('beryon/template/candidates', array_values(array_unique($candidates)));
    }

    /**
     * داده مشترک نماها.
     *
     * @param string $view نام نما
     * @return array<string,mixed>
     */
    public static function data(string $view = ''): array
    {
        $data = [
            'view'      => $view,
            'opt'       => static fn (string $key, $default = null) => Settings::opt($key, $default),
            't'         => static fn ($text): string => \beryon_tokens(is_string($text) ? $text : (string) $text),
            'fa'        => static fn ($value): string => \beryon_fa($value),
            'num'       => static fn ($value): string => \beryon_number($value),
            'options'   => Settings::all(),
            'nav'       => Nav::items('primary'),
            'nav_quick' => Nav::items('footer_quick'),
            'nav_cats'  => Nav::items('footer_cats'),
            'active'    => \beryon_active_nav(),
            'categories' => Products::categories(),
            'total'     => Products::total_count(),
            'is_rtl'    => \is_rtl(),
            'site_name' => (string) \get_bloginfo('name'),
            'year'      => \beryon_year(),
        ];

        /**
         * فیلتر داده نماها.
         *
         * @param array  $data داده
         * @param string $view نام نما
         */
        $data = (array) \apply_filters('beryon/view/data', $data, $view);

        /** فیلتر اختصاصی هر نما: beryon/view/{name} */
        $data = (array) \apply_filters('beryon/view/' . $view, $data);

        Acorn::share($data);

        return $data;
    }

    /**
     * پاک‌سازی حافظه نهان نما.
     *
     * @return void
     */
    public static function flush(): void
    {
        self::$resolved = '';
        Acorn::flush();
    }
}
