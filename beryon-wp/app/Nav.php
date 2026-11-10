<?php

/**
 * منوی پیش‌فرض و ابزارهای منو — قالب بریون
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * منوی پیش‌فرض و ابزارهای منو.
 */
final class Nav
{
    /**
     * منوی پیش‌فرض (زمانی که کاربر منویی نساخته باشد).
     *
     * @param string $location مکان
     * @param string $active   کلید فعال
     * @return string
     */
    public static function fallback(string $location = 'primary', string $active = ''): string
    {
        $items = self::items($location);

        if ($items === []) {
            return '';
        }

        return self::render($items, $location === 'primary' ? 'desktop' : 'footer', $active);
    }

    /**
     * داده آیتم‌های منو.
     *
     * @param string $location مکان
     * @return array<int,array<string,mixed>>
     */
    public static function items(string $location): array
    {
        switch ($location) {
            case 'primary':
                $products = [];

                foreach (Products::categories() as $category) {
                    $products[] = ['title' => (string) $category['name'], 'url' => (string) $category['link'], 'children' => []];
                }

                $about_children = [
                    ['key' => 'about', 'title' => \__('درباره بریون', BERYON_TD), 'url' => self::page('about-us', 'about')],
                    ['key' => 'about', 'title' => \__('معرفی مدیرعامل', BERYON_TD), 'url' => self::page('about-us/introduction-of-ceo', 'ceo')],
                    ['key' => 'about', 'title' => \__('چارت سازمانی', BERYON_TD), 'url' => self::page('about-us/organizational-chart', 'org')],
                    ['key' => 'about', 'title' => \__('گواهینامه‌ها و افتخارات', BERYON_TD), 'url' => self::page('about-us/certificates', 'certificates'), 'mobile_title' => \__('گواهینامه‌ها', BERYON_TD)],
                ];

                $items = [
                    ['key' => 'home', 'title' => \__('خانه', BERYON_TD), 'url' => \home_url('/'), 'children' => []],
                    ['key' => 'products', 'sub_id' => 'm-sub-prod', 'title' => \__('محصولات', BERYON_TD), 'url' => \beryon_products_url(), 'children' => $products],
                    ['key' => 'about', 'sub_id' => 'm-sub-about', 'title' => \__('درباره ما', BERYON_TD), 'url' => self::page('about-us', 'about'), 'children' => $about_children],
                    ['key' => 'blog', 'title' => \__('وبلاگ', BERYON_TD), 'url' => self::blog_url(), 'children' => []],
                    ['key' => 'catalog', 'title' => \__('کاتالوگ', BERYON_TD), 'url' => self::page('catalog', 'catalog'), 'children' => []],
                    ['key' => 'feedback', 'title' => \__('نظرات و شکایات', BERYON_TD), 'url' => self::page('feedback', 'feedback'), 'children' => []],
                    ['key' => 'contact', 'title' => \__('تماس با ما', BERYON_TD), 'url' => self::page('contact-us', 'contact'), 'children' => []],
                ];

                if ($products !== []) {
                    array_unshift($items[1]['children'], ['key' => 'products', 'title' => \__('همه محصولات', BERYON_TD), 'url' => \beryon_products_url(), 'children' => []]);
                }

                return $items;

            case 'footer_quick':
                // ترتیب و برچسب‌ها دقیقاً مطابق پانوشت قالب HTML نهایی.
                return [
                    ['title' => \__('صفحه اصلی', BERYON_TD), 'url' => \home_url('/')],
                    ['title' => \__('درباره بریون', BERYON_TD), 'url' => self::page('about-us', 'about')],
                    ['title' => \__('گواهینامه‌ها و افتخارات', BERYON_TD), 'url' => self::page('about-us/certificates', 'certificates')],
                    ['title' => \__('کاتالوگ محصولات', BERYON_TD), 'url' => self::page('catalog', 'catalog')],
                    ['title' => \__('مجله بریان گوشت', BERYON_TD), 'url' => self::blog_url()],
                    ['title' => \__('اعطای نمایندگی', BERYON_TD), 'url' => self::page('agency', 'agency')],
                    ['title' => \__('ثبت نظر و شکایت', BERYON_TD), 'url' => self::page('feedback', 'feedback')],
                ];

            case 'footer_cats':
                $items = [];

                foreach (Products::categories() as $category) {
                    $items[] = ['title' => (string) $category['name'], 'url' => (string) $category['link']];
                }

                $items[] = ['title' => \__('همه محصولات', BERYON_TD), 'url' => \beryon_products_url()];

                return $items;

            default:
                return [];
        }
    }

    /**
     * رندر آیتم‌ها.
     *
     * @param array<int,array<string,mixed>> $items    آیتم‌ها
     * @param string                         $mode     desktop|mobile|footer
     * @param string                         $active   کلید فعال
     * @return string
     */
    public static function render(array $items, string $mode = 'desktop', string $active = ''): string
    {
        $html = '';

        foreach ($items as $index => $item) {
            $key      = (string) ($item['key'] ?? '');
            $title    = esc_html((string) $item['title']);
            $url      = esc_url((string) $item['url']);
            $children = (array) ($item['children'] ?? []);
            $is_active = $active !== '' && ($key === $active || self::child_active($children, $active));

            $link_attrs = ' href="' . $url . '"' . ($is_active ? ' class="active" aria-current="page"' : '');

            if ($mode === 'footer') {
                $html .= ($html === '' ? '' : "\n") . '          <li><a href="' . $url . '">' . $title . '</a></li>';

                continue;
            }

            // آیکون فلش، دقیقاً با همان مارک‌آپ قالب HTML (svg درون‌خطی).
            $chevron = $children !== []
                ? ' <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>'
                : '';

            $chevron_mobile = $children !== []
                ? ' <svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>'
                : '';

            if ($mode === 'desktop') {
                $html .= '<li>';
                $html .= '<a' . $link_attrs . '>' . $title . $chevron . '</a>';

                if ($children !== []) {
                    $html .= '<div class="dropdown">';

                    foreach ($children as $child) {
                        $child_active = $active !== '' && (string) ($child['key'] ?? '') === $active;
                        $html .= '<a href="' . esc_url((string) $child['url']) . '"' . ($child_active ? ' class="active"' : '') . '>' . esc_html((string) $child['title']) . '</a>';
                    }

                    $html .= '</div>';
                }

                $html .= '</li>';

                continue;
            }

            // mobile
            $sub_id      = (string) ($item['sub_id'] ?? ('m-sub-' . $index));
            $mobile_text = esc_html((string) ($item['mobile_title'] ?? $item['title']));

            if ($children !== []) {
                $html .= '<a href="' . $url . '" data-subtoggle="#' . esc_attr($sub_id) . '"' . ($is_active ? ' class="active" aria-current="page"' : '') . '>' . $mobile_text . $chevron_mobile . '</a>';
                $html .= '<div class="sub" id="' . esc_attr($sub_id) . '">';

                foreach ($children as $child) {
                    $child_text = esc_html((string) ($child['mobile_title'] ?? $child['title']));
                    $html .= '<a href="' . esc_url((string) $child['url']) . '">' . $child_text . '</a>';
                }

                $html .= '</div>';
            } else {
                $html .= '<a' . $link_attrs . '>' . $title . '</a>';
            }
        }

        return $html;
    }

    /**
     * آیا یکی از زیرآیتم‌ها فعال است؟
     *
     * @param array<int,array<string,mixed>> $children زیرآیتم‌ها
     * @param string                         $active   کلید فعال
     * @return bool
     */
    private static function child_active(array $children, string $active): bool
    {
        foreach ($children as $child) {
            if ((string) ($child['key'] ?? '') === $active) {
                return true;
            }
        }

        return false;
    }

    /**
     * آدرس یک صفحه از روی اسلاگ یا نام قالب.
     *
     * @param string $slug     اسلاگ
     * @param string $template نام قالب
     * @return string
     */
    public static function page(string $slug, string $template = ''): string
    {
        return \beryon_page_url($slug, $template);
    }

    /**
     * آدرس وبلاگ.
     *
     * @return string
     */
    public static function blog_url(): string
    {
        $page = (int) \get_option('page_for_posts');

        if ($page > 0) {
            return (string) \get_permalink($page);
        }

        $by_template = \beryon_page_url('blog', 'blog');

        return $by_template !== \home_url('/') ? $by_template : \home_url('/');
    }
}
