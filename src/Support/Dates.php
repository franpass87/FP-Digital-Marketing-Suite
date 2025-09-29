<?php

declare(strict_types=1);

namespace FP\DMS\Support;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;

/**
 * @psalm-import-type Period from \FP\DMS\Support\Period
 */


class Dates
{
    public static function prevComparable(Period $period): Period
    {
        $diff = $period->end->diff($period->start);
        $days = (int) $diff->format('%a');
        $shift = new DateInterval('P' . max($days + 1, 1) . 'D');

        return new Period($period->start->sub($shift), $period->end->sub($shift));
    }

    /**
     * @return array<int, string>
     */
    public static function rangeDays(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $period = new DatePeriod($start, new DateInterval('P1D'), $end->add(new DateInterval('P1D')));
        $dates = [];
        foreach ($period as $day) {
            $dates[] = $day->format('Y-m-d');
        }

        return $dates;
    }

    public static function fmt(DateTimeImmutable $date, string $pattern): string
    {
        return $date->format($pattern);
    }
}
