<?php

/**
 * پیوند قالب با افزونه «سیستم نمایندگان فروش بریان» (beryon-agents) — قالب بریون
 *
 * افزونه سه شورت‌کد دارد:
 *   [beryon_agent_application]  فرم عمومی درخواست نمایندگی
 *   [beryon_agent_panel]        پنل نماینده فروش (ثبت سفارش، تاریخچه)
 *   [beryon_admin_panel]        پنل نظارت مدیریت (سفارش‌ها، نمایندگان، درخواست‌ها)
 *
 * این کلاس سه کار می‌کند:
 *   ۱) برگه هر شورت‌کد را پیدا می‌کند (قالب برگه، شورت‌کد در متن برگه، یا نامک) تا
 *      دکمه‌های «درخواست نمایندگی» سایت به همان برگه بروند؛
 *   ۲) برای آن برگه‌ها نمای مناسب قالب را انتخاب می‌کند (page-agency برای فرم،
 *      page-agent-panel و page-agents-admin برای دو پنل)، حتی اگر برگه فقط شورت‌کد
 *      داشته باشد و قالبی برایش انتخاب نشده باشد؛
 *   ۳) خروجی فرم افزونه را برای برگه‌های «اعطای نمایندگی» و «نظرات و شکایات» آماده
 *      می‌کند. اگر افزونه فعال نباشد همه‌چیز به فرم‌های خودِ قالب برمی‌گردد.
 *
 * افزونه نسخه ۱.۷.۲ فرم «نظرات و شکایات» ندارد؛ برای آن برگه، شورت‌کدِ تنظیم
 * feedback.form_shortcode یا شورت‌کدی که در متن خودِ برگه نوشته شده به‌کار می‌رود.
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * پیوند با افزونه نمایندگان.
 */
final class Agents
{
    public const TAG_APPLICATION = 'beryon_agent_application';
    public const TAG_AGENT_PANEL = 'beryon_agent_panel';
    public const TAG_ADMIN_PANEL = 'beryon_admin_panel';

    /** Transient نقشه «نقش → شناسه برگه» */
    private const CACHE = 'beryon_agents_pages';

    /** شورت‌کدهای هسته وردپرس که «فرم افزونه» به حساب نمی‌آیند */
    private const CORE_TAGS = ['caption', 'wp_caption', 'gallery', 'playlist', 'audio', 'video', 'embed'];

    /** @var array<string,int>|null */
    private static $map = null;

    /**
     * نقش‌ها: شورت‌کد، نمای قالب، نامک و عنوان پیش‌فرض برگه.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function roles(): array
    {
        return [
            'application' => [
                'tag'   => self::TAG_APPLICATION,
                'view'  => 'page-agency',
                'slug'  => 'agency',
                'slugs' => ['agency', 'فرم-درخواست-نمایندگی', 'درخواست-نمایندگی', 'agency-request'],
                'title' => \__('درخواست نمایندگی فروش بریون', BERYON_TD),
            ],
            'agent' => [
                'tag'   => self::TAG_AGENT_PANEL,
                'view'  => 'page-agent-panel',
                'slug'  => 'agent-panel',
                'slugs' => ['agent-panel', 'پنل-نمایندگان'],
                'title' => \__('پنل نمایندگان فروش', BERYON_TD),
            ],
            'admin' => [
                'tag'   => self::TAG_ADMIN_PANEL,
                'view'  => 'page-agents-admin',
                'slug'  => 'agents-admin',
                'slugs' => ['agents-admin', 'پنل-مدیریت-نمایندگان'],
                'title' => \__('پنل مدیریت نمایندگان', BERYON_TD),
            ],
        ];
    }

    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        \add_filter('beryon/template/candidates', [self::class, 'candidates']);
        \add_action('template_redirect', [self::class, 'guard_panels'], 5);
        \add_filter('wp_robots', [self::class, 'robots']);
        \add_filter('body_class', [self::class, 'body_class']);

        foreach (['save_post_page', 'deleted_post', 'trashed_post', 'untrashed_post', 'after_switch_theme', 'activated_plugin', 'deactivated_plugin'] as $hook) {
            \add_action($hook, [self::class, 'flush']);
        }
    }

    /* ============================================================== *
     * وضعیت افزونه
     * ============================================================== */

    /**
     * آیا فرم درخواست نمایندگیِ افزونه در دسترس است؟
     *
     * @return bool
     */
    public static function active(): bool
    {
        return \shortcode_exists(self::TAG_APPLICATION);
    }

    /**
     * آیا پنل‌های افزونه در دسترس‌اند؟ (افزونه آن‌ها را فقط با ووکامرس فعال بار می‌کند)
     *
     * @return bool
     */
    public static function panels_active(): bool
    {
        return \shortcode_exists(self::TAG_AGENT_PANEL) && \shortcode_exists(self::TAG_ADMIN_PANEL);
    }

    /**
     * نسخه افزونه (اگر فعال باشد).
     *
     * @return string
     */
    public static function plugin_version(): string
    {
        // افزونه نسخه خودش را در ثابت BERYON_VERSION می‌گذارد (نام ثابت‌های قالب BERYON_THEME_* است).
        return defined('BERYON_ADDON_SLUG') && defined('BERYON_VERSION') ? (string) constant('BERYON_VERSION') : '';
    }

    /* ============================================================== *
     * برگه‌ها
     * ============================================================== */

    /**
     * نقش یک برگه: application | agent | admin یا رشته خالی.
     *
     * نخست قالب انتخاب‌شده برای برگه، سپس شورت‌کدی که در متن برگه نوشته شده است
     * (حتی وقتی افزونه غیرفعال است، تا نمای برگه با خاموش‌شدن افزونه عوض نشود).
     *
     * @param \WP_Post|int|null $post برگه
     * @return string
     */
    public static function role_of($post): string
    {
        $post = \get_post($post);

        if (! $post instanceof \WP_Post || $post->post_type !== 'page') {
            return '';
        }

        $template = basename((string) \get_page_template_slug($post), '.blade.php');

        foreach (self::roles() as $role => $conf) {
            if ($template !== '' && $template === $conf['view']) {
                return (string) $role;
            }
        }

        $content = (string) $post->post_content;

        if ($content === '' || strpos($content, '[beryon_') === false) {
            return '';
        }

        // پنل‌ها پیش از فرم: برگه‌ای که هر دو را داشته باشد «پنل» است.
        foreach (['admin', 'agent', 'application'] as $role) {
            if (preg_match('/\[' . preg_quote((string) self::roles()[$role]['tag'], '/') . '[\s\]\/]/', $content) === 1) {
                return $role;
            }
        }

        return '';
    }

    /**
     * شناسه برگه یک نقش (۰ = چنین برگه‌ای نیست).
     *
     * ترتیب جست‌وجو: تنظیم دستی (فقط «درخواست نمایندگی») ← برگه‌ای که قالب آن نقش را
     * دارد ← برگه‌ای که شورت‌کد افزونه در متنش هست ← نامک‌های شناخته‌شده.
     *
     * @param string $role application | agent | admin
     * @return int
     */
    public static function page_id(string $role): int
    {
        $roles = self::roles();

        if (! isset($roles[$role])) {
            return 0;
        }

        if ($role === 'application') {
            $manual = self::manual_page((string) Settings::raw('agency.page', ''));

            if ($manual > 0) {
                return $manual;
            }
        }

        if (self::$map === null) {
            $cached    = \get_transient(self::CACHE);
            self::$map = is_array($cached) ? array_map('intval', $cached) : self::discover();
        }

        return (int) (self::$map[$role] ?? 0);
    }

    /**
     * نشانی برگه یک نقش (رشته خالی اگر برگه‌ای نباشد).
     *
     * @param string $role نقش
     * @return string
     */
    public static function page_url(string $role): string
    {
        $id = self::page_id($role);

        return $id > 0 ? (string) \get_permalink($id) : '';
    }

    /**
     * پاک‌کردن نقشه کش‌شده برگه‌ها.
     *
     * @return void
     */
    public static function flush(): void
    {
        self::$map = null;
        \delete_transient(self::CACHE);
    }

    /**
     * برگه‌ای که مدیر دستی تعیین کرده (شناسه یا نامک/مسیر).
     *
     * @param string $value مقدار تنظیم
     * @return int
     */
    private static function manual_page(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $page = ctype_digit($value) ? \get_post((int) $value) : \get_page_by_path(trim($value, '/'));

        return $page instanceof \WP_Post && $page->post_type === 'page' && $page->post_status === 'publish' ? (int) $page->ID : 0;
    }

    /**
     * پیدا کردن برگه هر نقش و ذخیره در کش.
     *
     * @return array<string,int>
     */
    private static function discover(): array
    {
        global $wpdb;

        $found = [];

        foreach (self::roles() as $role => $conf) {
            $id = 0;

            // ۱) قالب برگه
            $ids = \get_posts(
                [
                    'post_type'        => 'page',
                    'post_status'      => 'publish',
                    'posts_per_page'   => 1,
                    'orderby'          => 'ID',
                    'order'            => 'ASC',
                    'fields'           => 'ids',
                    'lang'             => '',
                    'suppress_filters' => true,
                    'meta_query'       => [ // phpcs:ignore WordPress.DB.SlowDBQuery
                        ['key' => '_wp_page_template', 'value' => 'views/' . $conf['view'] . '.blade.php'],
                    ],
                ]
            );

            if (! empty($ids)) {
                $id = (int) $ids[0];
            }

            // ۲) شورت‌کد افزونه در متن برگه
            if ($id === 0) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
                        '%' . $wpdb->esc_like('[' . $conf['tag']) . '%'
                    )
                );
            }

            // ۳) نامک‌های شناخته‌شده
            if ($id === 0) {
                foreach ((array) $conf['slugs'] as $slug) {
                    $page = \get_page_by_path((string) $slug);

                    if ($page instanceof \WP_Post && $page->post_status === 'publish') {
                        $id = (int) $page->ID;

                        break;
                    }
                }
            }

            $found[(string) $role] = $id;
        }

        /**
         * فیلتر نقشه «نقش → شناسه برگه» افزونه نمایندگان.
         *
         * @param array<string,int> $found
         */
        $found = array_map('intval', (array) \apply_filters('beryon/agents/pages', $found));

        \set_transient(self::CACHE, $found, DAY_IN_SECONDS);

        return $found;
    }

    /**
     * ساخت برگه‌های افزونه (فقط آن‌هایی که وجود ندارند).
     *
     * برگه با شورت‌کد همان نقش و قالب برگه مناسب ساخته می‌شود. شورت‌کد در متن برگه
     * می‌ماند چون خودِ افزونه مهمان را فقط وقتی به صفحه ورود می‌فرستد که شورت‌کد
     * پنل در متن برگه باشد.
     *
     * @return array<string,array{id:int,created:bool,url:string}>
     */
    public static function create_pages(): array
    {
        self::flush();

        $result = [];

        foreach (self::roles() as $role => $conf) {
            $id      = self::page_id((string) $role);
            $created = false;

            if ($id === 0) {
                $new = \wp_insert_post(
                    [
                        'post_type'      => 'page',
                        'post_status'    => 'publish',
                        'post_title'     => (string) $conf['title'],
                        'post_name'      => (string) $conf['slug'],
                        'post_content'   => '[' . $conf['tag'] . ']',
                        'comment_status' => 'closed',
                        'ping_status'    => 'closed',
                    ],
                    true
                );

                if (! \is_wp_error($new) && (int) $new > 0) {
                    $id      = (int) $new;
                    $created = true;

                    \update_post_meta($id, '_wp_page_template', 'views/' . $conf['view'] . '.blade.php');
                }
            }

            $result[(string) $role] = [
                'id'      => $id,
                'created' => $created,
                'url'     => $id > 0 ? (string) \get_permalink($id) : '',
            ];
        }

        self::flush();

        return $result;
    }

    /* ============================================================== *
     * نما و دسترسی
     * ============================================================== */

    /**
     * نامزد نما برای برگه‌های افزونه.
     *
     * برگه‌ای که فقط شورت‌کد افزونه را دارد (بدون قالب برگه) هم نمای همان نقش را
     * می‌گیرد؛ قالبی که مدیر صریحاً برای برگه انتخاب کرده دست نمی‌خورد.
     *
     * @param array<int,string> $candidates نامزدها
     * @return array<int,string>
     */
    public static function candidates($candidates): array
    {
        $candidates = (array) $candidates;

        if (! \is_page()) {
            return $candidates;
        }

        $post     = \get_queried_object();
        $template = $post instanceof \WP_Post ? (string) \get_page_template_slug($post) : '';

        if (! $post instanceof \WP_Post || ($template !== '' && $template !== 'default')) {
            return $candidates;
        }

        $role = self::role_of($post);

        if ($role !== '') {
            array_unshift($candidates, (string) self::roles()[$role]['view']);
        }

        return array_values(array_unique($candidates));
    }

    /**
     * مهمان در برگه پنل → صفحه ورود (و بازگشت به همان برگه پس از ورود).
     *
     * خودِ افزونه همین کار را فقط وقتی می‌کند که شورت‌کد پنل در متن برگه باشد؛ این‌جا
     * برگه‌ای که فقط قالب برگه پنل را دارد هم پوشش داده می‌شود.
     *
     * @return void
     */
    public static function guard_panels(): void
    {
        if (\is_user_logged_in() || ! \is_page() || ! self::panels_active()) {
            return;
        }

        $post = \get_queried_object();
        $role = self::role_of($post);

        if ($role !== 'agent' && $role !== 'admin') {
            return;
        }

        \nocache_headers();
        \wp_safe_redirect(\wp_login_url((string) \get_permalink($post)));
        exit;
    }

    /**
     * برگه‌های پنل در موتور جست‌وجو نمایه نشوند.
     *
     * @param array<string,mixed> $robots دستورهای robots
     * @return array<string,mixed>
     */
    public static function robots($robots)
    {
        $robots = (array) $robots;

        if (\is_page() && in_array(self::role_of(\get_queried_object()), ['agent', 'admin'], true)) {
            $robots['noindex']  = true;
            $robots['nofollow'] = true;
            unset($robots['max-image-preview'], $robots['follow'], $robots['index']);
        }

        return $robots;
    }

    /**
     * کلاس body برای برگه‌های افزونه (استایل‌های ویژه در wp.css).
     *
     * @param array<int,string> $classes کلاس‌ها
     * @return array<int,string>
     */
    public static function body_class($classes): array
    {
        $classes = (array) $classes;

        if (\is_page()) {
            $role = self::role_of(\get_queried_object());

            if ($role !== '') {
                $classes[] = 'agents-page';
                $classes[] = 'agents-page--' . $role;
            }
        }

        return $classes;
    }

    /* ============================================================== *
     * خروجی شورت‌کدها
     * ============================================================== */

    /**
     * فرم افزونه برای برگه «اعطای نمایندگی» یا «نظرات و شکایات».
     *
     * رشته خالی یعنی «فرم خودِ قالب نمایش داده شود».
     *
     * @param string $which agency | feedback
     * @return string HTML
     */
    public static function form_html(string $which): string
    {
        $group = $which === 'feedback' ? 'feedback' : 'agency';

        if ((string) Settings::opt($group . '.form_source', 'auto') === 'theme') {
            return '';
        }

        $html = '';
        $code = trim((string) Settings::raw($group . '.form_shortcode', ''));

        // ۱) شورت‌کدی که مدیر در تنظیمات نوشته است.
        if ($code !== '' && \beryon_shortcode_ready($code)) {
            $html = (string) \do_shortcode($code);
        }

        // ۲) شورت‌کدی که در متن همین برگه نوشته شده است.
        if ($html === '') {
            $post = \get_queried_object();
            $code = $post instanceof \WP_Post ? self::shortcode_in((string) $post->post_content, $group === 'agency' ? [self::TAG_APPLICATION] : []) : '';

            if ($code !== '') {
                $html = (string) \do_shortcode($code);
            }
        }

        // ۳) فرم درخواست نمایندگیِ افزونه، اگر فعال است.
        if ($html === '' && $group === 'agency' && self::active()) {
            $html = (string) \do_shortcode('[' . self::TAG_APPLICATION . ']');
        }

        /**
         * فیلتر خروجی فرم افزونه (رشته خالی = فرم قالب).
         *
         * @param string $html  HTML فرم
         * @param string $group agency | feedback
         */
        return trim((string) \apply_filters('beryon/agents/form_html', $html, $group));
    }

    /**
     * خروجی پنل نماینده یا مدیریت برای برگه جاری.
     *
     * اگر شورت‌کد در متن برگه باشد کل متن برگه رندر می‌شود (مدیر می‌تواند پیش و پس
     * از پنل متن بگذارد)، وگرنه خودِ شورت‌کد.
     *
     * @param string $role agent | admin
     * @return string HTML (رشته خالی = افزونه در دسترس نیست)
     */
    public static function panel_html(string $role): string
    {
        $roles = self::roles();

        if (! isset($roles[$role]) || ! \shortcode_exists((string) $roles[$role]['tag'])) {
            return '';
        }

        $tag     = (string) $roles[$role]['tag'];
        $post    = \get_queried_object();
        $content = $post instanceof \WP_Post ? (string) $post->post_content : '';

        if ($content !== '' && \has_shortcode($content, $tag)) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- فیلتر هسته وردپرس
            return (string) \apply_filters('the_content', $content);
        }

        return (string) \do_shortcode('[' . $tag . ']');
    }

    /**
     * نخستین شورت‌کدِ قابل اجرا در یک متن.
     *
     * @param string            $content متن برگه
     * @param array<int,string> $only    فقط همین برچسب‌ها (خالی = هر شورت‌کد ثبت‌شده جز شورت‌کدهای هسته)
     * @return string متن کامل شورت‌کد یا رشته خالی
     */
    private static function shortcode_in(string $content, array $only): string
    {
        if ($content === '' || strpos($content, '[') === false) {
            return '';
        }

        if ($only !== []) {
            $tags = array_values(array_filter($only, '\shortcode_exists'));
        } else {
            global $shortcode_tags;

            $tags = array_values(array_diff(array_keys((array) $shortcode_tags), self::CORE_TAGS));
        }

        if ($tags === [] || preg_match('/' . \get_shortcode_regex($tags) . '/', $content, $match) !== 1) {
            return '';
        }

        return (string) $match[0];
    }
}
