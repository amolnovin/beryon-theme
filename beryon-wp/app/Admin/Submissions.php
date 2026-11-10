<?php

/**
 * مدیریت درخواست‌های کاربران (فرم‌های تماس، نظرات، نمایندگی، خبرنامه)
 * ------------------------------------------------------------------
 * فهرست با کلاس WP_List_Table، تغییر وضعیت، حذف، خروجی CSV و جست‌وجو با کد پیگیری.
 *
 * @package beryon
 */

namespace App\Admin;

use App\Forms;
use App\Jalali;

defined('ABSPATH') || exit;

/**
 * کلاس جدول درخواست‌ها.
 */
final class Submissions
{
    /** اسلاگ صفحه */
    public const PAGE_SLUG = 'beryon-submissions';

    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        \add_action('admin_post_beryon_export_submissions', [self::class, 'export_csv']);
        \add_action('admin_post_beryon_purge_submissions', [self::class, 'purge']);
        \add_action('admin_init', [self::class, 'handle_bulk']);
    }

    /**
     * نام جدول در دیتابیس.
     *
     * @return string
     */
    private static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'beryon_submissions';
    }

    /**
     * رندر صفحه.
     *
     * @return void
     */
    public static function render(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\__('دسترسی مجاز نیست.', BERYON_TD));
        }

        $view = isset($_GET['view']) ? (string) \sanitize_key(\wp_unslash($_GET['view'])) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        echo '<div class="wrap beryon-wrap" dir="rtl">';

        self::tabs();

        if ($view === 'item') {
            self::item_view();
        } else {
            self::list_view();
        }

        echo '</div>';
    }

    /**
     * تب‌های صفحه.
     *
     * @return void
     */
    private static function tabs(): void
    {
        echo '<div class="beryon-head"><div><h1>' . esc_html__('درخواست‌های کاربران', BERYON_TD) . '</h1>';
        echo '<p class="beryon-muted">' . esc_html__('فرم‌های تماس، نظرات و شکایات، نمایندگی و خبرنامه', BERYON_TD) . '</p></div></div>';

        echo '<nav class="beryon-tabs">';
        echo '<a class="beryon-tab" href="' . esc_url(\admin_url('admin.php?page=beryon-settings')) . '">' . esc_html__('تنظیمات', BERYON_TD) . '</a>';
        echo '<a class="beryon-tab is-active" href="' . esc_url(\admin_url('admin.php?page=' . self::PAGE_SLUG)) . '" aria-current="page">' . esc_html__('درخواست‌ها', BERYON_TD) . '</a>';
        echo '<a class="beryon-tab" href="' . esc_url(\admin_url('admin.php?page=beryon-updates')) . '">' . esc_html__('به‌روزرسانی', BERYON_TD) . '</a>';
        echo '<a class="beryon-tab" href="' . esc_url(\admin_url('admin.php?page=beryon-tools')) . '">' . esc_html__('ابزارها', BERYON_TD) . '</a>';
        echo '</nav>';
    }

    /**
     * نمای فهرست.
     *
     * @return void
     */
    private static function list_view(): void
    {
        global $wpdb;

        $table   = self::table();
        $paged   = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $per     = 25;
        $offset  = ($paged - 1) * $per;
        $type    = isset($_GET['ftype']) ? (string) \sanitize_key(\wp_unslash($_GET['ftype'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $status  = isset($_GET['fstatus']) ? (string) \sanitize_key(\wp_unslash($_GET['fstatus'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search  = isset($_GET['s']) ? (string) \sanitize_text_field(\wp_unslash($_GET['s'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $orderby = isset($_GET['orderby']) ? (string) \sanitize_key(\wp_unslash($_GET['orderby'])) : 'id'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order   = isset($_GET['order']) && strtolower((string) \wp_unslash($_GET['order'])) === 'asc' ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if (! in_array($orderby, ['id', 'created_at', 'form_type', 'status'], true)) {
            $orderby = 'id';
        }

        $where  = ['1=1'];
        $params = [];

        if ($type !== '') {
            $where[]  = 'form_type = %s';
            $params[] = $type;
        }

        if ($status !== '') {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        if ($search !== '') {
            $where[]  = '(ref_code LIKE %s OR name LIKE %s OR tel LIKE %s OR email LIKE %s OR subject LIKE %s)';
            $like     = '%' . $wpdb->esc_like($search) . '%'; // phpcs:ignore WordPress.DB
            array_push($params, $like, $like, $like, $like, $like);
        }

        $where_sql = implode(' AND ', $where);

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        if ($params !== []) {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", array_merge($params, [$per, $offset])), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", [$per, $offset]), ARRAY_A);
        }
        // phpcs:enable

        $stats = Forms::stats();

        echo '<div class="beryon-stat-row">';
        echo self::stat_card(\__('کل', BERYON_TD), (string) ($stats['total'] ?? 0));
        echo self::stat_card(\__('جدید', BERYON_TD), (string) ($stats['new'] ?? 0), 'warn');
        echo self::stat_card(\__('تماس', BERYON_TD), (string) ($stats['contact'] ?? 0));
        echo self::stat_card(\__('نظرات', BERYON_TD), (string) ($stats['feedback'] ?? 0));
        echo self::stat_card(\__('نمایندگی', BERYON_TD), (string) ($stats['agency'] ?? 0));
        echo self::stat_card(\__('خبرنامه', BERYON_TD), (string) ($stats['newsletter'] ?? 0));
        echo '</div>';

        echo '<form method="get" class="beryon-filters">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '">';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('جست‌وجو با کد پیگیری، نام، تلفن…', BERYON_TD) . '">';

        echo '<select name="ftype"><option value="">' . esc_html__('همه فرم‌ها', BERYON_TD) . '</option>';

        foreach (['contact' => __('تماس', BERYON_TD), 'feedback' => __('نظرات', BERYON_TD), 'agency' => __('نمایندگی', BERYON_TD), 'newsletter' => __('خبرنامه', BERYON_TD)] as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($type, $key, false) . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';

        echo '<select name="fstatus"><option value="">' . esc_html__('همه وضعیت‌ها', BERYON_TD) . '</option>';

        foreach (self::statuses() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';
        \submit_button(\__('فیلتر', BERYON_TD), 'secondary', 'filter_action', false);
        echo '</form>';

        echo '<form method="post" id="beryon-submissions-form">';
        \wp_nonce_field('beryon_submissions_bulk', 'beryon_bulk_nonce');
        echo '<input type="hidden" name="beryon_bulk" value="1">';

        echo '<div class="tablenav top"><div class="alignleft actions bulkactions">';
        echo '<select name="bulk_action">';
        echo '<option value="">' . esc_html__('عملیات گروهی', BERYON_TD) . '</option>';
        echo '<option value="read">' . esc_html__('علامت خوانده‌شده', BERYON_TD) . '</option>';
        echo '<option value="answered">' . esc_html__('علامت پاسخ‌داده‌شده', BERYON_TD) . '</option>';
        echo '<option value="archived">' . esc_html__('بایگانی', BERYON_TD) . '</option>';
        echo '<option value="delete">' . esc_html__('حذف', BERYON_TD) . '</option>';
        echo '</select>';
        echo '<button type="submit" class="button action">' . esc_html__('اعمال', BERYON_TD) . '</button>';
        echo '</div>';

        $total_pages = (int) ceil($total / $per);

        if ($total_pages > 1) {
            echo '<div class="tablenav-pages">';
            echo '<span class="displaying-num">' . esc_html(\sprintf(\__('%s صفحه', BERYON_TD), (string) $total_pages)) . '</span> ';

            echo \paginate_links([ // phpcs:ignore WordPress.Security.EscapeOutput
                'base'      => \add_query_arg('paged', '%#%'),
                'format'    => '',
                'current'   => $paged,
                'total'     => $total_pages,
                'prev_text' => '‹',
                'next_text' => '›',
            ]);
            echo '</div>';
        }

        echo '<div class="alignright actions">';
        echo '<a class="button" href="' . esc_url(\wp_nonce_url(\admin_url('admin-post.php?action=beryon_export_submissions'), 'beryon_export_submissions')) . '">' . esc_html__('خروجی CSV', BERYON_TD) . '</a> ';
        echo '<a class="button" href="' . esc_url(\wp_nonce_url(\admin_url('admin-post.php?action=beryon_purge_submissions'), 'beryon_purge_submissions')) . '" onclick="return confirm(\'' . esc_js(__('همه رکوردهای بایگانی‌شده حذف شوند؟', BERYON_TD)) . '\')">' . esc_html__('پاک‌سازی بایگانی', BERYON_TD) . '</a>';
        echo '</div></div>';

        echo '<table class="widefat striped beryon-table"><thead><tr>';
        echo '<th class="check-column"><input type="checkbox" id="beryon-check-all"></th>';
        echo self::sortable_th('id', \__('کد', BERYON_TD), $orderby, $order);
        echo self::sortable_th('ref_code', \__('کد پیگیری', BERYON_TD), $orderby, $order);
        echo self::sortable_th('form_type', \__('فرم', BERYON_TD), $orderby, $order);
        echo '<th>' . esc_html__('نام', BERYON_TD) . '</th>';
        echo '<th>' . esc_html__('تماس', BERYON_TD) . '</th>';
        echo '<th>' . esc_html__('موضوع', BERYON_TD) . '</th>';
        echo self::sortable_th('status', \__('وضعیت', BERYON_TD), $orderby, $order);
        echo self::sortable_th('created_at', \__('تاریخ', BERYON_TD), $orderby, $order);
        echo '</tr></thead><tbody>';

        if ($rows === []) {
            echo '<tr><td colspan="9">' . esc_html__('رکوردی یافت نشد.', BERYON_TD) . '</td></tr>';
        }

        foreach ((array) $rows as $row) {
            $id      = (int) $row['id'];
            $view_url = \add_query_arg(['page' => self::PAGE_SLUG, 'view' => 'item', 'id' => $id], \admin_url('admin.php'));

            echo '<tr>';
            echo '<th class="check-column"><input type="checkbox" name="ids[]" value="' . esc_attr((string) $id) . '"></th>';
            echo '<td>' . esc_html((string) $id) . '</td>';
            echo '<td><a href="' . esc_url($view_url) . '"><code>' . esc_html((string) $row['ref_code']) . '</code></a></td>';
            echo '<td>' . esc_html(self::type_label((string) $row['form_type'])) . '</td>';
            echo '<td>' . esc_html((string) $row['name']) . '</td>';
            echo '<td dir="ltr">' . esc_html((string) $row['tel']) . ($row['email'] !== '' ? '<br><small>' . esc_html((string) $row['email']) . '</small>' : '') . '</td>';
            echo '<td>' . esc_html((string) $row['subject']) . '</td>';
            echo '<td>' . self::status_badge((string) $row['status']) . '</td>';
            echo '<td>' . esc_html(Jalali::format((int) strtotime((string) $row['created_at'] . ' UTC'), 'Y/m/d H:i')) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></form>';
    }

    /**
     * نمای یک رکورد.
     *
     * @return void
     */
    private static function item_view(): void
    {
        global $wpdb;

        $id    = isset($_GET['id']) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $table = self::table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A) : null;

        if (! is_array($row)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('رکورد یافت نشد.', BERYON_TD) . '</p></div>';

            return;
        }

        // تغییر وضعیت
        if (isset($_POST['beryon_status']) && \check_admin_referer('beryon_status_' . $id)) {
            $status = (string) \sanitize_key(\wp_unslash($_POST['beryon_status']));

            if (isset(self::statuses()[$status])) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->update($table, ['status' => $status], ['id' => $id], ['%s'], ['%d']);
                $row['status'] = $status;
                echo '<div class="notice notice-success"><p>' . esc_html__('وضعیت به‌روزرسانی شد.', BERYON_TD) . '</p></div>';
            }
        }

        $payload = json_decode((string) ($row['payload'] ?? ''), true);

        echo '<div class="beryon-card">';
        echo '<h2 class="beryon-card-title">' . esc_html(self::type_label((string) $row['form_type'])) . ' — <code>' . esc_html((string) $row['ref_code']) . '</code></h2>';

        echo '<table class="widefat striped beryon-table"><tbody>';
        echo '<tr><th>' . esc_html__('نام', BERYON_TD) . '</th><td>' . esc_html((string) $row['name']) . '</td></tr>';

        if ((string) $row['tel'] !== '') {
            echo '<tr><th>' . esc_html__('تلفن', BERYON_TD) . '</th><td dir="ltr"><a href="tel:' . esc_attr((string) $row['tel']) . '">' . esc_html((string) $row['tel']) . '</a></td></tr>';
        }

        if ((string) $row['email'] !== '') {
            echo '<tr><th>' . esc_html__('ایمیل', BERYON_TD) . '</th><td dir="ltr"><a href="mailto:' . esc_attr((string) $row['email']) . '">' . esc_html((string) $row['email']) . '</a></td></tr>';
        }

        if ((string) $row['subject'] !== '') {
            echo '<tr><th>' . esc_html__('موضوع', BERYON_TD) . '</th><td>' . esc_html((string) $row['subject']) . '</td></tr>';
        }

        echo '<tr><th>' . esc_html__('پیام', BERYON_TD) . '</th><td>' . \wpautop(esc_html((string) $row['message'])) . '</td></tr>';
        echo '<tr><th>' . esc_html__('تاریخ', BERYON_TD) . '</th><td>' . esc_html(Jalali::format((int) strtotime((string) $row['created_at'] . ' UTC'), 'Y/m/d H:i')) . ' (' . esc_html((string) $row['created_at']) . ' GMT)</td></tr>';
        echo '<tr><th>' . esc_html__('وضعیت', BERYON_TD) . '</th><td>' . self::status_badge((string) $row['status']) . '</td></tr>';
        echo '<tr><th>' . esc_html__('IP', BERYON_TD) . '</th><td dir="ltr"><code>' . esc_html((string) $row['ip']) . '</code></td></tr>';
        echo '<tr><th>' . esc_html__('صفحه مبدأ', BERYON_TD) . '</th><td dir="ltr"><code>' . esc_html((string) $row['page_url']) . '</code></td></tr>';
        echo '<tr><th>' . esc_html__('User-Agent', BERYON_TD) . '</th><td dir="ltr"><small><code>' . esc_html((string) $row['user_agent']) . '</code></small></td></tr>';
        echo '</tbody></table>';

        if (is_array($payload) && $payload !== []) {
            echo '<h3>' . esc_html__('فیلدهای تکمیلی', BERYON_TD) . '</h3>';
            echo '<table class="widefat striped beryon-table"><tbody>';

            foreach ($payload as $key => $value) {
                if (is_array($value)) {
                    $value = implode('، ', array_map('strval', $value));
                }

                echo '<tr><th><code>' . esc_html((string) $key) . '</code></th><td>' . esc_html((string) $value) . '</td></tr>';
            }

            echo '</tbody></table>';
        }

        echo '<form method="post" class="beryon-inline-form">';
        \wp_nonce_field('beryon_status_' . $id);
        echo '<select name="beryon_status">';

        foreach (self::statuses() as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected((string) $row['status'], $key, false) . '>' . esc_html($label) . '</option>';
        }

        echo '</select> ';
        \submit_button(\__('ذخیره وضعیت', BERYON_TD), 'primary', 'beryon_save_status', false);
        echo '</form>';

        echo '<p><a class="button" href="' . esc_url(\admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">' . esc_html__('بازگشت به فهرست', BERYON_TD) . '</a></p>';
        echo '</div>';
    }

    /**
     * ستون مرتب‌شدنی.
     *
     * @param string $key     کلید
     * @param string $label   برچسب
     * @param string $orderby ستون فعال
     * @param string $order   جهت فعال
     * @return string
     */
    private static function sortable_th(string $key, string $label, string $orderby, string $order): string
    {
        $next  = $orderby === $key && $order === 'ASC' ? 'desc' : 'asc';
        $arrow = $orderby === $key ? ($order === 'ASC' ? ' ▲' : ' ▼') : '';
        $url   = \add_query_arg(['page' => self::PAGE_SLUG, 'orderby' => $key, 'order' => $next]);

        return '<th scope="col"><a href="' . esc_url($url) . '">' . esc_html($label) . esc_html($arrow) . '</a></th>';
    }

    /**
     * کارت آمار.
     *
     * @param string $label برچسب
     * @param string $value مقدار
     * @param string $tone  رنگ
     * @return string
     */
    private static function stat_card(string $label, string $value, string $tone = ''): string
    {
        return '<div class="beryon-stat' . ($tone !== '' ? ' beryon-stat--' . esc_attr($tone) : '') . '"><strong>' . esc_html($value) . '</strong><span>' . esc_html($label) . '</span></div>';
    }

    /**
     * وضعیت‌ها.
     *
     * @return array<string,string>
     */
    private static function statuses(): array
    {
        return [
            'new'      => \__('جدید', BERYON_TD),
            'read'     => \__('خوانده‌شده', BERYON_TD),
            'answered' => \__('پاسخ‌داده‌شده', BERYON_TD),
            'archived' => \__('بایگانی', BERYON_TD),
        ];
    }

    /**
     * نشان وضعیت.
     *
     * @param string $status وضعیت
     * @return string
     */
    private static function status_badge(string $status): string
    {
        $map = ['new' => 'warn', 'read' => 'info', 'answered' => 'ok', 'archived' => 'off'];
        $tone = $map[$status] ?? 'off';
        $label = self::statuses()[$status] ?? $status;

        return '<span class="beryon-badge ' . esc_attr($tone) . '">' . esc_html($label) . '</span>';
    }

    /**
     * برچسب نوع فرم.
     *
     * @param string $type نوع
     * @return string
     */
    private static function type_label(string $type): string
    {
        $map = [
            'contact'    => \__('تماس با ما', BERYON_TD),
            'feedback'   => \__('نظرات و شکایات', BERYON_TD),
            'agency'     => \__('درخواست نمایندگی', BERYON_TD),
            'newsletter' => \__('خبرنامه', BERYON_TD),
        ];

        return $map[$type] ?? $type;
    }

    /**
     * اعمال عملیات گروهی.
     *
     * @return void
     */
    public static function handle_bulk(): void
    {
        if (! isset($_POST['beryon_bulk']) || ! \check_admin_referer('beryon_submissions_bulk', 'beryon_bulk_nonce')) {
            return;
        }

        if (! \current_user_can('manage_options')) {
            return;
        }

        global $wpdb;

        $table  = self::table();
        $action = isset($_POST['bulk_action']) ? (string) \sanitize_key(\wp_unslash($_POST['bulk_action'])) : '';
        $ids    = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $ids    = array_values(array_filter($ids));

        if ($action === '' || $ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        if ($action === 'delete') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids));
        } elseif (isset(self::statuses()[$action])) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $wpdb->query($wpdb->prepare("UPDATE {$table} SET status = %s WHERE id IN ({$placeholders})", array_merge([$action], $ids)));
        }
    }

    /**
     * خروجی CSV.
     *
     * @return void
     */
    public static function export_csv(): void
    {
        if (! \current_user_can('manage_options') || ! \check_admin_referer('beryon_export_submissions')) {
            \wp_die(\__('دسترسی نامجاز.', BERYON_TD), '', ['response' => 403]);
        }

        global $wpdb;

        $table = self::table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 5000", ARRAY_A);

        $filename = 'beryon-submissions-' . gmdate('Ymd-His') . '.csv';

        \nocache_headers();
        \header('Content-Type: text/csv; charset=UTF-8');
        \header('Content-Disposition: attachment; filename=' . $filename);

        $out = fopen('php://output', 'w');

        if ($out === false) {
            return;
        }

        // BOM برای نمایش صحیح فارسی در Excel
        fwrite($out, "\xEF\xBB\xBF"); // phpcs:ignore WordPress.WP.AlternativeFunctions

        fputcsv($out, ['id', 'ref_code', 'form_type', 'status', 'name', 'tel', 'email', 'subject', 'message', 'created_at', 'ip', 'page_url']);

        foreach ((array) $rows as $row) {
            fputcsv($out, [
                $row['id'], $row['ref_code'], $row['form_type'], $row['status'], $row['name'], $row['tel'],
                $row['email'], $row['subject'], $row['message'], $row['created_at'], $row['ip'], $row['page_url'],
            ]);
        }

        fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions
        exit;
    }

    /**
     * پاک‌سازی رکوردهای بایگانی‌شده.
     *
     * @return void
     */
    public static function purge(): void
    {
        if (! \current_user_can('manage_options') || ! \check_admin_referer('beryon_purge_submissions')) {
            \wp_die(\__('دسترسی نامجاز.', BERYON_TD), '', ['response' => 403]);
        }

        global $wpdb;

        $table = self::table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE status = %s", 'archived'));

        \wp_safe_redirect(\admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }
}
