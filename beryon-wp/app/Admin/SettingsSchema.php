<?php

/**
 * طرح (Schema) فیلدهای پنل تنظیمات قالب بریون
 * ------------------------------------------------------------------
 * هر تب = یک گروه از تنظیمات؛ هر فیلد با «گروه.نام» به Settings::opt نگاشت می‌شود.
 * این فایل تنها توصیف UI است؛ مقادیر پیش‌فرض در config/defaults.json نگهداری می‌شوند
 * تا با Patch قابل به‌روزرسانی باشند.
 *
 * @package beryon
 */

namespace App\Admin;

defined('ABSPATH') || exit;

/**
 * کلاس طرح فیلدها.
 */
final class SettingsSchema
{
    /**
     * تعریف کامل تب‌ها و فیلدها.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        static $building = false;

        // محافظ بازگشت: ساخت تب «پیشرفته» خودش به فهرست فیلدهای اعلام‌شده
        // نیاز دارد و آن را از همین متد می‌خواند.
        if ($building) {
            return [];
        }

        $building = true;

        $schema = [
            'general' => [
                'label'  => \__('عمومی و برند', BERYON_TD),
                'intro'  => \__('اطلاعات برند، تماس، شبکه‌های اجتماعی و پانوشت — در همه صفحات استفاده می‌شود. توکن‌های <code>{brand}</code>، <code>{company}</code>، <code>{count}</code>، <code>{year}</code> و <code>{site_name}</code> در متن‌ها جایگزین می‌شوند.', BERYON_TD),
                'fields' => self::general(),
            ],
            'home' => [
                'label'  => \__('صفحه اصلی', BERYON_TD),
                'intro'  => \__('بخش‌های قهرمان (Hero)، دسته‌ها، مزیت‌ها، درباره، آمار، محصولات ویژه، افتخارات، وبلاگ و فراخوان نمایندگی.', BERYON_TD),
                'fields' => self::home(),
            ],
            'products' => [
                'label'  => \__('محصولات و کاتالوگ', BERYON_TD),
                'intro'  => \__('رفتار آرشیو محصولات، صفحه تکی، فیلترها و صفحه کاتالوگ.', BERYON_TD),
                'fields' => self::products(),
            ],
            'pages' => [
                'label'  => \__('درباره، مدیرعامل و چارت', BERYON_TD),
                'intro'  => \__('محتوای صفحات معرفی شرکت، مدیرعامل، چارت سازمانی و گواهینامه‌ها.', BERYON_TD),
                'fields' => self::pages(),
            ],
            'messages' => [
                'label'  => \__('فرم‌ها و پیام‌ها', BERYON_TD),
                'intro'  => \__('تنظیمات فرم تماس، نظرات و شکایات، نمایندگی، خبرنامه و پیام‌های موفقیت.', BERYON_TD),
                'fields' => self::messages(),
            ],
            'blog' => [
                'label'  => \__('وبلاگ', BERYON_TD),
                'intro'  => \__('تنظیمات مجله بریان گوشت، سایدبار و خبرنامه.', BERYON_TD),
                'fields' => self::blog(),
            ],
            'seo' => [
                'label'  => \__('سئو پیشرفته', BERYON_TD),
                'intro'  => \__('عنوان، توضیحات، Open Graph، داده ساخت‌یافته (JSON-LD) و نقشه سایت XML. اگر افزونه سئو (Yoast / Rank Math / AIOSEO) فعال باشد، این بخش به‌صورت خودکار غیرفعال می‌شود تا تداخل ایجاد نگردد.', BERYON_TD),
                'fields' => self::seo(),
            ],
            'performance' => [
                'label'  => \__('عملکرد و بهینه‌سازی', BERYON_TD),
                'intro'  => \__('کاهش حجم DOM، بارگذاری تنبل تصاویر، حذف اسکریپت‌های اضافی وردپرس، پیش‌بارگذاری و میزبانی محلی فونت.', BERYON_TD),
                'fields' => self::performance(),
            ],
            'security' => [
                'label'  => \__('امنیت', BERYON_TD),
                'intro'  => \__('هدرهای امنیتی، CSP، پنهان‌سازی نسخه و پایش یکپارچگی فایل‌های هسته.', BERYON_TD),
                'fields' => self::security(),
            ],
            'updater' => [
                'label'  => \__('به‌روزرسانی (Patch)', BERYON_TD),
                'intro'  => \__('منبع به‌روزرسانی، کانال انتشار، محدودیت‌های امنیتی بسته پچ و تعداد پشتیبان‌های نگهداری‌شده. صفحه کامل نصب و بازگردانی: <a href="' . esc_url(\admin_url('admin.php?page=beryon-updates')) . '">به‌روزرسانی (Patch)</a>', BERYON_TD),
                'fields' => self::updater(),
            ],
        ];

        // تب «پیشرفته»: هر کلیدی از config/defaults.json که فیلد صریح ندارد،
        // به‌صورت خودکار اینجا قابل ویرایش می‌شود (بدون نیاز به کدنویسی).
        $advanced = self::advanced();

        if ($advanced !== []) {
            $schema['advanced'] = [
                'label'  => \__('پیشرفته (همه کلیدها)', BERYON_TD),
                'intro'  => \__('فهرست خودکار کلیدهای تنظیماتی که در تب‌های بالا فیلد اختصاصی ندارند: برچسب مسیرها (Breadcrumb)، متن‌های موفقیت فرم‌ها، متن‌های آرشیو، فهرست استان‌ها و… مقدار خالی یعنی استفاده از پیش‌فرض <code>config/defaults.json</code>.', BERYON_TD),
                'fields' => $advanced,
            ];
        }

        $building = false;

        /**
         * فیلتر طرح پنل تنظیمات (افزودن تب سفارشی توسط افزونه‌ها).
         *
         * @param array $schema طرح
         */
        return (array) \apply_filters('beryon/settings/schema', $schema);
    }

    /**
     * کلیدهای «گروه.نام» که در تب‌های صریح اعلام شده‌اند.
     *
     * @return array<string,bool>
     */
    private static function declared(): array
    {
        static $declared = null;

        if ($declared !== null) {
            return $declared;
        }

        $declared = [];

        // مهم: این فهرست مستقیماً از سازنده‌های تب‌ها خوانده می‌شود و نه از
        // self::all() — چون all() خودش advanced() را صدا می‌زند و فراخوانی
        // بازگشتی، فهرست را خالی برمی‌گرداند (در نتیجه تب «پیشرفته» همه
        // کلیدها را تکرار می‌کرد).
        foreach (self::tabMethods() as $method) {
            foreach ((array) self::$method() as $field) {
                $group = (string) ($field['group'] ?? '');
                $name  = (string) ($field['name'] ?? '');

                if ($group !== '' && $name !== '') {
                    $declared[$group . '.' . $name] = true;
                }
            }
        }

        return $declared;
    }

    /**
     * نام متدهای سازنده تب‌های صریح (بدون تب خودکار «پیشرفته»).
     *
     * @return array<int,string>
     */
    private static function tabMethods(): array
    {
        return [
            'general',
            'home',
            'products',
            'pages',
            'messages',
            'blog',
            'seo',
            'performance',
            'security',
            'updater',
        ];
    }

    /**
     * برچسب فارسی کلیدهای پرکاربرد (برای تب پیشرفته).
     *
     * @return array<string,string>
     */
    private static function advanced_labels(): array
    {
        return [
            'crumb'                  => \__('برچسب در مسیر صفحه (Breadcrumb)', BERYON_TD),
            'sr'                     => \__('عنوان پنهان برای صفحه‌خوان', BERYON_TD),
            'empty'                  => \__('متن «موردی یافت نشد»', BERYON_TD),
            'title'                  => \__('عنوان', BERYON_TD),
            'lead'                   => \__('متن معرفی (Lead)', BERYON_TD),
            'more_label'             => \__('برچسب «ادامه مطلب»', BERYON_TD),
            'photo'                  => \__('نام فایل تصویر در پوشه assets/images قالب', BERYON_TD),
            'photo_alt'              => \__('متن جایگزین تصویر', BERYON_TD),
            'logo_file'              => \__('نام فایل لوگو (پیش‌فرض قالب)', BERYON_TD),
            'archive_crumb'          => \__('برچسب محصولات در مسیر صفحه', BERYON_TD),
            'archive_lead'           => \__('متن معرفی آرشیو محصولات', BERYON_TD),
            'archive_sr'             => \__('عنوان پنهان فهرست محصولات', BERYON_TD),
            'default_keep'           => \__('پیش‌فرض تب «نگهداری و مصرف» (هر خط یک مورد)', BERYON_TD),
            'other_cats_tag'         => \__('برچسب بخش «گروه‌های دیگر»', BERYON_TD),
            'other_cats_title'       => \__('عنوان بخش «گروه‌های دیگر»', BERYON_TD),
            'sample_note'            => \__('یادداشت زیر عنوان در هیرو محصول', BERYON_TD),
            'search_aria'            => \__('برچسب دسترس‌پذیر جست‌وجو', BERYON_TD),
            'search_placeholder'     => \__('متن راهنمای جست‌وجو', BERYON_TD),
            'success_title'          => \__('عنوان پیام موفقیت فرم', BERYON_TD),
            'success_text'           => \__('متن پیام موفقیت فرم', BERYON_TD),
            'agree_text'             => \__('متن بند تأیید (چک‌باکس)', BERYON_TD),
            'cta_title'              => \__('عنوان فراخوان اقدام', BERYON_TD),
            'cta_text'               => \__('متن فراخوان اقدام', BERYON_TD),
            'author_box_title'       => \__('عنوان جعبه نویسنده', BERYON_TD),
            'provinces'              => \__('فهرست استان‌ها (هر خط یک استان)', BERYON_TD),
            'minify_notice'          => \__('یادآوری فشرده‌سازی دارایی‌ها', BERYON_TD),
            'newsletter_title'       => \__('عنوان خبرنامه', BERYON_TD),
            'newsletter_text'        => \__('متن خبرنامه', BERYON_TD),
            'newsletter_btn'         => \__('برچسب دکمه خبرنامه', BERYON_TD),
            'newsletter_success_title' => \__('عنوان موفقیت خبرنامه', BERYON_TD),
            'newsletter_success_text'  => \__('متن موفقیت خبرنامه', BERYON_TD),
        ];
    }

    /**
     * ساخت خودکار فیلدهای تب پیشرفته از config/defaults.json.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function advanced(): array
    {
        $defaults = \App\Settings::defaults();
        $labels   = self::advanced_labels();
        $declared = self::declared();
        $fields   = [];
        $newline  = "\n";

        foreach ($defaults as $group => $values) {
            if (! is_string($group) || strpos($group, '_') === 0 || ! is_array($values)) {
                continue;
            }

            foreach ($values as $name => $value) {
                if (! is_string($name) || strpos($name, '_') === 0) {
                    continue;
                }

                $key = $group . '.' . $name;

                // کلیدهایی که فیلد صریح دارند یا ردیف‌ساز (repeater) هستند
                if (isset($declared[$key])) {
                    continue;
                }

                if (is_array($value) && $value !== [] && is_array(reset($value))) {
                    continue;
                }

                $label = isset($labels[$name])
                    ? $labels[$name]
                    : \sprintf(\__('کلید %1$s (گروه %2$s)', BERYON_TD), $name, $group);

                $help = \sprintf(\__('کلید: <code>%s</code>', BERYON_TD), $key);

                if (is_array($value)) {
                    $fields[] = [
                        'group' => $group,
                        'name'  => $name,
                        'label' => $label,
                        'type'  => 'lines',
                        'help'  => $help . ' — ' . \__('هر خط یک مورد.', BERYON_TD),
                    ];

                    continue;
                }

                $text  = (string) $value;
                $multi = mb_strlen($text) > 90 || strpos($text, $newline) !== false;

                $fields[] = [
                    'group' => $group,
                    'name'  => $name,
                    'label' => $label,
                    'type'  => $multi ? 'textarea' : 'text',
                    'help'  => $help,
                ];
            }
        }

        usort($fields, static function (array $a, array $b): int {
            return [$a['group'], $a['name']] <=> [$b['group'], $b['name']];
        });

        return $fields;
    }

    /**
     * تب عمومی.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function general(): array
    {
        return [
            ['group' => 'general', 'name' => 'brand_name', 'label' => \__('نام برند', BERYON_TD), 'type' => 'text'],
            ['group' => 'general', 'name' => 'brand_latin', 'label' => \__('نام لاتین برند', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
            ['group' => 'general', 'name' => 'company_name', 'label' => \__('نام رسمی شرکت', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'general', 'name' => 'logo_id', 'label' => \__('لوگو (کتابخانه رسانه)', BERYON_TD), 'type' => 'image', 'fallback' => 'logo.png'],
            ['group' => 'general', 'name' => 'og_image', 'label' => \__('نام تصویر پیش‌فرض اشتراک‌گذاری', BERYON_TD), 'type' => 'text', 'dir' => 'ltr', 'help' => \__('فایل داخل پوشه assets/images قالب، مثال: hero.webp', BERYON_TD)],
            ['group' => 'general', 'name' => 'theme_color', 'label' => \__('رنگ برند (theme-color)', BERYON_TD), 'type' => 'color'],

            ['group' => 'general', 'type' => 'heading', 'label' => \__('راه‌های تماس', BERYON_TD)],
            ['group' => 'general', 'name' => 'phone_factory', 'label' => \__('تلفن کارخانه (href)', BERYON_TD), 'type' => 'tel'],
            ['group' => 'general', 'name' => 'phone_factory_label', 'label' => \__('تلفن کارخانه (نمایشی)', BERYON_TD), 'type' => 'text'],
            ['group' => 'general', 'name' => 'phone_admin', 'label' => \__('تلفن اداری (href)', BERYON_TD), 'type' => 'tel'],
            ['group' => 'general', 'name' => 'phone_admin_label', 'label' => \__('تلفن اداری (نمایشی)', BERYON_TD), 'type' => 'text'],
            ['group' => 'general', 'name' => 'phone_sales', 'label' => \__('تلفن فروش (href)', BERYON_TD), 'type' => 'tel'],
            ['group' => 'general', 'name' => 'phone_sales_label', 'label' => \__('تلفن فروش (نمایشی)', BERYON_TD), 'type' => 'text'],
            ['group' => 'general', 'name' => 'email', 'label' => \__('ایمیل عمومی', BERYON_TD), 'type' => 'email'],
            ['group' => 'general', 'name' => 'address', 'label' => \__('آدرس کوتاه', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'general', 'name' => 'address_full', 'label' => \__('آدرس کامل', BERYON_TD), 'type' => 'textarea', 'rows' => 3],
            ['group' => 'general', 'name' => 'map_url', 'label' => \__('لینک نقشه', BERYON_TD), 'type' => 'url'],
            ['group' => 'general', 'name' => 'work_hours', 'label' => \__('ساعات کاری', BERYON_TD), 'type' => 'text', 'wide' => true],

            ['group' => 'general', 'type' => 'heading', 'label' => \__('شبکه‌های اجتماعی', BERYON_TD)],
            ['group' => 'general', 'name' => 'socials', 'label' => \__('لیست شبکه‌ها', BERYON_TD), 'type' => 'repeater', 'max' => 8, 'row' => [
                ['name' => 'label', 'label' => \__('نام', BERYON_TD), 'type' => 'text'],
                ['name' => 'icon', 'label' => \__('آیکون (svg sprite)', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
                ['name' => 'url', 'label' => \__('آدرس', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
            ]],

            ['group' => 'general', 'type' => 'heading', 'label' => \__('پانوشت', BERYON_TD)],
            ['group' => 'general', 'name' => 'footer_text', 'label' => \__('متن معرفی در پانوشت', BERYON_TD), 'type' => 'html', 'rows' => 4],
            ['group' => 'general', 'name' => 'footer_quick_title', 'label' => \__('عنوان ستون دسترسی سریع', BERYON_TD), 'type' => 'text'],
            ['group' => 'general', 'name' => 'footer_cats_title', 'label' => \__('عنوان ستون دسته‌ها', BERYON_TD), 'type' => 'text'],
            ['group' => 'general', 'name' => 'copyright', 'label' => \__('متن کپی‌رایت', BERYON_TD), 'type' => 'html', 'rows' => 2],
            ['group' => 'general', 'name' => 'credit', 'label' => \__('اعتبار طراح', BERYON_TD), 'type' => 'html', 'rows' => 2],
            ['group' => 'general', 'name' => 'agency_cta_label', 'label' => \__('برچسب دکمه نمایندگی (هدر)', BERYON_TD), 'type' => 'text'],
            ['group' => 'general', 'name' => 'date_format', 'label' => \__('قالب تاریخ شمسی', BERYON_TD), 'type' => 'text', 'dir' => 'ltr', 'help' => \__('Y/m/d یا d F Y', BERYON_TD)],
        ];
    }

    /**
     * تب صفحه اصلی.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function home(): array
    {
        return [
            ['group' => 'home', 'name' => 'transparent_header', 'label' => \__('هدر شفاف فقط در صفحه اصلی (دسکتاپ)', BERYON_TD), 'type' => 'checkbox'],

            ['group' => 'home', 'type' => 'heading', 'label' => \__('بخش قهرمان (Hero)', BERYON_TD)],
            ['group' => 'home', 'name' => 'hero_badge', 'label' => \__('برچسب بالای عنوان', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'hero_title_1', 'label' => \__('عنوان — بخش ۱', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'home', 'name' => 'hero_title_hl', 'label' => \__('عنوان — بخش هایلایت', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'home', 'name' => 'hero_title_2', 'label' => \__('عنوان — بخش ۲', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'home', 'name' => 'hero_lead', 'label' => \__('متن معرفی', BERYON_TD), 'type' => 'textarea', 'rows' => 3],
            ['group' => 'home', 'name' => 'hero_image', 'label' => \__('نام تصویر قهرمان', BERYON_TD), 'type' => 'text', 'dir' => 'ltr', 'help' => \__('webp داخل assets/images', BERYON_TD)],
            ['group' => 'home', 'name' => 'hero_image_alt', 'label' => \__('متن جایگزین تصویر', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'home', 'name' => 'hero_image_w', 'label' => \__('عرض تصویر (px)', BERYON_TD), 'type' => 'number', 'min' => 200, 'max' => 2000],
            ['group' => 'home', 'name' => 'hero_image_h', 'label' => \__('ارتفاع تصویر (px)', BERYON_TD), 'type' => 'number', 'min' => 200, 'max' => 2000],
            ['group' => 'home', 'name' => 'hero_btn_primary', 'label' => \__('دکمه اصلی', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'hero_btn_secondary', 'label' => \__('دکمه فرعی', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'hero_stats', 'label' => \__('آمارهای قهرمان', BERYON_TD), 'type' => 'repeater', 'max' => 4, 'row' => [
                ['name' => 'value', 'label' => \__('مقدار', BERYON_TD), 'type' => 'text'],
                ['name' => 'suffix', 'label' => \__('پسوند', BERYON_TD), 'type' => 'text'],
                ['name' => 'label', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text'],
            ]],
            ['group' => 'home', 'name' => 'hero_float_1', 'label' => \__('کارت شناور ۱ — عنوان', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'hero_float_1_sub', 'label' => \__('کارت شناور ۱ — زیرعنوان', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'hero_float_1_icon', 'label' => \__('کارت شناور ۱ — آیکون', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
            ['group' => 'home', 'name' => 'hero_float_2', 'label' => \__('کارت شناور ۲ — عنوان', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'hero_float_2_sub', 'label' => \__('کارت شناور ۲ — زیرعنوان', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'hero_float_2_icon', 'label' => \__('کارت شناور ۲ — آیکون', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],

            ['group' => 'home', 'type' => 'heading', 'label' => \__('دسته‌بندی محصولات', BERYON_TD)],
            ['group' => 'home', 'name' => 'cats_tag', 'label' => \__('برچسب', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'cats_title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'home', 'name' => 'cats_desc', 'label' => \__('توضیح', BERYON_TD), 'type' => 'textarea', 'rows' => 2],

            ['group' => 'home', 'type' => 'heading', 'label' => \__('چرا بریون؟', BERYON_TD)],
            ['group' => 'home', 'name' => 'features_tag', 'label' => \__('برچسب', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'features_title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'home', 'name' => 'features_desc', 'label' => \__('توضیح', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'home', 'name' => 'features', 'label' => \__('مزیت‌ها', BERYON_TD), 'type' => 'repeater', 'max' => 8, 'row' => [
                ['name' => 'icon', 'label' => \__('آیکون', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
                ['name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text'],
                ['name' => 'text', 'label' => \__('متن', BERYON_TD), 'type' => 'textarea'],
            ]],

            ['group' => 'home', 'type' => 'heading', 'label' => \__('درباره بریون (تیزر)', BERYON_TD)],
            ['group' => 'home', 'name' => 'about_tag', 'label' => \__('برچسب', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'about_title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'home', 'name' => 'about_image', 'label' => \__('نام تصویر', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
            ['group' => 'home', 'name' => 'about_image_alt', 'label' => \__('متن جایگزین', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'home', 'name' => 'about_paragraphs', 'label' => \__('پاراگراف‌ها', BERYON_TD), 'type' => 'lines', 'rows' => 4],
            ['group' => 'home', 'name' => 'about_checks', 'label' => \__('موارد تأیید', BERYON_TD), 'type' => 'lines', 'rows' => 4],
            ['group' => 'home', 'name' => 'about_exp_value', 'label' => \__('عدد تجربه', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'about_exp_label', 'label' => \__('عنوان تجربه', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'about_btn_1', 'label' => \__('دکمه ۱', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'about_btn_2', 'label' => \__('دکمه ۲', BERYON_TD), 'type' => 'text'],

            ['group' => 'home', 'type' => 'heading', 'label' => \__('نوار آمار', BERYON_TD)],
            ['group' => 'home', 'name' => 'stats_band', 'label' => \__('آمارها', BERYON_TD), 'type' => 'repeater', 'max' => 6, 'row' => [
                ['name' => 'value', 'label' => \__('مقدار', BERYON_TD), 'type' => 'text'],
                ['name' => 'suffix', 'label' => \__('پسوند', BERYON_TD), 'type' => 'text'],
                ['name' => 'label', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text'],
            ]],

            ['group' => 'home', 'type' => 'heading', 'label' => \__('محصولات ویژه', BERYON_TD)],
            ['group' => 'home', 'name' => 'featured_tag', 'label' => \__('برچسب', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'featured_title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'home', 'name' => 'featured_desc', 'label' => \__('توضیح', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'home', 'name' => 'featured_more', 'label' => \__('برچسب دکمه «مشاهده همه»', BERYON_TD), 'type' => 'text'],

            ['group' => 'home', 'type' => 'heading', 'label' => \__('افتخارات و وبلاگ', BERYON_TD)],
            ['group' => 'home', 'name' => 'certs_label', 'label' => \__('برچسب افتخارات', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'cert_pills', 'label' => \__('نشان‌های افتخار', BERYON_TD), 'type' => 'lines', 'rows' => 4],
            ['group' => 'home', 'name' => 'blog_tag', 'label' => \__('برچسب وبلاگ', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'blog_title', 'label' => \__('عنوان وبلاگ', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'home', 'name' => 'blog_desc', 'label' => \__('توضیح وبلاگ', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'home', 'name' => 'blog_count', 'label' => \__('تعداد مقاله در خانه', BERYON_TD), 'type' => 'number', 'min' => 1, 'max' => 6],

            ['group' => 'home', 'type' => 'heading', 'label' => \__('فراخوان نمایندگی (CTA)', BERYON_TD)],
            ['group' => 'home', 'name' => 'cta_title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'home', 'name' => 'cta_text', 'label' => \__('متن', BERYON_TD), 'type' => 'textarea', 'rows' => 3],
            ['group' => 'home', 'name' => 'cta_btn_1', 'label' => \__('دکمه ۱', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'cta_btn_2', 'label' => \__('دکمه ۲', BERYON_TD), 'type' => 'text'],
            ['group' => 'home', 'name' => 'cta_items', 'label' => \__('موارد CTA', BERYON_TD), 'type' => 'repeater', 'max' => 4, 'row' => [
                ['name' => 'icon', 'label' => \__('آیکون', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
                ['name' => 'text', 'label' => \__('متن', BERYON_TD), 'type' => 'textarea'],
            ]],
        ];
    }

    /**
     * تب محصولات.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function products(): array
    {
        return [
            ['group' => 'products', 'name' => 'source', 'label' => \__('منبع داده محصولات', BERYON_TD), 'type' => 'select', 'options' => [
                ['value' => 'auto', 'label' => \__('خودکار (ووکامرس در صورت نصب، در غیر این‌صورت CPT قالب)', BERYON_TD)],
                ['value' => 'woo', 'label' => \__('همیشه محصولات ووکامرس', BERYON_TD)],
                ['value' => 'cpt', 'label' => \__('همیشه CPT اختصاصی قالب', BERYON_TD)],
            ]],
            ['group' => 'products', 'name' => 'catalog_mode', 'label' => \__('حالت کاتالوگ/استعلام (بدون قیمت و سبد خرید)', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'products', 'name' => 'default_image', 'label' => \__('تصویر پیش‌فرض محصول', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
            ['group' => 'products', 'name' => 'per_page', 'label' => \__('تعداد محصول در هر صفحه آرشیو', BERYON_TD), 'type' => 'number', 'min' => 4, 'max' => 96],
            ['group' => 'products', 'name' => 'grid_limit', 'label' => \__('حداکثر محصول در گرید بدون صفحه‌بندی', BERYON_TD), 'type' => 'number', 'min' => 4, 'max' => 200],
            ['group' => 'products', 'name' => 'paginate', 'label' => \__('صفحه‌بندی آرشیو محصولات', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'products', 'name' => 'show_filters', 'label' => \__('نمایش فیلترهای دسته/برچسب', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'products', 'name' => 'show_search', 'label' => \__('نمایش جست‌وجوی زنده در آرشیو', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'products', 'name' => 'excerpt_length', 'label' => \__('طول خلاصه (کاراکتر)', BERYON_TD), 'type' => 'number', 'min' => 10, 'max' => 200],
            ['group' => 'products', 'name' => 'related_count', 'label' => \__('تعداد محصولات مرتبط', BERYON_TD), 'type' => 'number', 'min' => 0, 'max' => 12],
            ['group' => 'products', 'name' => 'inquiry_label', 'label' => \__('برچسب دکمه استعلام', BERYON_TD), 'type' => 'text'],
            ['group' => 'products', 'name' => 'more_label', 'label' => \__('برچسب «مشاهده محصول»', BERYON_TD), 'type' => 'text'],
            ['group' => 'products', 'name' => 'share_label', 'label' => \__('برچسب اشتراک‌گذاری', BERYON_TD), 'type' => 'text'],
            ['group' => 'products', 'name' => 'lightbox_label', 'label' => \__('برچسب نمایش بزرگ تصویر', BERYON_TD), 'type' => 'text'],

            ['group' => 'products', 'type' => 'heading', 'label' => \__('آرشیو محصولات', BERYON_TD)],
            ['group' => 'products', 'name' => 'archive_title', 'label' => \__('عنوان آرشیو', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'products', 'name' => 'archive_desc', 'label' => \__('توضیح آرشیو', BERYON_TD), 'type' => 'textarea', 'rows' => 3],
            ['group' => 'products', 'name' => 'archive_seo_html', 'label' => \__('متن سئویی انتهای آرشیو', BERYON_TD), 'type' => 'html', 'rows' => 8],
            ['group' => 'products', 'name' => 'archive_chips', 'label' => \__('چیپ‌های فیلتر آرشیو', BERYON_TD), 'type' => 'repeater', 'max' => 20, 'row' => [
                ['name' => 'value', 'label' => \__('مقدار فیلتر', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
                ['name' => 'label', 'label' => \__('برچسب', BERYON_TD), 'type' => 'text'],
            ]],
            ['group' => 'products', 'name' => 'no_result', 'label' => \__('پیام «نتیجه‌ای یافت نشد»', BERYON_TD), 'type' => 'text', 'wide' => true],

            ['group' => 'products', 'type' => 'heading', 'label' => \__('صفحه تکی محصول', BERYON_TD)],
            ['group' => 'products', 'name' => 'tab_desc', 'label' => \__('عنوان تب توضیحات', BERYON_TD), 'type' => 'text'],
            ['group' => 'products', 'name' => 'tab_spec', 'label' => \__('عنوان تب مشخصات', BERYON_TD), 'type' => 'text'],
            ['group' => 'products', 'name' => 'tab_keep', 'label' => \__('عنوان تب نگهداری', BERYON_TD), 'type' => 'text'],
            ['group' => 'products', 'name' => 'related_tag', 'label' => \__('برچسب محصولات مرتبط', BERYON_TD), 'type' => 'text'],
            ['group' => 'products', 'name' => 'related_title', 'label' => \__('عنوان محصولات مرتبط', BERYON_TD), 'type' => 'text'],
            ['group' => 'products', 'name' => 'default_spec_rows', 'label' => \__('ردیف‌های پیش‌فرض جدول مشخصات', BERYON_TD), 'type' => 'repeater', 'max' => 12, 'row' => [
                ['name' => 'label', 'label' => \__('عنوان ردیف', BERYON_TD), 'type' => 'text'],
                ['name' => 'meta', 'label' => \__('کلید متا', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
            ]],

            ['group' => 'products', 'type' => 'heading', 'label' => \__('صفحه کاتالوگ', BERYON_TD)],
            ['group' => 'catalog', 'name' => 'title', 'label' => \__('عنوان کاتالوگ', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'catalog', 'name' => 'lead', 'label' => \__('متن ابتدای صفحه', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'catalog', 'name' => 'cover_title', 'label' => \__('عنوان جلد', BERYON_TD), 'type' => 'text'],
            ['group' => 'catalog', 'name' => 'cover_sub', 'label' => \__('زیرعنوان جلد', BERYON_TD), 'type' => 'text'],
            ['group' => 'catalog', 'name' => 'cover_year', 'label' => \__('نسخه/سال جلد', BERYON_TD), 'type' => 'text'],
            ['group' => 'catalog', 'name' => 'badge', 'label' => \__('برچسب بالای صفحه', BERYON_TD), 'type' => 'text'],
            ['group' => 'catalog', 'name' => 'heading', 'label' => \__('عنوان بخش معرفی', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'catalog', 'name' => 'text', 'label' => \__('متن بخش معرفی', BERYON_TD), 'type' => 'textarea', 'rows' => 4],
            ['group' => 'catalog', 'name' => 'pdf_id', 'label' => \__('فایل PDF کاتالوگ (کتابخانه رسانه)', BERYON_TD), 'type' => 'media'],
            ['group' => 'catalog', 'name' => 'pdf_url', 'label' => \__('یا آدرس مستقیم PDF', BERYON_TD), 'type' => 'url'],
            ['group' => 'catalog', 'name' => 'flipbook_url', 'label' => \__('آدرس نسخه آنلاین (Flipbook)', BERYON_TD), 'type' => 'url'],
            ['group' => 'catalog', 'name' => 'download_label', 'label' => \__('برچسب دانلود', BERYON_TD), 'type' => 'text'],
            ['group' => 'catalog', 'name' => 'download_pending', 'label' => \__('برچسب «به‌زودی»', BERYON_TD), 'type' => 'text'],
            ['group' => 'catalog', 'name' => 'online_label', 'label' => \__('برچسب مشاهده آنلاین', BERYON_TD), 'type' => 'text'],
            ['group' => 'catalog', 'name' => 'cta_title', 'label' => \__('عنوان CTA', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'catalog', 'name' => 'cta_text', 'label' => \__('متن CTA', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'catalog', 'name' => 'cta_btn', 'label' => \__('دکمه CTA', BERYON_TD), 'type' => 'text'],
        ];
    }

    /**
     * تب صفحات معرفی.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function pages(): array
    {
        return [
            ['group' => 'about', 'type' => 'heading', 'label' => \__('صفحه درباره بریون', BERYON_TD)],
            ['group' => 'about', 'name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'about', 'name' => 'lead', 'label' => \__('متن ابتدایی', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'about', 'name' => 'image', 'label' => \__('نام تصویر کارخانه', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
            ['group' => 'about', 'name' => 'image_alt', 'label' => \__('متن جایگزین', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'about', 'name' => 'exp_value', 'label' => \__('عدد شاخص', BERYON_TD), 'type' => 'text'],
            ['group' => 'about', 'name' => 'exp_label', 'label' => \__('عنوان شاخص', BERYON_TD), 'type' => 'text'],
            ['group' => 'about', 'name' => 'tag', 'label' => \__('برچسب تاریخچه', BERYON_TD), 'type' => 'text'],
            ['group' => 'about', 'name' => 'heading', 'label' => \__('عنوان تاریخچه', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'about', 'name' => 'paragraphs', 'label' => \__('پاراگراف‌ها', BERYON_TD), 'type' => 'lines', 'rows' => 5],
            ['group' => 'about', 'name' => 'checks', 'label' => \__('موارد تأیید', BERYON_TD), 'type' => 'lines', 'rows' => 4],
            ['group' => 'about', 'name' => 'timeline_tag', 'label' => \__('برچسب گاه‌شمار', BERYON_TD), 'type' => 'text'],
            ['group' => 'about', 'name' => 'timeline_title', 'label' => \__('عنوان گاه‌شمار', BERYON_TD), 'type' => 'text'],
            ['group' => 'about', 'name' => 'timeline_desc', 'label' => \__('توضیح گاه‌شمار', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'about', 'name' => 'timeline', 'label' => \__('رویدادهای گاه‌شمار', BERYON_TD), 'type' => 'repeater', 'max' => 12, 'row' => [
                ['name' => 'year', 'label' => \__('سال', BERYON_TD), 'type' => 'text'],
                ['name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text'],
                ['name' => 'text', 'label' => \__('متن', BERYON_TD), 'type' => 'textarea'],
            ]],
            ['group' => 'about', 'name' => 'cards_tag', 'label' => \__('برچسب کارت‌ها', BERYON_TD), 'type' => 'text'],
            ['group' => 'about', 'name' => 'cards_title', 'label' => \__('عنوان کارت‌ها', BERYON_TD), 'type' => 'text', 'wide' => true],

            ['group' => 'ceo', 'type' => 'heading', 'label' => \__('صفحه مدیرعامل', BERYON_TD)],
            ['group' => 'ceo', 'name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'ceo', 'name' => 'lead', 'label' => \__('متن ابتدایی', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'ceo', 'name' => 'name', 'label' => \__('نام مدیرعامل', BERYON_TD), 'type' => 'text'],
            ['group' => 'ceo', 'name' => 'image', 'label' => \__('نام فایل پرتره', BERYON_TD), 'type' => 'text', 'dir' => 'ltr', 'help' => \__('تصویر ceo-photo.png بدون هیچ تغییری استفاده می‌شود.', BERYON_TD)],
            ['group' => 'ceo', 'name' => 'image_alt', 'label' => \__('متن جایگزین پرتره', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'ceo', 'name' => 'exp_value', 'label' => \__('عدد شاخص', BERYON_TD), 'type' => 'text'],
            ['group' => 'ceo', 'name' => 'exp_label', 'label' => \__('عنوان شاخص', BERYON_TD), 'type' => 'text'],
            ['group' => 'ceo', 'name' => 'tag', 'label' => \__('برچسب', BERYON_TD), 'type' => 'text'],
            ['group' => 'ceo', 'name' => 'paragraphs', 'label' => \__('پاراگراف‌ها', BERYON_TD), 'type' => 'lines', 'rows' => 5],
            ['group' => 'ceo', 'name' => 'checks', 'label' => \__('موارد تأیید', BERYON_TD), 'type' => 'lines', 'rows' => 4],
            ['group' => 'ceo', 'name' => 'achievements_tag', 'label' => \__('برچسب دستاوردها', BERYON_TD), 'type' => 'text'],
            ['group' => 'ceo', 'name' => 'achievements_title', 'label' => \__('عنوان دستاوردها', BERYON_TD), 'type' => 'text'],
            ['group' => 'ceo', 'name' => 'achievements', 'label' => \__('دستاوردها', BERYON_TD), 'type' => 'repeater', 'max' => 6, 'row' => [
                ['name' => 'icon', 'label' => \__('آیکون', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
                ['name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text'],
                ['name' => 'text', 'label' => \__('متن', BERYON_TD), 'type' => 'textarea'],
            ]],
            ['group' => 'ceo', 'name' => 'quote', 'label' => \__('نقل‌قول', BERYON_TD), 'type' => 'textarea', 'rows' => 3],
            ['group' => 'ceo', 'name' => 'quote_by', 'label' => \__('گوینده نقل‌قول', BERYON_TD), 'type' => 'text', 'wide' => true],

            ['group' => 'org', 'type' => 'heading', 'label' => \__('چارت سازمانی', BERYON_TD)],
            ['group' => 'org', 'name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'org', 'name' => 'lead', 'label' => \__('متن ابتدایی', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'org', 'name' => 'root', 'label' => \__('عنوان ریشه چارت', BERYON_TD), 'type' => 'text'],
            ['group' => 'org', 'name' => 'root_name', 'label' => \__('نام ریشه', BERYON_TD), 'type' => 'text'],
            ['group' => 'org', 'name' => 'units', 'label' => \__('واحدهای سازمانی', BERYON_TD), 'type' => 'repeater', 'max' => 12, 'row' => [
                ['name' => 'title', 'label' => \__('واحد', BERYON_TD), 'type' => 'text'],
                ['name' => 'children', 'label' => \__('زیرواحدها (هر مورد در یک خط)', BERYON_TD), 'type' => 'textarea'],
            ]],
            ['group' => 'org', 'name' => 'duties_tag', 'label' => \__('برچسب شرح وظایف', BERYON_TD), 'type' => 'text'],
            ['group' => 'org', 'name' => 'duties_title', 'label' => \__('عنوان شرح وظایف', BERYON_TD), 'type' => 'text'],
            ['group' => 'org', 'name' => 'duties', 'label' => \__('وظایف واحدها', BERYON_TD), 'type' => 'repeater', 'max' => 12, 'row' => [
                ['name' => 'icon', 'label' => \__('آیکون', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
                ['name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text'],
                ['name' => 'text', 'label' => \__('متن', BERYON_TD), 'type' => 'textarea'],
            ]],

            ['group' => 'certificates', 'type' => 'heading', 'label' => \__('گواهینامه‌ها و افتخارات', BERYON_TD)],
            ['group' => 'certificates', 'name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'certificates', 'name' => 'lead', 'label' => \__('متن ابتدایی', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'certificates', 'name' => 'intl_tag', 'label' => \__('برچسب گواهینامه‌های بین‌المللی', BERYON_TD), 'type' => 'text'],
            ['group' => 'certificates', 'name' => 'intl_title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text'],
            ['group' => 'certificates', 'name' => 'intl_desc', 'label' => \__('توضیح', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'certificates', 'name' => 'intl', 'label' => \__('گواهینامه‌های بین‌المللی', BERYON_TD), 'type' => 'repeater', 'max' => 16, 'row' => [
                ['name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text'],
                ['name' => 'sub', 'label' => \__('زیرعنوان', BERYON_TD), 'type' => 'text'],
            ]],
            ['group' => 'certificates', 'name' => 'national_tag', 'label' => \__('برچسب افتخارات ملی', BERYON_TD), 'type' => 'text'],
            ['group' => 'certificates', 'name' => 'national_title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text'],
            ['group' => 'certificates', 'name' => 'national', 'label' => \__('تندیس‌ها و لوح‌ها', BERYON_TD), 'type' => 'repeater', 'max' => 12, 'row' => [
                ['name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text'],
                ['name' => 'sub', 'label' => \__('زیرعنوان', BERYON_TD), 'type' => 'text'],
            ]],
            ['group' => 'certificates', 'name' => 'cta_title', 'label' => \__('عنوان CTA', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'certificates', 'name' => 'cta_text', 'label' => \__('متن CTA', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'certificates', 'name' => 'cta_btn_1', 'label' => \__('دکمه ۱', BERYON_TD), 'type' => 'text'],
            ['group' => 'certificates', 'name' => 'cta_btn_2', 'label' => \__('دکمه ۲', BERYON_TD), 'type' => 'text'],
        ];
    }

    /**
     * تب فرم‌ها و پیام‌ها.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function messages(): array
    {
        return [
            ['group' => 'forms', 'name' => 'enabled', 'label' => \__('فعال‌سازی فرم‌ها (REST API)', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'forms', 'name' => 'store', 'label' => \__('ذخیره درخواست‌ها در دیتابیس', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'forms', 'name' => 'notify', 'label' => \__('ارسال ایمیل اطلاع‌رسانی', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'forms', 'name' => 'notify_email', 'label' => \__('ایمیل دریافت‌کننده', BERYON_TD), 'type' => 'email'],
            ['group' => 'forms', 'name' => 'honeypot', 'label' => \__('فیلد تله ضدربات (Honeypot)', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'forms', 'name' => 'honeypot_field', 'label' => \__('نام فیلد تله', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
            ['group' => 'forms', 'name' => 'rate_limit', 'label' => \__('حداکثر ارسال در بازه (هر IP)', BERYON_TD), 'type' => 'number', 'min' => 1, 'max' => 100],
            ['group' => 'forms', 'name' => 'rate_window', 'label' => \__('بازه زمانی (ثانیه)', BERYON_TD), 'type' => 'number', 'min' => 30, 'max' => 86400],
            ['group' => 'forms', 'name' => 'min_message_length', 'label' => \__('حداقل طول پیام', BERYON_TD), 'type' => 'number', 'min' => 0, 'max' => 500],
            ['group' => 'forms', 'name' => 'max_message_length', 'label' => \__('حداکثر طول پیام', BERYON_TD), 'type' => 'number', 'min' => 100, 'max' => 20000],
            ['group' => 'forms', 'name' => 'track_prefix', 'label' => \__('پیشوند کد پیگیری', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
            ['group' => 'forms', 'name' => 'success_contact_title', 'label' => \__('عنوان موفقیت فرم تماس', BERYON_TD), 'type' => 'text'],
            ['group' => 'forms', 'name' => 'success_contact_text', 'label' => \__('متن موفقیت فرم تماس', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'forms', 'name' => 'success_track_label', 'label' => \__('برچسب کد پیگیری', BERYON_TD), 'type' => 'text'],

            ['group' => 'contact', 'type' => 'heading', 'label' => \__('صفحه تماس با ما', BERYON_TD)],
            ['group' => 'contact', 'name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'contact', 'name' => 'lead', 'label' => \__('متن ابتدایی', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'contact', 'name' => 'cards', 'label' => \__('کارت‌های تماس', BERYON_TD), 'type' => 'repeater', 'max' => 6, 'row' => [
                ['name' => 'icon', 'label' => \__('آیکون', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
                ['name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text'],
                ['name' => 'text', 'label' => \__('متن', BERYON_TD), 'type' => 'textarea'],
                ['name' => 'href', 'label' => \__('لینک (اختیاری)', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
            ]],
            ['group' => 'contact', 'name' => 'map_text', 'label' => \__('متن لینک نقشه', BERYON_TD), 'type' => 'text'],
            ['group' => 'contact', 'name' => 'form_title', 'label' => \__('عنوان فرم تماس', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'contact', 'name' => 'departments', 'label' => \__('واحد‌های انتخابی', BERYON_TD), 'type' => 'lines', 'rows' => 3],

            ['group' => 'feedback', 'type' => 'heading', 'label' => \__('صفحه نظرات و شکایات', BERYON_TD)],
            ['group' => 'feedback', 'name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'feedback', 'name' => 'lead', 'label' => \__('متن ابتدایی', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'feedback', 'name' => 'cards', 'label' => \__('کارت‌ها', BERYON_TD), 'type' => 'repeater', 'max' => 6, 'row' => [
                ['name' => 'icon', 'label' => \__('آیکون', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
                ['name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text'],
                ['name' => 'text', 'label' => \__('متن', BERYON_TD), 'type' => 'textarea'],
            ]],
            ['group' => 'feedback', 'name' => 'form_title', 'label' => \__('عنوان فرم', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'feedback', 'name' => 'subjects', 'label' => \__('موضوعات', BERYON_TD), 'type' => 'lines', 'rows' => 3],
            ['group' => 'feedback', 'name' => 'types', 'label' => \__('انواع پیام', BERYON_TD), 'type' => 'repeater', 'max' => 8, 'row' => [
                ['name' => 'value', 'label' => \__('مقدار', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
                ['name' => 'label', 'label' => \__('برچسب', BERYON_TD), 'type' => 'text'],
            ]],
            ['group' => 'feedback', 'name' => 'faq_tag', 'label' => \__('برچسب پرسش‌های پرتکرار', BERYON_TD), 'type' => 'text'],
            ['group' => 'feedback', 'name' => 'faq_title', 'label' => \__('عنوان پرسش‌ها', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'feedback', 'name' => 'faqs', 'label' => \__('پرسش و پاسخ‌ها', BERYON_TD), 'type' => 'repeater', 'max' => 12, 'row' => [
                ['name' => 'q', 'label' => \__('پرسش', BERYON_TD), 'type' => 'text'],
                ['name' => 'a', 'label' => \__('پاسخ', BERYON_TD), 'type' => 'textarea'],
            ]],
            ['group' => 'feedback', 'name' => 'success_title', 'label' => \__('عنوان موفقیت', BERYON_TD), 'type' => 'text'],
            ['group' => 'feedback', 'name' => 'success_text', 'label' => \__('متن موفقیت', BERYON_TD), 'type' => 'textarea', 'rows' => 2],

            ['group' => 'agency', 'type' => 'heading', 'label' => \__('صفحه اعطای نمایندگی', BERYON_TD)],
            ['group' => 'agency', 'name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'agency', 'name' => 'lead', 'label' => \__('متن ابتدایی', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'agency', 'name' => 'form_title', 'label' => \__('عنوان فرم', BERYON_TD), 'type' => 'text'],
            ['group' => 'agency', 'name' => 'form_note', 'label' => \__('یادداشت زیر عنوان فرم', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'agency', 'name' => 'steps', 'label' => \__('مراحل همکاری', BERYON_TD), 'type' => 'repeater', 'max' => 6, 'row' => [
                ['name' => 'icon', 'label' => \__('آیکون', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
                ['name' => 'title', 'label' => \__('عنوان', BERYON_TD), 'type' => 'text'],
                ['name' => 'text', 'label' => \__('متن', BERYON_TD), 'type' => 'textarea'],
            ]],
            ['group' => 'agency', 'name' => 'sales_types', 'label' => \__('انواع فروش', BERYON_TD), 'type' => 'lines', 'rows' => 3],
            ['group' => 'agency', 'name' => 'ownership_types', 'label' => \__('مالکیت انبار', BERYON_TD), 'type' => 'lines', 'rows' => 3],
            ['group' => 'agency', 'name' => 'success_title', 'label' => \__('عنوان موفقیت', BERYON_TD), 'type' => 'text'],
            ['group' => 'agency', 'name' => 'success_text', 'label' => \__('متن موفقیت', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
        ];
    }

    /**
     * تب وبلاگ.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function blog(): array
    {
        return [
            ['group' => 'blog', 'name' => 'title', 'label' => \__('عنوان مجله', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'blog', 'name' => 'lead', 'label' => \__('متن ابتدایی', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'blog', 'name' => 'per_page', 'label' => \__('تعداد نوشته در هر صفحه', BERYON_TD), 'type' => 'number', 'min' => 3, 'max' => 24],
            ['group' => 'blog', 'name' => 'author_label', 'label' => \__('نام نویسنده پیش‌فرض', BERYON_TD), 'type' => 'text'],
            ['group' => 'blog', 'name' => 'author_note', 'label' => \__('توضیح نویسنده', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'blog', 'name' => 'read_time_label', 'label' => \__('برچسب زمان مطالعه', BERYON_TD), 'type' => 'text'],
            ['group' => 'blog', 'name' => 'words_per_minute', 'label' => \__('کلمه در دقیقه (فارسی)', BERYON_TD), 'type' => 'number', 'min' => 100, 'max' => 400],
            ['group' => 'blog', 'name' => 'more_label', 'label' => \__('برچسب «ادامه مطلب»', BERYON_TD), 'type' => 'text'],
            ['group' => 'blog', 'name' => 'sidebar_latest', 'label' => \__('عنوان «تازه‌های مجله»', BERYON_TD), 'type' => 'text'],
            ['group' => 'blog', 'name' => 'sidebar_cats', 'label' => \__('عنوان «دسته‌بندی مطالب»', BERYON_TD), 'type' => 'text'],
            ['group' => 'blog', 'name' => 'sidebar_catalog_title', 'label' => \__('عنوان کارت کاتالوگ', BERYON_TD), 'type' => 'text'],
            ['group' => 'blog', 'name' => 'sidebar_catalog_text', 'label' => \__('متن کارت کاتالوگ', BERYON_TD), 'type' => 'text', 'wide' => true],
            ['group' => 'blog', 'name' => 'sidebar_catalog_btn', 'label' => \__('دکمه کارت کاتالوگ', BERYON_TD), 'type' => 'text'],
            ['group' => 'blog', 'name' => 'newsletter_title', 'label' => \__('عنوان خبرنامه', BERYON_TD), 'type' => 'text'],
            ['group' => 'blog', 'name' => 'newsletter_text', 'label' => \__('متن خبرنامه', BERYON_TD), 'type' => 'textarea', 'rows' => 2],
            ['group' => 'blog', 'name' => 'newsletter_btn', 'label' => \__('دکمه خبرنامه', BERYON_TD), 'type' => 'text'],
            ['group' => 'blog', 'name' => 'newsletter_success_title', 'label' => \__('عنوان موفقیت خبرنامه', BERYON_TD), 'type' => 'text'],
            ['group' => 'blog', 'name' => 'newsletter_success_text', 'label' => \__('متن موفقیت خبرنامه', BERYON_TD), 'type' => 'text', 'wide' => true],
        ];
    }

    /**
     * تب سئو.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function seo(): array
    {
        return [
            ['group' => 'seo', 'name' => 'enabled', 'label' => \__('فعال‌سازی موتور سئوی قالب', BERYON_TD), 'type' => 'checkbox', 'help' => \__('اگر افزونه سئوی دیگری فعال باشد، این گزینه بی‌اثر است.', BERYON_TD)],
            ['group' => 'seo', 'name' => 'title_template', 'label' => \__('الگوی عنوان', BERYON_TD), 'type' => 'text', 'wide' => true, 'help' => \__('%s جای عنوان صفحه می‌نشیند.', BERYON_TD)],
            ['group' => 'seo', 'name' => 'title_sep', 'label' => \__('جداکننده عنوان', BERYON_TD), 'type' => 'text'],
            ['group' => 'seo', 'name' => 'default_description', 'label' => \__('توضیحات متای پیش‌فرض', BERYON_TD), 'type' => 'textarea', 'rows' => 3],
            ['group' => 'seo', 'name' => 'og_image', 'label' => \__('تصویر پیش‌فرض شبکه‌های اجتماعی', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
            ['group' => 'seo', 'name' => 'og_site_name', 'label' => \__('نام سایت (og:site_name)', BERYON_TD), 'type' => 'text'],
            ['group' => 'seo', 'name' => 'og_locale', 'label' => \__('زبان (og:locale)', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
            ['group' => 'seo', 'name' => 'twitter_card', 'label' => \__('نوع کارت توییتر', BERYON_TD), 'type' => 'select', 'options' => [
                ['value' => 'summary_large_image', 'label' => 'summary_large_image'],
                ['value' => 'summary', 'label' => 'summary'],
            ]],
            ['group' => 'seo', 'name' => 'robots_default', 'label' => \__('دستور پیش‌فرض روبات‌ها', BERYON_TD), 'type' => 'text', 'dir' => 'ltr', 'wide' => true],
            ['group' => 'seo', 'name' => 'noindex_search', 'label' => \__('noindex برای صفحه جست‌وجو', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'seo', 'name' => 'noindex_archives', 'label' => \__('noindex برای آرشیوها', BERYON_TD), 'type' => 'checkbox'],

            ['group' => 'seo', 'type' => 'heading', 'label' => \__('داده ساخت‌یافته (JSON-LD)', BERYON_TD)],
            ['group' => 'seo', 'name' => 'schema_org', 'label' => \__('Organization', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'seo', 'name' => 'org_type', 'label' => \__('نوع سازمان', BERYON_TD), 'type' => 'select', 'options' => [
                ['value' => 'FoodEstablishment', 'label' => 'FoodEstablishment'],
                ['value' => 'Organization', 'label' => 'Organization'],
                ['value' => 'LocalBusiness', 'label' => 'LocalBusiness'],
                ['value' => 'Corporation', 'label' => 'Corporation'],
            ]],
            ['group' => 'seo', 'name' => 'org_founding_year', 'label' => \__('سال تأسیس (میلادی)', BERYON_TD), 'type' => 'text', 'dir' => 'ltr'],
            ['group' => 'seo', 'name' => 'schema_website', 'label' => \__('WebSite + SearchAction', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'seo', 'name' => 'schema_breadcrumb', 'label' => \__('BreadcrumbList', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'seo', 'name' => 'schema_product', 'label' => \__('Product', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'seo', 'name' => 'schema_article', 'label' => \__('Article', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'seo', 'name' => 'schema_faq', 'label' => \__('FAQPage', BERYON_TD), 'type' => 'checkbox'],

            ['group' => 'seo', 'type' => 'heading', 'label' => \__('نقشه سایت XML', BERYON_TD)],
            ['group' => 'seo', 'name' => 'sitemap_enabled', 'label' => \__('فعال‌سازی نقشه سایت قالب', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'seo', 'name' => 'sitemap_slug', 'label' => \__('مسیر نقشه سایت', BERYON_TD), 'type' => 'text', 'dir' => 'ltr', 'help' => \__('اگر افزونه سئو فعال باشد، نقشه سایت همان افزونه استفاده می‌شود.', BERYON_TD)],
            ['group' => 'seo', 'name' => 'sitemap_per_page', 'label' => \__('تعداد آدرس در هر صفحه', BERYON_TD), 'type' => 'number', 'min' => 50, 'max' => 1000],
            ['group' => 'seo', 'name' => 'seo_plugin_detected_notice', 'label' => \__('نمایش هشدار تشخیص افزونه سئو', BERYON_TD), 'type' => 'checkbox'],
        ];
    }

    /**
     * تب عملکرد.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function performance(): array
    {
        return [
            ['group' => 'performance', 'name' => 'lazy_images', 'label' => \__('بارگذاری تنبل تصاویر', BERYON_TD), 'type' => 'checkbox', 'help' => \__('تصویر اول صفحه اصلی همیشه eager + fetchpriority=high است.', BERYON_TD)],
            ['group' => 'performance', 'name' => 'lazy_iframes', 'label' => \__('بارگذاری تنبل iframeها', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'performance', 'name' => 'srcset', 'label' => \__('خروجی srcset/sizes واکنش‌گرا', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'performance', 'name' => 'preload_hero', 'label' => \__('پیش‌بارگذاری تصویر قهرمان', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'performance', 'name' => 'webp_hint', 'label' => \__('اولویت‌دهی به فرمت WebP', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'performance', 'name' => 'defer_scripts', 'label' => \__('defer برای اسکریپت قالب', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'performance', 'name' => 'dns_prefetch', 'label' => \__('dns-prefetch و preconnect', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'performance', 'name' => 'fonts_google', 'label' => \__('بارگذاری فونت از Google Fonts', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'performance', 'name' => 'fonts_self_host', 'label' => \__('میزبانی محلی فونت (در صورت وجود فایل)', BERYON_TD), 'type' => 'checkbox', 'help' => \__('فونت‌ها را در public/assets/fonts با نام vazirmatn-*.woff2 قرار دهید.', BERYON_TD)],
            ['group' => 'performance', 'name' => 'async_fonts', 'label' => \__('بارگذاری غیرمسدودکننده فونت', BERYON_TD), 'type' => 'checkbox'],

            ['group' => 'performance', 'type' => 'heading', 'label' => \__('حذف بار اضافی وردپرس', BERYON_TD)],
            ['group' => 'performance', 'name' => 'remove_emojis', 'label' => \__('حذف اسکریپت ایموجی', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'performance', 'name' => 'remove_oembed', 'label' => \__('حذف اسکریپت oEmbed', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'performance', 'name' => 'remove_wp_block_css', 'label' => \__('حذف CSS بلوک‌ها و گوتنبرگ', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'performance', 'name' => 'remove_woo_css', 'label' => \__('حذف CSS ووکامرس (استایل اختصاصی قالب)', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'performance', 'name' => 'disable_xmlrpc', 'label' => \__('غیرفعال‌سازی XML-RPC', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'performance', 'name' => 'disable_rest_for_guests', 'label' => \__('محدودسازی REST برای مهمان‌ها', BERYON_TD), 'type' => 'checkbox', 'help' => \__('مسیر فرم‌های قالب (beryon/v1) همیشه باز می‌ماند.', BERYON_TD)],

            ['group' => 'performance', 'type' => 'heading', 'label' => \__('حافظه نهان', BERYON_TD)],
            ['group' => 'performance', 'name' => 'query_cache', 'label' => \__('حافظه نهان کوئری محصولات', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'performance', 'name' => 'cache_ttl', 'label' => \__('مدت حافظه نهان (ثانیه)', BERYON_TD), 'type' => 'number', 'min' => 60, 'max' => 86400],
        ];
    }

    /**
     * تب امنیت.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function security(): array
    {
        return [
            ['group' => 'security', 'name' => 'headers', 'label' => \__('هدرهای امنیتی', BERYON_TD), 'type' => 'checkbox', 'help' => \__('X-Content-Type-Options، X-Frame-Options، Referrer-Policy، Permissions-Policy', BERYON_TD)],
            ['group' => 'security', 'name' => 'hsts', 'label' => \__('HSTS (فقط در HTTPS کامل)', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'security', 'name' => 'csp_enabled', 'label' => \__('فعال‌سازی Content-Security-Policy', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'security', 'name' => 'csp_report_only', 'label' => \__('حالت فقط‌گزارش (Report-Only)', BERYON_TD), 'type' => 'checkbox', 'help' => \__('برای تست سازگاری با سرویس‌های شخص ثالث توصیه می‌شود.', BERYON_TD)],
            ['group' => 'security', 'name' => 'csp_extra', 'label' => \__('دستورهای اضافی CSP', BERYON_TD), 'type' => 'textarea', 'rows' => 3, 'dir' => 'ltr', 'help' => \__('هر دستور در یک خط، مثال: frame-src https://maps.google.com', BERYON_TD)],
            ['group' => 'security', 'name' => 'hide_version', 'label' => \__('پنهان‌سازی نسخه وردپرس و قالب', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'security', 'name' => 'remove_generator', 'label' => \__('حذف تگ generator', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'security', 'name' => 'login_errors_generic', 'label' => \__('پیام خطای عمومی در صفحه ورود', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'security', 'name' => 'block_bad_bots', 'label' => \__('مسدودسازی User-Agentهای مخرب', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'security', 'name' => 'file_integrity', 'label' => \__('پایش یکپارچگی فایل‌های هسته (FIM)', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'security', 'name' => 'integrity_scope', 'label' => \__('دامنه پایش', BERYON_TD), 'type' => 'select', 'options' => [
                ['value' => 'core', 'label' => \__('فقط فایل‌های هسته قالب', BERYON_TD)],
                ['value' => 'off', 'label' => \__('غیرفعال', BERYON_TD)],
            ]],
            ['group' => 'security', 'name' => 'disable_file_edit', 'label' => \__('غیرفعال‌سازی ویرایشگر فایل در پیشخوان', BERYON_TD), 'type' => 'checkbox'],
        ];
    }

    /**
     * تب به‌روزرسانی.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function updater(): array
    {
        return [
            ['group' => 'updater', 'name' => 'allow_remote', 'label' => \__('اجازه بررسی/دانلود از سرور راه دور', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'updater', 'name' => 'feed_url', 'label' => \__('آدرس فید به‌روزرسانی (JSON)', BERYON_TD), 'type' => 'url', 'help' => \__('مثال: https://example.com/patches/beryon/feed.json', BERYON_TD)],
            ['group' => 'updater', 'name' => 'auto_check', 'label' => \__('بررسی خودکار زمان‌بندی‌شده', BERYON_TD), 'type' => 'checkbox'],
            ['group' => 'updater', 'name' => 'check_interval', 'label' => \__('بازه بررسی', BERYON_TD), 'type' => 'select', 'options' => [
                ['value' => 'hourly', 'label' => \__('هر ساعت', BERYON_TD)],
                ['value' => 'twicedaily', 'label' => \__('دو بار در روز', BERYON_TD)],
                ['value' => 'daily', 'label' => \__('روزانه', BERYON_TD)],
                ['value' => 'weekly', 'label' => \__('هفتگی', BERYON_TD)],
            ]],
            ['group' => 'updater', 'name' => 'channel', 'label' => \__('کانال انتشار', BERYON_TD), 'type' => 'select', 'options' => [
                ['value' => 'stable', 'label' => \__('پایدار', BERYON_TD)],
                ['value' => 'beta', 'label' => \__('آزمایشی', BERYON_TD)],
            ]],
            ['group' => 'updater', 'name' => 'backups_keep', 'label' => \__('تعداد پشتیبان نگهداری‌شده', BERYON_TD), 'type' => 'number', 'min' => 0, 'max' => 20],
            ['group' => 'updater', 'name' => 'max_patch_size', 'label' => \__('حداکثر حجم بسته پچ (بایت)', BERYON_TD), 'type' => 'number', 'min' => 1048576, 'max' => 268435456],
            ['group' => 'updater', 'name' => 'max_file_size', 'label' => \__('حداکثر حجم هر فایل (بایت)', BERYON_TD), 'type' => 'number', 'min' => 10240, 'max' => 104857600],
            ['group' => 'updater', 'name' => 'patch_slug_allowlist', 'label' => \__('پیشوندهای مجاز مسیر فایل', BERYON_TD), 'type' => 'lines', 'rows' => 6, 'dir' => 'ltr', 'help' => \__('هر پیشوند در یک خط؛ مثال: app/ یا resources/views/', BERYON_TD)],
            ['group' => 'updater', 'name' => 'patch_ext_allowlist', 'label' => \__('پسوندهای مجاز فایل', BERYON_TD), 'type' => 'lines', 'rows' => 6, 'dir' => 'ltr'],
            ['group' => 'updater', 'name' => 'signature_key', 'label' => \__('کلید امضای بسته (HMAC-SHA256)', BERYON_TD), 'type' => 'text', 'dir' => 'ltr', 'help' => \__('اگر پر شود، هر بسته پچ باید با همین کلید امضا شده باشد؛ در غیر این‌صورت رد می‌شود.', BERYON_TD)],
        ];
    }
}
