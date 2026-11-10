<?php

/**
 * تبدیل تاریخ میلادی ↔ جلالی (شمسی) — بدون وابستگی خارجی
 * ------------------------------------------------------------------
 * الگوریتم استاندارد jalaali-js (با جدول شکست ۱۵ نقطه‌ای) که برای بازهٔ
 * ۱۱۷۸ تا ۱۶۳۳ شمسی معتبر است؛ همه تاریخ‌های سایت بریون در این بازه‌اند.
 *
 * @package beryon
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * کلاس تاریخ جلالی.
 */
final class Jalali
{
    /**
     * نام ماه‌های شمسی.
     *
     * @var array<int,string>
     */
    private const MONTHS = [
        'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
    ];

    /**
     * جدول شکست سال‌های کبیسه.
     *
     * @var array<int,int>
     */
    private const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

    /**
     * تبدیل میلادی → جلالی.
     *
     * @param int $gy سال میلادی
     * @param int $gm ماه میلادی
     * @param int $gd روز میلادی
     * @return array{jy:int,jm:int,jd:int}
     */
    public static function toJalali(int $gy, int $gm, int $gd): array
    {
        $d0  = self::gregorianToJdn($gy, $gm, $gd);
        $res = self::jdnToJalali($d0);

        return ['jy' => $res['jy'], 'jm' => $res['jm'], 'jd' => $res['jd']];
    }

    /**
     * تبدیل جلالی → میلادی.
     *
     * @param int $jy سال شمسی
     * @param int $jm ماه شمسی
     * @param int $jd روز شمسی
     * @return array{gy:int,gm:int,gd:int}
     */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $d0  = self::jalaliToJdn($jy, $jm, $jd);
        $res = self::jdnToGregorian($d0);

        return ['gy' => $res['gy'], 'gm' => $res['gm'], 'gd' => $res['gd']];
    }

    /**
     * قالب‌بندی یک timestamp به شمسی.
     *
     * پشتیبانی از: Y y m n d j F M برای تاریخ و H G i s برای ساعت (به وقت سایت)؛
     * سایر نویسه‌ها و جداکننده‌ها عیناً چاپ می‌شوند. پیش‌تر نویسه‌های ساعت پشتیبانی
     * نمی‌شد و قالب «Y/m/d H:i» در پنل به‌صورت «۱۴۰۵/۰۸/۱۹ H:i» چاپ می‌شد.
     *
     * @param string|int|null $timestamp timestamp (پیش‌فرض: الان)
     * @param string          $format    قالب، پیش‌فرض Y/m/d
     * @return string
     */
    public static function format($timestamp = null, string $format = 'Y/m/d'): string
    {
        $timestamp = $timestamp === null || $timestamp === '' ? time() : (is_numeric($timestamp) ? (int) $timestamp : (int) strtotime((string) $timestamp));

        if ($timestamp <= 0) {
            $timestamp = time();
        }

        [$gy, $gm, $gd] = self::localParts($timestamp);
        $j   = self::toJalali($gy, $gm, $gd);
        $out = '';
        $len = strlen($format);

        for ($i = 0; $i < $len; $i++) {
            $char = $format[$i];

            switch ($char) {
                case 'Y':
                    $out .= self::digits((string) $j['jy']);
                    break;
                case 'y':
                    $out .= self::digits((string) ($j['jy'] % 100));
                    break;
                case 'm':
                    $out .= self::digits(sprintf('%02d', $j['jm']));
                    break;
                case 'n':
                    $out .= self::digits((string) $j['jm']);
                    break;
                case 'd':
                    $out .= self::digits(sprintf('%02d', $j['jd']));
                    break;
                case 'j':
                    $out .= self::digits((string) $j['jd']);
                    break;
                case 'F':
                    $out .= self::MONTHS[$j['jm'] - 1] ?? '';
                    break;
                case 'M':
                    $out .= mb_substr(self::MONTHS[$j['jm'] - 1] ?? '', 0, 6);
                    break;
                case 'H':
                case 'G':
                case 'i':
                case 's':
                    $out .= self::digits(self::localTime($char, $timestamp));
                    break;
                case '\\':
                    $out .= $format[++$i] ?? '';
                    break;
                default:
                    $out .= $char;
            }
        }

        return $out;
    }

    /**
     * سال شمسی جاری.
     *
     * @return int
     */
    public static function currentYear(): int
    {
        return self::toJalali(...self::localParts(time()))['jy'];
    }

    /**
     * اختلاف روزهای شمسی (برای زمان انتشار و …).
     *
     * @param string|int|null $timestamp زمان
     * @return string
     */
    public static function humanTimeDiff($timestamp = null): string
    {
        $timestamp = $timestamp === null ? time() : (int) $timestamp;

        return self::digits(\function_exists('human_time_diff') ? human_time_diff($timestamp, time()) : '');
    }

    /**
     * ارقام فارسی.
     *
     * @param string $value مقدار
     * @return string
     */
    private static function digits(string $value): string
    {
        return \function_exists('beryon_fa') ? beryon_fa($value) : $value;
    }

    /**
     * یک جزء ساعت (H، G، i یا s) به وقت منطقه زمانی وردپرس.
     *
     * @param string $char      نویسه قالب
     * @param int    $timestamp timestamp یونیکس
     * @return string
     */
    private static function localTime(string $char, int $timestamp): string
    {
        if (\function_exists('wp_date')) {
            $value = wp_date($char, $timestamp);

            if (is_string($value)) {
                return $value;
            }
        }

        return gmdate($char, $timestamp);
    }

    /**
     * اجزای تاریخ محلی بر پایه منطقه زمانی وردپرس.
     *
     * @param int $timestamp timestamp یونیکس
     * @return array{0:int,1:int,2:int} [سال، ماه، روز]
     */
    private static function localParts(int $timestamp): array
    {
        if (\function_exists('wp_date')) {
            return [
                (int) wp_date('Y', $timestamp),
                (int) wp_date('n', $timestamp),
                (int) wp_date('j', $timestamp),
            ];
        }

        return [(int) gmdate('Y', $timestamp), (int) gmdate('n', $timestamp), (int) gmdate('j', $timestamp)];
    }

    /* ------------------------------------------------------------ *
     * هسته الگوریتم (jalaali-js)
     * ------------------------------------------------------------ */

    /**
     * @param int $gy سال میلادی
     * @param int $gm ماه
     * @param int $gd روز
     * @return int
     */
    private static function gregorianToJdn(int $gy, int $gm, int $gd): int
    {
        $jdn = self::div(($gy + self::div($gm - 8, 6) + 100100) * 1461, 4)
            + self::div(153 * self::mod($gm + 9, 12) + 2, 5)
            + $gd - 34840408;

        return $jdn - self::div(self::div($gy + 100100 + self::div($gm - 8, 6), 100) * 3, 4) + 752;
    }

    /**
     * @param int $jdn شماره روز ژولینی
     * @return array{gy:int,gm:int,gd:int}
     */
    private static function jdnToGregorian(int $jdn): array
    {
        $j  = 4 * $jdn + 139361631;
        $j  = $j + self::div(self::div(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i  = self::div(self::mod($j, 1461), 4) * 5 + 308;
        $gd = self::div(self::mod($i, 153), 5) + 1;
        $gm = self::mod(self::div($i, 153), 12) + 1;
        $gy = self::div($j, 1461) - 100100 + self::div(8 - $gm, 6);

        return ['gy' => $gy, 'gm' => $gm, 'gd' => $gd];
    }

    /**
     * @param int $jy سال شمسی
     * @param int $jm ماه شمسی
     * @param int $jd روز شمسی
     * @return int
     */
    private static function jalaliToJdn(int $jy, int $jm, int $jd): int
    {
        $r   = self::jalaliCal($jy);
        $gy  = $jy + 621;

        return self::gregorianToJdn($gy, 3, $r['march']) + ($jm - 1) * 31 - self::div($jm, 7) * ($jm - 7) + $jd - 1;
    }

    /**
     * @param int $jdn شماره روز ژولینی
     * @return array{jy:int,jm:int,jd:int}
     */
    private static function jdnToJalali(int $jdn): array
    {
        $gy = self::jdnToGregorian($jdn)['gy'];
        $jy = $gy - 621;
        $r  = self::jalaliCal($jy);
        $k  = $jdn - self::gregorianToJdn($gy, 3, $r['march']);

        if ($k >= 0) {
            if ($k <= 185) {
                return ['jy' => $jy, 'jm' => 1 + self::div($k, 31), 'jd' => self::mod($k, 31) + 1];
            }

            $k -= 186;
        } else {
            $jy -= 1;
            $k   += 179;

            if ($r['leap'] === 1) {
                $k += 1;
            }
        }

        return ['jy' => $jy, 'jm' => 7 + self::div($k, 30), 'jd' => self::mod($k, 30) + 1];
    }

    /**
     * محاسبه اطلاعات تقویم یک سال شمسی.
     *
     * @param int $jy سال شمسی
     * @return array{leap:int,gy:int,march:int}
     */
    private static function jalaliCal(int $jy): array
    {
        $bl    = count(self::BREAKS);
        $gy    = $jy + 621;
        $leapJ = -14;
        $jp    = self::BREAKS[0];
        $jump  = 0;

        if ($jy < $jp || $jy >= self::BREAKS[$bl - 1]) {
            // خارج از بازه پشتیبانی‌شده؛ نزدیک‌ترین مقدار ممکن برگردانده می‌شود.
            $jy = max($jp, min($jy, self::BREAKS[$bl - 1] - 1));
        }

        for ($i = 1; $i < $bl; $i++) {
            $jm   = self::BREAKS[$i];
            $jump = $jm - $jp;

            if ($jy < $jm) {
                break;
            }

            $leapJ = $leapJ + self::div($jump, 33) * 8 + self::div(self::mod($jump, 33), 4);
            $jp    = $jm;
        }

        $n     = $jy - $jp;
        $leapJ = $leapJ + self::div($n, 33) * 8 + self::div(self::mod($n, 33) + 3, 4);

        if (self::mod($jump, 33) === 4 && ($jump - $n) === 4) {
            $leapJ += 1;
        }

        // سال‌های کبیسه میلادی تا سال gy (فرمول jalaali-js).
        $leapG = self::div($gy, 4) - self::div((self::div($gy, 100) + 1) * 3, 4) - 150;

        // روز فروردین ۱ در ماه مارس میلادی.
        $march = 20 + $leapJ - $leapG;

        // تعداد سال‌ها از آخرین کبیسه (۰ = سال کبیسه).
        if (($jump - $n) < 6) {
            $n = $n - $jump + self::div($jump + 4, 33) * 33;
        }

        $leap = self::mod(self::mod($n + 1, 33) - 1, 4);

        if ($leap === -1) {
            $leap = 4;
        }

        return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
    }

    /**
     * @param int $a صورت
     * @param int $b مخرج
     * @return int
     */
    private static function div(int $a, int $b): int
    {
        // همانند ~~(a / b) در jalaali-js: برش به سمت صفر.
        $q = $a / $b;

        return (int) ($q < 0 ? ceil($q) : floor($q));
    }

    /**
     * @param int $a عدد
     * @param int $b مقسوم‌علیه
     * @return int
     */
    private static function mod(int $a, int $b): int
    {
        return $a - self::div($a, $b) * $b;
    }
}
