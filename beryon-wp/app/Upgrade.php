<?php

/**
 * ارتقای داده‌های ذخیره‌شده قالب — قالب بریون
 *
 * هنگام فعال‌سازی قالب، همه مقادیر config/defaults.json در پایگاه داده ذخیره می‌شود
 * (Settings::seed_defaults). پس تغییر یک مقدار پیش‌فرض در نسخه تازه، روی سایتی که
 * قالب را پیش‌تر فعال کرده اثری ندارد. این کلاس در نخستین بارگذاری پس از هر
 * به‌روزرسانی، همان مقادیر «پیش‌فرضِ دست‌نخورده» را به مقدار تازه می‌برد؛ مقداری
 * که مدیر خودش عوض کرده باشد دست نمی‌خورد.
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * ارتقای تنظیمات ذخیره‌شده.
 */
final class Upgrade
{
    /** Option شماره آخرین ارتقای انجام‌شده */
    public const OPTION = 'beryon_upgrade_rev';

    /** شماره ارتقای این نسخه (۱.۰.۶ → 106) */
    public const REV = 106;

    /**
     * ثبت هوک‌ها.
     *
     * @return void
     */
    public static function boot(): void
    {
        \add_action('init', [self::class, 'run'], 1);
    }

    /**
     * اجرای ارتقاهای انجام‌نشده (یک بار برای هر نسخه).
     *
     * @return void
     */
    public static function run(): void
    {
        $done = (int) \get_option(self::OPTION, 0);

        if ($done >= self::REV) {
            return;
        }

        try {
            if ($done < 106) {
                self::to_106();
            }
        } catch (\Throwable $error) {
            // ارتقا نباید سایت را متوقف کند؛ خطا ثبت می‌شود و دوباره تلاش نمی‌شود.
            error_log('[beryon] ارتقای تنظیمات ناموفق: ' . $error->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        }

        \update_option(self::OPTION, self::REV, true);
    }

    /**
     * ارتقا به ۱.۰.۶.
     *
     * - شماره‌های تماس بدون خط تیره؛
     * - متن کارت «تلفن کارخانه» برگه تماس (شماره ناقصِ «۰۱۱-۴۴۴۸۰۷ تا ۹»)؛
     * - برچسب دکمه «مشاهده آنلاین» برگه کاتالوگ؛
     * - چارت سازمانی (ریشه و واحدها) مطابق چارت تازه؛
     * - حذف تنظیمات و زمان‌بندیِ «به‌روزرسانی از راه دور».
     *
     * @return void
     */
    private static function to_106(): void
    {
        Updater::disable_remote();
        Agents::flush();

        $stored = \get_option(Settings::OPTION_KEY, []);

        if (! is_array($stored) || $stored === []) {
            return;
        }

        $before   = $stored;
        $defaults = Settings::defaults();

        // ۱) مقدارهای متنی: «پیش‌فرض پیشین» → «پیش‌فرض تازه».
        $texts = [
            'catalog.online_label' => ['مشاهده آنلاین محصولات'],
            'org.root'             => ['مدیرعامل'],
            'org.root_name'        => ['دکتر بهرام شهره'],
        ];

        foreach ($texts as $key => $old_values) {
            [$group, $name] = explode('.', $key, 2);

            if (isset($stored[$group][$name]) && is_string($stored[$group][$name])
                && in_array(trim($stored[$group][$name]), $old_values, true)) {
                $stored[$group][$name] = (string) ($defaults[$group][$name] ?? '');
            }
        }

        // ۲) کارت «تلفن کارخانه»: شماره متن پیشین ناقص بود (۴۴۴۸۰۷ به‌جای ۴۴۴۴۸۰۷۱).
        if (isset($stored['contact']['cards']) && is_array($stored['contact']['cards'])) {
            foreach ($stored['contact']['cards'] as $index => $card) {
                if (is_array($card) && isset($card['text']) && trim((string) $card['text']) === '۰۱۱-۴۴۴۸۰۷ تا ۹ (پاسخگویی شنبه تا پنجشنبه)') {
                    $stored['contact']['cards'][$index]['text'] = '{phone_factory_label} تا ۹ (پاسخگویی شنبه تا پنجشنبه)';
                }
            }
        }

        // ۳) چارت سازمانی: فقط اگر همان واحدهای پیش‌فرض پیشین باشد.
        if (isset($stored['org']['units']) && self::units_signature($stored['org']['units']) === self::units_signature(self::old_units())
            && isset($defaults['org']['units'])) {
            $stored['org']['units'] = $defaults['org']['units'];
        }

        // ۴) تنظیمات «به‌روزرسانی از راه دور» دیگر وجود ندارند.
        if (isset($stored['updater']) && is_array($stored['updater'])) {
            unset(
                $stored['updater']['feed_url'],
                $stored['updater']['auto_check'],
                $stored['updater']['check_interval'],
                $stored['updater']['channel'],
                $stored['updater']['allow_remote']
            );
        }

        // ۵) شماره‌های تماس بدون خط تیره (در خواندن هم اعمال می‌شود؛ این‌جا خودِ داده پاک می‌شود).
        $stored = Settings::plain_phones($stored);

        if ($stored !== $before) {
            Settings::save($stored);
        }

        Settings::flush();
    }

    /**
     * واحدهای چارت سازمانیِ پیش‌فرض تا نسخه ۱.۰.۵.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function old_units(): array
    {
        return [
            ['title' => 'مدیریت مالی', 'children' => [
                ['title' => 'واحد حسابداری صنعتی', 'sub' => 'انبار محصول'],
                ['title' => 'واحد حسابداری مالی', 'sub' => ''],
                ['title' => 'واحد مالیاتی', 'sub' => ''],
            ]],
            ['title' => 'مدیریت تولید', 'children' => []],
            ['title' => 'مدیریت بازرگانی', 'children' => [
                ['title' => 'انبارها', 'sub' => 'فنی و مواد اولیه'],
                ['title' => 'واحد خرید', 'sub' => ''],
            ]],
            ['title' => 'مدیریت فروش', 'children' => []],
            ['title' => 'مدیریت R&D', 'children' => []],
            ['title' => 'مدیریت فنی بهداشت', 'children' => []],
            ['title' => 'مدیریت QC', 'children' => []],
        ];
    }

    /**
     * امضای متنی واحدهای چارت (فقط عنوان‌ها و زیرعنوان‌ها) برای مقایسه.
     *
     * @param mixed $units واحدها
     * @return string
     */
    private static function units_signature($units): string
    {
        $out = [];

        foreach ((array) $units as $unit) {
            if (! is_array($unit)) {
                continue;
            }

            $row = [trim((string) ($unit['title'] ?? ''))];

            foreach ((array) ($unit['children'] ?? []) as $child) {
                if (is_array($child)) {
                    $row[] = trim((string) ($child['title'] ?? '')) . '|' . trim((string) ($child['sub'] ?? ''));
                } elseif (is_scalar($child)) {
                    $row[] = trim((string) $child) . '|';
                }
            }

            $out[] = implode('>', $row);
        }

        return implode("\n", $out);
    }
}
