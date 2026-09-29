<?php

namespace App\Support;

/**
 * Inclusive bounds for date-column range filters.
 *
 * Date columns are written through Eloquent's `date` cast, which serialises with
 * the connection's datetime format. MySQL's DATE column truncates that to
 * "2026-05-20"; SQLite keeps the string it was handed, "2026-05-20 00:00:00".
 * Comparing such a column against a bare "2026-05-20" upper bound therefore
 * drops every row landing on the last day of the range under SQLite.
 *
 * Pushing the bound to the end of the day is correct on both, and — unlike
 * whereDate(), which wraps the column in DATE() — leaves the query a plain
 * indexed range scan.
 */
final class DateRange
{
    public static function start(string $date): string
    {
        return $date;
    }

    public static function end(string $date): string
    {
        return $date.' 23:59:59';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function between(string $from, string $to): array
    {
        return [self::start($from), self::end($to)];
    }
}
