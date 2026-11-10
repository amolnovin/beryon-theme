<?php

/**
 * فرم‌های قالب بریون — REST API، ذخیره‌سازی و کد پیگیری
 * ------------------------------------------------------------------
 * قرارداد داده (Data Contract):
 *   POST {rest_url}/beryon/v1/form
 *   Header: X-WP-Nonce (nonce = wp_rest)
 *   Body  : type=contact|feedback|agency|newsletter + فیلدهای فرم + hp (honeypot) + url
 *   201   : {"ok":true,"ref":"BRY-1405-48213","message":"…"}
 *   400   : {"ok":false,"code":"invalid","errors":{"tel":["…"]}}
 *   429   : {"ok":false,"code":"rate_limited","retry_after":123}
 *   503   : {"ok":false,"code":"disabled"}
 * همه ورودی‌ها sanitize و با allowlist ذخیره می‌شوند؛ هیچ مقداری مستقیم
 * در SQL یا خروجی استفاده نمی‌شود.
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * کلاس فرم‌ها.
 */
final class Forms
{
    /** فضای نام REST */
    public const REST_NS = 'beryon/v1';

    /** نسخه ساختار جدول */
    public const DB_VERSION = '1.0.0';

    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        \add_action('rest_api_init', [self::class, 'routes']);
        \add_action('after_switch_theme', [self::class, 'install_table']);
        \add_action('admin_init', [self::class, 'maybe_install_table']);

        \add_action('wp_ajax_beryon_export_submissions', [Admin\Submissions::class, 'export_csv']);
        \add_action('wp_ajax_beryon_submission_action', [Admin\Submissions::class, 'ajax_action']);
    }

    /**
     * نام جدول پیام‌ها.
     *
     * @return string
     */
    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'beryon_submissions';
    }

    /**
     * ساخت جدول (dbDelta).
     *
     * @return void
     */
    public static function install_table(): void
    {
        global $wpdb;

        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ref_code VARCHAR(32) NOT NULL,
            form_type VARCHAR(32) NOT NULL DEFAULT 'contact',
            name VARCHAR(190) NOT NULL DEFAULT '',
            tel VARCHAR(32) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL DEFAULT '',
            subject VARCHAR(190) NOT NULL DEFAULT '',
            message LONGTEXT NULL,
            payload LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            page_url VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY ref_code (ref_code),
            KEY form_type (form_type),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        \dbDelta($sql);

        \update_option('beryon_db_version', self::DB_VERSION, false);
    }

    /**
     * ساخت جدول در صورت نبود (برای نصب‌های موجود).
     *
     * @return void
     */
    public static function maybe_install_table(): void
    {
        if (\get_option('beryon_db_version') !== self::DB_VERSION) {
            self::install_table();
        }
    }

    /**
     * ثبت مسیرهای REST.
     *
     * @return void
     */
    public static function routes(): void
    {
        \register_rest_route(
            self::REST_NS,
            '/form',
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'handle'],
                'permission_callback' => '__return_true', // فرم عمومی؛ امنیت با nonce + rate limit + honeypot
                'args'                => self::args_schema(),
            ]
        );

        \register_rest_route(
            self::REST_NS,
            '/form/track/(?P<code>[A-Za-z0-9\-]{6,32})',
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'track'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * طرح آرگومان‌های REST.
     *
     * @return array<string,array<string,mixed>>
     */
    private static function args_schema(): array
    {
        $optional = [
            'required'          => false,
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => static fn ($value) => is_string($value) || is_numeric($value),
        ];

        $fields = ['type', 'hp', 'url', 'name', 'first_name', 'last_name', 'tel', 'email', 'subject', 'message', 'ptype',
            'department', 'company', 'province', 'city', 'work_tel', 'address', 'work_area', 'work_ownership',
            'warehouse_address', 'warehouse_tel', 'warehouse_area', 'warehouse_ownership', 'sales_types',
            'visitors', 'vehicles', 'coverage', 'agree'];

        $schema = [];

        foreach ($fields as $field) {
            $schema[$field] = $optional;
        }

        $schema['type']['required'] = true;

        // «انواع فروش» چک‌باکس چندگزینه‌ای است (name="sales_types[]") و به‌صورت آرایه
        // می‌رسد؛ طرح تک‌مقداری بالا آن را با خطای ۴۰۰ رد می‌کرد و فرم نمایندگی
        // هرگز ثبت نمی‌شد.
        $schema['sales_types'] = [
            'required'          => false,
            'validate_callback' => static function ($value): bool {
                if (is_string($value) || is_numeric($value)) {
                    return true;
                }

                if (! is_array($value) || count($value) > 30) {
                    return false;
                }

                foreach ($value as $item) {
                    if (! is_string($item) && ! is_numeric($item)) {
                        return false;
                    }
                }

                return true;
            },
            'sanitize_callback' => [self::class, 'sanitize_choices'],
        ];

        return $schema;
    }

    /**
     * پاک‌سازی فیلد چندگزینه‌ای: رشته یا آرایه‌ای از رشته‌ها → یک رشته «الف، ب».
     *
     * @param mixed $value مقدار خام
     * @return string
     */
    public static function sanitize_choices($value): string
    {
        if (! is_array($value)) {
            return is_scalar($value) ? \sanitize_text_field((string) $value) : '';
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $item = trim(\sanitize_text_field((string) $item));

            if ($item !== '' && ! in_array($item, $items, true)) {
                $items[] = $item;
            }
        }

        return implode('، ', $items);
    }

    /**
     * پردازش ارسال فرم.
     *
     * @param \WP_REST_Request $request درخواست
     * @return \WP_REST_Response
     */
    public static function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        if (! (bool) Settings::opt('forms.enabled', true)) {
            return self::response(['ok' => false, 'code' => 'disabled', 'message' => \__('ثبت پیام موقتاً غیرفعال است.', BERYON_TD)], 503);
        }

        // ۱) nonce (محافظت CSRF)
        $nonce = (string) $request->get_header('X-WP-Nonce');

        if ($nonce === '' || ! \wp_verify_nonce($nonce, 'wp_rest')) {
            return self::response(['ok' => false, 'code' => 'invalid_nonce', 'message' => \__('نشست شما منقضی شده است؛ صفحه را تازه کنید.', BERYON_TD)], 403);
        }

        $type = (string) $request->get_param('type');

        if (! in_array($type, ['contact', 'feedback', 'agency', 'newsletter'], true)) {
            return self::response(['ok' => false, 'code' => 'invalid', 'message' => \__('نوع فرم نامعتبر است.', BERYON_TD)], 400);
        }

        // ۲) honeypot
        $honeypot_field = (string) Settings::opt('forms.honeypot_field', 'website_url');
        $honeypot       = (string) $request->get_param('hp') . (string) $request->get_param($honeypot_field);

        if ((bool) Settings::opt('forms.honeypot', true) && trim($honeypot) !== '') {
            // پاسخ موفق جعلی (ربات متوجه نشود) اما چیزی ذخیره نمی‌شود.
            return self::response(['ok' => true, 'ref' => self::tracking_code(), 'message' => \__('پیام شما ثبت شد.', BERYON_TD), 'silent' => true], 201);
        }

        // ۳) نرخ درخواست
        $limited = self::rate_limited();

        if ($limited > 0) {
            return self::response(['ok' => false, 'code' => 'rate_limited', 'retry_after' => $limited, 'message' => \__('تعداد درخواست‌ها بیش از حد مجاز است؛ کمی بعد تلاش کنید.', BERYON_TD)], 429);
        }

        // ۴) نرمال‌سازی و اعتبارسنجی
        $data   = self::collect($request, $type);
        $errors = self::validate($data, $type);

        if ($errors !== []) {
            return self::response(['ok' => false, 'code' => 'invalid', 'errors' => $errors, 'message' => \__('برخی فیلدها نیاز به اصلاح دارند.', BERYON_TD)], 422);
        }

        // ۵) ذخیره
        $ref = self::tracking_code();
        $id  = self::insert($data, $ref, $type);

        if ($id === 0) {
            return self::response(['ok' => false, 'code' => 'db_error', 'message' => \__('ثبت پیام ناموفق بود؛ لطفاً دوباره تلاش کنید.', BERYON_TD)], 500);
        }

        // ۶) اطلاع‌رسانی
        if ((bool) Settings::opt('forms.notify', true)) {
            self::notify($data, $ref, $type);
        }

        self::rate_hit();

        /**
         * پس از ثبت موفق پیام.
         *
         * @param int    $id   شناسه رکورد
         * @param array  $data داده‌ها
         * @param string $ref  کد پیگیری
         * @param string $type نوع فرم
         */
        \do_action('beryon/form/submitted', $id, $data, $ref, $type);

        $messages = [
            'contact'   => \__('پیام شما ارسال شد؛ کارشناسان ما در اولین فرصت کاری تماس می‌گیرند.', BERYON_TD),
            'feedback'  => (string) Settings::opt('feedback.success_text', \__('پیام شما ثبت شد.', BERYON_TD)),
            'agency'    => (string) Settings::opt('agency.success_text', \__('درخواست شما ثبت شد.', BERYON_TD)),
            'newsletter' => (string) Settings::opt('blog.newsletter_success_text', \__('عضویت شما ثبت شد.', BERYON_TD)),
        ];

        return self::response(['ok' => true, 'ref' => $ref, 'message' => $messages[$type] ?? $messages['contact'], 'id' => $id], 201);
    }

    /**
     * جمع‌آوری و پاک‌سازی ورودی‌ها.
     *
     * @param \WP_REST_Request $request درخواست
     * @param string           $type    نوع فرم
     * @return array<string,mixed>
     */
    private static function collect(\WP_REST_Request $request, string $type): array
    {
        $first = \sanitize_text_field((string) $request->get_param('first_name'));
        $last  = \sanitize_text_field((string) $request->get_param('last_name'));
        $name  = \sanitize_text_field((string) $request->get_param('name'));

        if ($name === '' && ($first !== '' || $last !== '')) {
            $name = trim($first . ' ' . $last);
        }

        $tel = \sanitize_text_field((string) $request->get_param('tel'));
        $tel = preg_replace('/[^\d+]/', '', \beryon_en_digits($tel)) ?? '';

        $message = (string) $request->get_param('message');
        $message = \wp_strip_all_tags($message);
        $message = \mb_substr(trim($message), 0, (int) Settings::opt('forms.max_message_length', 4000));

        // رشته یا آرایه (چک‌باکس چندگزینه‌ای)؛ همیشه به یک رشته خوانا تبدیل می‌شود.
        $sales_types = self::sanitize_choices($request->get_param('sales_types'));

        $data = [
            'name'    => $name,
            'tel'     => $tel,
            'email'   => \sanitize_email((string) $request->get_param('email')),
            'subject' => \sanitize_text_field((string) $request->get_param('subject')),
            'message' => $message,
            'page'    => esc_url_raw((string) $request->get_param('url')),
            'type'    => $type,
        ];

        $data['payload'] = [
            'ptype'                => \sanitize_key((string) $request->get_param('ptype')),
            'department'           => \sanitize_text_field((string) $request->get_param('department')),
            'company'              => \sanitize_text_field((string) $request->get_param('company')),
            'province'             => \sanitize_text_field((string) $request->get_param('province')),
            'city'                 => \sanitize_text_field((string) $request->get_param('city')),
            'work_tel'             => \sanitize_text_field((string) $request->get_param('work_tel')),
            'address'              => \sanitize_text_field((string) $request->get_param('address')),
            'work_area'            => \sanitize_text_field((string) $request->get_param('work_area')),
            'work_ownership'       => \sanitize_text_field((string) $request->get_param('work_ownership')),
            'warehouse_address'    => \sanitize_text_field((string) $request->get_param('warehouse_address')),
            'warehouse_tel'        => \sanitize_text_field((string) $request->get_param('warehouse_tel')),
            'warehouse_area'       => \sanitize_text_field((string) $request->get_param('warehouse_area')),
            'warehouse_ownership'  => \sanitize_text_field((string) $request->get_param('warehouse_ownership')),
            'sales_types'          => $sales_types,
            'visitors'             => \sanitize_text_field((string) $request->get_param('visitors')),
            'vehicles'             => \sanitize_text_field((string) $request->get_param('vehicles')),
            'coverage'             => \sanitize_text_field((string) $request->get_param('coverage')),
            'agree'                => (string) $request->get_param('agree'),
        ];

        return $data;
    }

    /**
     * اعتبارسنجی بر اساس نوع فرم.
     *
     * @param array<string,mixed> $data داده‌ها
     * @param string              $type نوع فرم
     * @return array<string,array<int,string>>
     */
    private static function validate(array $data, string $type): array
    {
        $errors   = [];
        $payload  = (array) $data['payload'];
        $min      = (int) Settings::opt('forms.min_message_length', 10);

        if ($type === 'newsletter') {
            if (! \is_email((string) $data['email'])) {
                $errors['email'] = [\__('ایمیل معتبر وارد کنید.', BERYON_TD)];
            }

            return $errors;
        }

        if (trim((string) $data['name']) === '') {
            $errors['name'] = [\__('لطفاً نام خود را وارد کنید.', BERYON_TD)];
        }

        if (! self::valid_tel((string) $data['tel'])) {
            $errors['tel'] = [\__('شماره همراه معتبر وارد کنید (مثال: 09123456789).', BERYON_TD)];
        }

        if ($data['email'] !== '' && ! \is_email((string) $data['email'])) {
            $errors['email'] = [\__('ایمیل معتبر وارد کنید.', BERYON_TD)];
        }

        if ($type !== 'agency' && \mb_strlen((string) $data['message']) < $min) {
            $errors['message'] = [\__('لطفاً متن پیام را بنویسید.', BERYON_TD)];
        }

        if ($type === 'agency') {
            if (empty($payload['province'])) {
                $errors['province'] = [\__('استان را انتخاب کنید.', BERYON_TD)];
            }

            if (trim((string) $payload['city']) === '') {
                $errors['city'] = [\__('شهر الزامی است.', BERYON_TD)];
            }

            if (trim((string) $payload['address']) === '') {
                $errors['address'] = [\__('آدرس الزامی است.', BERYON_TD)];
            }

            if (trim((string) $payload['coverage']) === '') {
                $errors['coverage'] = [\__('شهرهای تحت پوشش را بنویسید.', BERYON_TD)];
            }

            if (empty($payload['sales_types'])) {
                $errors['sales_types'] = [\__('نوع فروش را انتخاب کنید.', BERYON_TD)];
            }

            if (empty($payload['agree'])) {
                $errors['agree'] = [\__('تأیید صحت اطلاعات الزامی است.', BERYON_TD)];
            }
        }

        /**
         * فیلتر خطاهای اعتبارسنجی.
         *
         * @param array  $errors
         * @param array  $data
         * @param string $type
         */
        return (array) \apply_filters('beryon/form/errors', $errors, $data, $type);
    }

    /**
     * اعتبارسنجی شماره همراه ایران.
     *
     * @param string $tel شماره
     * @return bool
     */
    public static function valid_tel(string $tel): bool
    {
        $digits = preg_replace('/\D/', '', $tel) ?? '';

        if (str_starts_with($digits, '98')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        }

        return (bool) preg_match('/^0?9\d{9}$/', $digits);
    }

    /**
     * ذخیره رکورد.
     *
     * @param array<string,mixed> $data داده‌ها
     * @param string              $ref  کد پیگیری
     * @param string              $type نوع فرم
     * @return int
     */
    private static function insert(array $data, string $ref, string $type): int
    {
        global $wpdb;

        self::maybe_install_table();

        $subject = (string) $data['subject'];

        if ($subject === '' && ! empty($data['payload']['ptype'])) {
            $labels = [
                'praise'    => \__('تقدیر و تشکر', BERYON_TD),
                'idea'      => \__('پیشنهاد', BERYON_TD),
                'critique'  => \__('انتقاد', BERYON_TD),
                'complaint' => \__('شکایت', BERYON_TD),
            ];

            $subject = $labels[(string) $data['payload']['ptype']] ?? '';
        }

        if ($subject === '' && ! empty($data['payload']['department'])) {
            $subject = (string) $data['payload']['department'];
        }

        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            self::table(),
            [
                'ref_code'   => $ref,
                'form_type'  => $type,
                'name'       => \mb_substr((string) $data['name'], 0, 190),
                'tel'        => \mb_substr((string) $data['tel'], 0, 32),
                'email'      => \mb_substr((string) $data['email'], 0, 190),
                'subject'    => \mb_substr($subject, 0, 190),
                'message'    => (string) $data['message'],
                'payload'    => (string) \wp_json_encode($data['payload'], JSON_UNESCAPED_UNICODE),
                'status'     => 'new',
                'ip'         => self::client_ip(),
                'user_agent' => \mb_substr(self::server_var('HTTP_USER_AGENT'), 0, 255),
                'page_url'   => \mb_substr((string) $data['page'], 0, 255),
                'created_at' => \current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return $inserted === false ? 0 : (int) $wpdb->insert_id;
    }

    /**
     * کد پیگیری یکتا: BRY-{سال شمسی}-{۵ رقم}
     *
     * @return string
     */
    public static function tracking_code(): string
    {
        $prefix = (string) Settings::opt('forms.track_prefix', 'BRY');
        $year   = Jalali::currentYear();
        global $wpdb;

        self::maybe_install_table();

        $table  = self::table();
        $code   = '';

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $candidate = sprintf('%s-%d-%05d', $prefix, $year, \wp_rand(10000, 99999));

            // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE ref_code = %s LIMIT 1", $candidate));
            // phpcs:enable

            if ($exists === null) {
                $code = $candidate;
                break;
            }

            $code = $candidate;
        }

        return $code;
    }

    /**
     * ارسال ایمیل اطلاع‌رسانی.
     *
     * @param array<string,mixed> $data داده‌ها
     * @param string              $ref  کد پیگیری
     * @param string              $type نوع فرم
     * @return void
     */
    private static function notify(array $data, string $ref, string $type): void
    {
        $to = (string) Settings::opt('forms.notify_email', (string) \get_option('admin_email'));

        if (! \is_email($to)) {
            return;
        }

        $titles = [
            'contact'    => \__('فرم تماس', BERYON_TD),
            'feedback'   => \__('نظر/شکایت', BERYON_TD),
            'agency'     => \__('درخواست نمایندگی', BERYON_TD),
            'newsletter' => \__('عضویت خبرنامه', BERYON_TD),
        ];

        $subject = sprintf('[بریون] %s — %s', $titles[$type] ?? $type, $ref);
        $lines   = [
            \__('کد پیگیری:', BERYON_TD) . ' ' . $ref,
            \__('نوع فرم:', BERYON_TD) . ' ' . ($titles[$type] ?? $type),
            \__('نام:', BERYON_TD) . ' ' . $data['name'],
            \__('تلفن:', BERYON_TD) . ' ' . $data['tel'],
            \__('ایمیل:', BERYON_TD) . ' ' . $data['email'],
        ];

        foreach ((array) $data['payload'] as $key => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $lines[] = $key . ': ' . $value;
            }
        }

        $lines[] = \__('پیام:', BERYON_TD) . "\n" . $data['message'];
        $lines[] = \__('صفحه:', BERYON_TD) . ' ' . $data['page'];

        $headers = ['Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $data['email']];

        \wp_mail($to, $subject, implode("\n", $lines), $headers);
    }

    /**
     * محدودسازی نرخ درخواست بر پایه IP.
     *
     * @return int ثانیه‌های باقی‌مانده (۰ = مجاز)
     */
    private static function rate_limited(): int
    {
        $limit  = (int) Settings::opt('forms.rate_limit', 5);
        $window = (int) Settings::opt('forms.rate_window', 300);

        if ($limit < 1) {
            return 0;
        }

        $key   = 'beryon_rl_' . substr(md5(self::client_ip()), 0, 24);
        $hits  = (int) \get_transient($key);

        return $hits >= $limit ? $window : 0;
    }

    /**
     * ثبت یک درخواست در شمارنده نرخ.
     *
     * @return void
     */
    private static function rate_hit(): void
    {
        $limit  = (int) Settings::opt('forms.rate_limit', 5);
        $window = (int) Settings::opt('forms.rate_window', 300);

        if ($limit < 1) {
            return;
        }

        $key  = 'beryon_rl_' . substr(md5(self::client_ip()), 0, 24);
        $hits = (int) \get_transient($key);

        \set_transient($key, $hits + 1, $window);
    }

    /**
     * پیگیری وضعیت پیام با کد.
     *
     * @param \WP_REST_Request $request درخواست
     * @return \WP_REST_Response
     */
    public static function track(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        self::maybe_install_table();

        $table = self::table();
        $code  = \sanitize_text_field((string) $request->get_param('code'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT ref_code, form_type, status, created_at FROM {$table} WHERE ref_code = %s", $code), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        if (! is_array($row)) {
            return self::response(['ok' => false, 'code' => 'not_found', 'message' => \__('کد پیگیری یافت نشد.', BERYON_TD)], 404);
        }

        $statuses = [
            'new'       => \__('ثبت شد — در صف بررسی', BERYON_TD),
            'read'      => \__('بررسی شد', BERYON_TD),
            'answered'  => \__('پاسخ داده شد', BERYON_TD),
            'archived'  => \__('بایگانی شد', BERYON_TD),
        ];

        return self::response(
            [
                'ok'          => true,
                'ref'         => (string) $row['ref_code'],
                'type'        => (string) $row['form_type'],
                'status'      => (string) $row['status'],
                'status_text' => $statuses[(string) $row['status']] ?? (string) $row['status'],
                'created_at'  => Jalali::format((int) strtotime((string) \get_date_from_gmt((string) $row['created_at'], 'Y-m-d H:i:s')), 'Y/m/d H:i'),
            ]
        );
    }

    /**
     * آمار صندوق پیام‌ها (برای داشبورد).
     *
     * @return array<string,int>
     */
    public static function stats(): array
    {
        global $wpdb;

        self::maybe_install_table();

        $table = self::table();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A);
        // phpcs:enable
        $out  = ['all' => 0, 'new' => 0, 'read' => 0, 'answered' => 0, 'archived' => 0];

        foreach ((array) $rows as $row) {
            $out[(string) $row['status']] = (int) $row['total'];
            $out['all']                  += (int) $row['total'];
        }

        return $out;
    }

    /**
     * ساخت پاسخ REST.
     *
     * @param array<string,mixed> $data   داده
     * @param int                 $status کد وضعیت
     * @return \WP_REST_Response
     */
    private static function response(array $data, int $status = 200): \WP_REST_Response
    {
        return new \WP_REST_Response($data, $status);
    }

    /**
     * آدرس IP کلاینت (با احترام به پروکسی معتبر).
     *
     * @return string
     */
    public static function client_ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $value = self::server_var($key);

            if ($value === '') {
                continue;
            }

            $first = trim(explode(',', $value)[0]);

            if (\filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return '';
    }

    /**
     * خواندن امن یک متغیر سرور.
     *
     * @param string $key نام
     * @return string
     */
    public static function server_var(string $key): string
    {
        if (! isset($_SERVER[$key])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            return '';
        }

        return \sanitize_text_field(\wp_unslash($_SERVER[$key])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    }
}
