<?php

/**
 * پل اتصال به Acorn/Sage 11 — قالب بریون
 * ------------------------------------------------------------------
 * اگر roots/acorn نصب باشد، رندر نماها به Blade واقعی واگذار می‌شود؛
 * در غیر این‌صورت MiniBlade داخلی قالب (بدون وابستگی composer) وارد عمل می‌شود.
 * این دو مسیر خروجی یکسان تولید می‌کنند.
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * کلاس پل Acorn.
 */
final class Acorn
{
    /**
     * آیا Acorn در دسترس است؟
     *
     * @return bool
     */
    public static function available(): bool
    {
        return function_exists('\Roots\view') || class_exists('\Roots\Acorn\Application');
    }

    /**
     * رندر یک نما به رشته.
     *
     * @param string              $view نام نما (dot notation)
     * @param array<string,mixed> $data داده
     * @return string
     */
    public static function view(string $view, array $data = []): string
    {
        if (self::available()) {
            return (string) \Roots\view($view, $data)->render();
        }

        MiniBlade::addPath(BERYON_PATH . '/resources/views');

        return MiniBlade::render($view, $data);
    }

    /**
     * رندر نما همراه با چیدمان (@extends) در حالت MiniBlade.
     *
     * @param string              $view نام نما
     * @param array<string,mixed> $data داده
     * @return string
     */
    public static function page(string $view, array $data = []): string
    {
        if (self::available()) {
            return (string) \Roots\view($view, $data)->render();
        }

        MiniBlade::addPath(BERYON_PATH . '/resources/views');

        return MiniBlade::renderWithLayout($view, $data);
    }

    /**
     * داده مشترک همه نماها.
     *
     * @param array<string,mixed> $data داده
     * @return void
     */
    public static function share(array $data): void
    {
        if (self::available() && function_exists('\Roots\view')) {
            foreach ($data as $key => $value) {
                \Roots\view()->share($key, $value); // phpcs:ignore
            }
        }

        foreach ($data as $key => $value) {
            MiniBlade::share((string) $key, $value);
        }
    }

    /**
     * پاک‌سازی حافظه نهان نماها.
     *
     * @return void
     */
    public static function flush(): void
    {
        MiniBlade::flushState();
        MiniBlade::clearCache();

        $cache = BERYON_PATH . '/storage/framework/views';

        if (is_dir($cache)) {
            foreach ((array) glob($cache . '/*.php') as $file) {
                @unlink((string) $file); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            }
        }
    }
}
