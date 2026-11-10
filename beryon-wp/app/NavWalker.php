<?php

/**
 * Walker منوی اصلی قالب بریون
 * ------------------------------------------------------------------
 * مارک‌آپ خروجی دقیقاً مطابق قالب HTML نهایی است:
 *   دسکتاپ: <li><a>…</a><div class="dropdown">…</div></li>
 *   موبایل: <a data-subtoggle="#m-sub-*">…</a><div class="sub" id="m-sub-*">…</div>
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
     * شروع یک سطح فهرست.
     *
     * @param string   $output خروجی (به‌صورت ارجاع)
     * @param int      $depth  عمق
     * @param stdClass $args   آرگومان‌ها
     * @return void
     */
    public function start_lvl(&$output, $depth = 0, $args = null) // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod, Squiz.Commenting.FunctionComment.TypeHintMissing
    {
        if ($depth === 0) {
            $output .= '<div class="dropdown">';

            return;
        }

        $output .= '<div class="sub">';
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
        $classes   = empty($item->classes) ? [] : (array) $item->classes;
        $has_child = in_array('menu-item-has-children', $classes, true);
        $is_mobile = isset($args->beryon_mobile) && $args->beryon_mobile;

        $link_class = [];

        if ($has_child && $depth === 0) {
            $link_class[] = 'has-sub';
        }

        if (in_array('current-menu-item', $classes, true) || in_array('current-menu-ancestor', $classes, true)) {
            $link_class[] = 'active';
        }

        $attributes  = ' href="' . esc_url((string) $item->url) . '"';
        $attributes .= $link_class === [] ? '' : ' class="' . esc_attr(implode(' ', $link_class)) . '"';

        if (in_array('current-menu-item', $classes, true)) {
            $attributes .= ' aria-current="page"';
        }

        $title = apply_filters('the_title', $item->title, $item->ID); // phpcs:ignore WordPress.WP.GlobalVariablesOverride

        $icon = '';

        if ($has_child && $depth === 0) {
            $icon = 'chevron-down';
        }

        $inner = esc_html(wp_strip_all_tags((string) $title));

        if ($icon !== '') {
            $size    = $is_mobile ? ' width="16"' : '';
            $inner  .= ' <svg' . $size . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><use href="#i-' . esc_attr($icon) . '"></use></svg>';
        }

        if ($is_mobile && $has_child && $depth === 0) {
            $sub_id      = 'm-sub-' . (string) $item->object_id;
            $attributes .= ' data-subtoggle="#' . esc_attr($sub_id) . '" aria-expanded="false"';
        }

        if ($is_mobile) {
            $output .= '<a' . $attributes . '>' . $inner . '</a>';

            if ($has_child && $depth === 0) {
                $output .= '<div class="sub" id="m-sub-' . esc_attr((string) $item->object_id) . '">';
            }

            return;
        }

        $li_class = [];

        if ($has_child) {
            $li_class[] = 'has-dropdown';
        }

        if (in_array('current-menu-item', $classes, true) || in_array('current-menu-ancestor', $classes, true)) {
            $li_class[] = 'current';
        }

        $output .= '<li' . ($li_class === [] ? '' : ' class="' . esc_attr(implode(' ', $li_class)) . '"') . '>';
        $output .= '<a' . $attributes . '>' . $inner . '</a>';
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
        $is_mobile = isset($args->beryon_mobile) && $args->beryon_mobile;

        $output .= $is_mobile ? '' : '</li>';
    }
}
