<?php

/**
 * تنظیمات قالب بریون — منبع حقیقت مقادیر پیش‌فرض و خواندن Optionها
 * ------------------------------------------------------------------
 * ساختار کلیدها: «گروه.نام» مانند general.phone یا seo.title_sep
 * مقادیر پیش‌فرض در config/defaults.json نگهداری می‌شوند تا با سیستم
 * به‌روزرسانی Patch قابل جایگزینی باشند.
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * کلاس تنظیمات قالب.
 */
final class Settings
{
    /** نام Option اصلی در جدول wp_options */
    public const OPTION_KEY = 'beryon_theme_options';

    /** نام Option نسخه‌های اعمال‌شده */
    public const VERSION_KEY = 'beryon_theme_versions';

    /**
     * حافظه نهان مقادیر در هر درخواست.
     *
     * @var array<string,mixed>|null
     */
    private static $cache = null;

    /**
     * مقادیر پیش‌فرض (از config/defaults.json) در هر درخواست.
     *
     * @var array<string,mixed>|null
     */
    private static $defaults = null;

    /**
     * حافظه نهان فایل‌های JSON پوشه config در هر درخواست.
     *
     * @var array<string,array<string,mixed>>
     */
    private static $json = [];

    /**
     * خواندن همه تنظیمات (ادغام پیش‌فرض + ذخیره‌شده).
     *
     * @return array<string,mixed>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $defaults = self::defaults();
        $stored   = \function_exists('get_option') ? (array) \get_option(self::OPTION_KEY, []) : [];

        self::$cache = self::merge($defaults, $stored);

        /**
         * فیلتر همه تنظیمات قالب.
         *
         * @param array $options
         */
        self::$cache = (array) \apply_filters('beryon/options', self::$cache);

        return self::$cache;
    }

    /**
     * خواندن یک کلید با نقطه‌مسیر: general.phone
     *
     * @param string $key     کلید
     * @param mixed  $default پیش‌فرض محلی
     * @return mixed
     */
    public static function opt(string $key, $default = null)
    {
        $options = self::all();
        $parts   = explode('.', $key);
        $value   = $options;

        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                $value = null;
                break;
            }
        }

        if ($value === null || $value === '') {
            $fallback = self::default($key);

            return $fallback !== null ? $fallback : $default;
        }

        /**
         * فیلتر یک تنظیم مشخص.
         *
         * @param mixed  $value
         * @param string $key
         */
        return \apply_filters('beryon/option/' . $key, $value, $key);
    }

    /**
     * مقدار پیش‌فرض یک کلید.
     *
     * @param string $key کلید
     * @return mixed
     */
    public static function default(string $key)
    {
        $value = self::defaults();

        foreach (explode('.', $key) as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * بارگذاری مقادیر پیش‌فرض از فایل JSON.
     *
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        if (self::$defaults !== null) {
            return self::$defaults;
        }

        $file = BERYON_PATH . '/config/defaults.json';
        $json = is_readable($file) ? (string) file_get_contents($file) : '';
        $data = json_decode($json, true);

        self::$defaults = is_array($data) ? $data : [];

        /**
         * فیلتر مقادیر پیش‌فرض.
         *
         * @param array $defaults
         */
        return (array) \apply_filters('beryon/defaults', self::$defaults);
    }

    /**
     * خواندن یک فایل JSON از پوشه config قالب (با کش در حافظه).
     *
     * @param string $file نام فایل (مثلاً provinces.json)
     * @return array<string,mixed>
     */
    public static function json(string $file): array
    {
        $file = basename($file);

        if (isset(self::$json[$file])) {
            return self::$json[$file];
        }

        $path = BERYON_PATH . '/config/' . $file;
        $raw  = is_readable($path) ? (string) file_get_contents($path) : '';
        $data = json_decode($raw, true);

        self::$json[$file] = is_array($data) ? $data : [];

        /**
         * فیلتر محتوای یک فایل پیکربندی قالب.
         *
         * @param array  $data داده‌ها
         * @param string $file نام فایل
         */
        return (array) \apply_filters('beryon/config/' . $file, self::$json[$file], $file);
    }

    /**
     * ذخیره تنظیمات (پس از sanitize در پنل).
     *
     * @param array<string,mixed> $values مقادیر
     * @return bool
     */
    public static function save(array $values): bool
    {
        self::$cache = null;

        return (bool) \update_option(self::OPTION_KEY, $values, false);
    }

    /**
     * درج مقادیر پیش‌فرض هنگام فعال‌سازی قالب (بدون بازنویسی مقادیر کاربر).
     *
     * @return void
     */
    public static function seed_defaults(): void
    {
        $existing = (array) \get_option(self::OPTION_KEY, []);

        if ($existing === []) {
            \update_option(self::OPTION_KEY, self::defaults(), false);

            return;
        }

        \update_option(self::OPTION_KEY, self::merge(self::defaults(), $existing), false);
    }

    /**
     * پاک‌سازی حافظه نهان (پس از ذخیره یا اعمال Patch).
     *
     * @return void
     */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /**
     * ادغام بازگشتی آرایه‌ها (مقادیر ذخیره‌شده بر پیش‌فرض اولویت دارند).
     *
     * @param array $base     پایه
     * @param array $override جایگزین
     * @return array
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && ! self::isList($value)) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * تشخیص آرایه فهرست‌مانند (ردیف‌های تکرارشونده جای‌گذاری نمی‌شوند).
     *
     * @param array $array آرایه
     * @return bool
     */
    private static function isList(array $array): bool
    {
        return $array !== [] && array_keys($array) === range(0, count($array) - 1);
    }
}
