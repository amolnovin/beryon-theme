<?php

/**
 * Walker منوی اصلی قالب بریون
 * ------------------------------------------------------------------
 * مارک‌آپ خروجی دقیقاً مطابق قالب HTML نهایی (و \App\Nav::render) است:
 *   دسکتاپ: <li><a>…</a><div class="dropdown"><a>…</a>…</div></li>
 *   موبایل: <a data-subtoggle="#m-sub-*">…</a><div class="sub" id="m-sub-*"><a>…</a>…</div>
 *   پانوشت: <li><a>…</a></li>
 *
 * زیرمنوها <li> ندارند؛ <li> داخل <div class="dropdown"> در مرورگر <li> والد را
 * می‌بندد و کل سربرگ را به هم می‌ریزد (نقص نسخه‌های ≤ ۱.۰.۳ وقتی منویی به
 * «منوی اصلی» نسبت داده می‌شد).
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * Walker منوی اصلی.
 */
final class NavWalker extends \Walker_Nav_Menu
{
    /**
     * شناسه زیرمنوی موبایلِ آیتم سطح اولی که در حال چاپ است.
     *
     * @var string
     */
    private $sub_id = '';

    /**
     * حالت رندر: desktop | mobile | footer.
     *
     * @param mixed $args آرگومان‌های wp_nav_menu
     * @return string
     */
    private static function mode($args): string
    {
        $mode = is_object($args) && isset($args->beryon_mode) ? (string) $args->beryon_mode : '';

        if ($mode === '' && is_object($args) && ! empty($args->beryon_mobile)) {
            $mode = 'mobile';
        }

        return in_array($mode, ['mobile', 'footer'], true) ? $mode : 'desktop';
    }

    /**
     * یکسان‌سازی آدرس برای مقایسه (بدون لنگر و اسلش پایانی).
     *
     * @param string $url آدرس
     * @return string
     */
    private static function normalize(string $url): string
    {
        return \untrailingslashit((string) preg_replace('/#.*$/', '', trim($url)));
    }

    /**
     * شروع یک سطح فهرست.
     *
     * فقط سطح اول ظرف می‌سازد؛ سطح‌های عمیق‌تر (اگر کسی depth بزرگ‌تری بدهد)
     * در همان ظرف تخت می‌شوند تا مارک‌آپ همیشه معتبر بماند.
     *
     * @param string   $output خروجی (به‌صورت ارجاع)
     * @param int      $depth  عمق
     * @param stdClass $args   آرگومان‌ها
     * @return void
     */
    public function start_lvl(&$output, $depth = 0, $args = null) // phpcs:ignore Squiz.Commenting.FunctionComment.TypeHintMissing
    {
        if ($depth !== 0) {
            return;
        }

        if (self::mode($args) === 'mobile') {
            $output .= '<div class="sub" id="' . esc_attr($this->sub_id) . '">';

            return;
        }

        $output .= '<div class="dropdown">';
    }

    /**
     * پایان یک سطح فهرست.
     *
     * @param string   $output خروجی
     * @param int      $depth  عمق
     * @param stdClass $args   آرگومان‌ها
     * @return void
     */
    public function end_lvl(&$output, $depth = 0, $args = null) // phpcs:ignore Squiz.Commenting.FunctionComment.TypeHintMissing
    {
        if ($depth !== 0) {
            return;
        }

        $output .= '</div>';
    }

    /**
     * شروع یک آیتم.
     *
     * @param string   $output خروجی
     * @param \WP_Post $item   آیتم
     * @param int      $depth  عمق
     * @param stdClass $args   آرگومان‌ها
     * @param int      $id     شناسه
     * @return void
     */
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) // phpcs:ignore Squiz.Commenting.FunctionComment.TypeHintMissing
    {
        $mode      = self::mode($args);
        $classes   = empty($item->classes) ? [] : (array) $item->classes;
        $max_depth = is_object($args) && isset($args->depth) ? (int) $args->depth : 0;

        // زیرمنو فقط وقتی باز می‌شود که Walker واقعاً فرزندان را چاپ کند
        // (شرط همان شرط Walker::display_element است).
        $opens_sub = $depth === 0 && ! empty($this->has_children) && ($max_depth === 0 || $max_depth > 1);

        $is_current = in_array('current-menu-item', $classes, true);
        $is_active  = false;

        if ($mode !== 'footer' && $depth === 0) {
            $is_active = $is_current || in_array('current-menu-ancestor', $classes, true);

            // «بخش فعال» قالب (محصولات روی صفحه تک‌محصول، وبلاگ روی تک‌نوشته و …)
            // تا برجسته‌سازی با منوی پیش‌فرض قالب یکی باشد.
            $section = is_object($args) && isset($args->beryon_active_url) ? self::normalize((string) $args->beryon_active_url) : '';

            if (! $is_active && $section !== '' && self::normalize((string) $item->url) === $section) {
                $is_active = true;
            }
        }

        $attributes = ' href="' . esc_url((string) $item->url) . '"';

        if ($mode === 'mobile' && $opens_sub) {
            $this->sub_id = 'm-sub-' . (int) $item->ID;
            $attributes  .= ' data-subtoggle="#' . esc_attr($this->sub_id) . '"';
        }

        if ($is_active) {
            $attributes .= ' class="active"';
        }

        if ($mode !== 'footer' && $is_current) {
            $attributes .= ' aria-current="page"';
        }

        if (! empty($item->target)) {
            $attributes .= ' target="' . esc_attr((string) $item->target) . '"';
        }

        $rel = trim((string) ($item->xfn ?? ''));

        if ($rel === '' && (string) ($item->target ?? '') === '_blank') {
            $rel = 'noopener';
        }

        if ($rel !== '') {
            $attributes .= ' rel="' . esc_attr($rel) . '"';
        }

        $title = apply_filters('the_title', $item->title, $item->ID); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
        $inner = esc_html(wp_strip_all_tags((string) $title));

        if ($opens_sub && $mode !== 'footer') {
            $size   = $mode === 'mobile' ? ' width="16"' : '';
            $inner .= ' <svg viewBox="0 0 24 24"' . $size . ' fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
        }

        $link = '<a' . $attributes . '>' . $inner . '</a>';

        // موبایل و زیرمنوها: فقط پیوند. <li> تنها برای آیتم‌های سطح اولِ دسکتاپ/پانوشت است.
        if ($mode === 'mobile' || $depth > 0) {
            $output .= $link;

            return;
        }

        $output .= '<li>' . $link;
    }

    /**
     * پایان یک آیتم.
     *
     * @param string   $output خروجی
     * @param \WP_Post $item   آیتم
     * @param int      $depth  عمق
     * @param stdClass $args   آرگومان‌ها
     * @return void
     */
    public function end_el(&$output, $item, $depth = 0, $args = null) // phpcs:ignore Squiz.Commenting.FunctionComment.TypeHintMissing
    {
        if (self::mode($args) === 'mobile' || $depth > 0) {
            return;
        }

        $output .= '</li>';
    }
}
