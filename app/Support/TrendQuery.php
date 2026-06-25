<?php

namespace App\Support;

use Closure;

/**
 * Builds a per-day time series for dashboard charts.
 *
 * Database-agnostic on purpose: instead of GROUP BY date() (which differs
 * between MySQL and the SQLite used in tests), it runs one cheap aggregate
 * per day with whereDate(). For a 14–30 day dashboard window that's fine.
 *
 * Pass a closure that returns a FRESH query builder each call so the daily
 * filters never accumulate on a shared instance.
 */
class TrendQuery
{
    /**
     * @param  Closure():\Illuminate\Database\Eloquent\Builder  $query  fresh base query each call
     * @param  int  $days  how many days back (inclusive of today)
     * @param  string|null  $sumColumn  column to SUM per day (e.g. 'total'); null to skip
     * @return array{labels: string[], counts: int[], sums: float[]}
     */
    public static function daily(Closure $query, int $days = 14, ?string $sumColumn = null): array
    {
        $labels = [];
        $counts = [];
        $sums = [];

        $start = now()->subDays($days - 1)->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $date = $day->toDateString();

            $labels[] = $day->format('M j');
            $counts[] = $query()->whereDate('created_at', $date)->count();

            if ($sumColumn !== null) {
                $sums[] = (float) $query()->whereDate('created_at', $date)->sum($sumColumn);
            }
        }

        return ['labels' => $labels, 'counts' => $counts, 'sums' => $sums];
    }
}
