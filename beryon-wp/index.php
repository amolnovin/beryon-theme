<?php

/**
 * نقطه ورود قالب بریون (Sage 11)
 * ------------------------------------------------------------------
 * سلسله‌مراتب قالب وردپرس در app/Template.php به نماهای Blade نگاشت می‌شود.
 * اگر composer نصب باشد، Blade واقعی (roots/acorn) رندر می‌کند؛ در غیر این‌صورت
 * MiniBlade داخلی قالب (app/MiniBlade.php) همان خروجی را تولید می‌کند.
 *
 * @package beryon
 */

defined('ABSPATH') || exit;

if (! defined('BERYON_PATH')) {
    require_once __DIR__ . '/functions.php';
}

echo \App\Template::render(); // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی Blade کنترل‌شده است
