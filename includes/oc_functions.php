<?php
declare(strict_types=1);

/**
 * 某年某月某日的 0 点时间戳
 * 2 月 29 日在平年自动归到 3 月 1 日
 */
function oc_birthday_in_year(int $month, int $day, int $year): int
{
    if ($month === 2 && $day === 29 && !checkdate(2, 29, $year)) {
        return mktime(0, 0, 0, 3, 1, $year);
    }
    return mktime(0, 0, 0, $month, $day, $year);
}

/**
 * 下一次生日的时间戳（当天 0 点）
 */
function oc_next_birthday(int $month, int $day, ?int $now = null): int
{
    $now = $now ?? time();
    $year = (int)date('Y', $now);
    $todayStart = strtotime('today', $now);

    $ts = oc_birthday_in_year($month, $day, $year);
    if ($ts < $todayStart) {
        $ts = oc_birthday_in_year($month, $day, $year + 1);
    }
    return $ts;
}

/**
 * 下一次生日时满几岁
 */
function oc_age_on(int $nextBirthdayTs, ?int $birthYear): ?int
{
    if (!$birthYear) return null;
    $age = (int)date('Y', $nextBirthdayTs) - $birthYear;
    return $age >= 0 ? $age : null;
}