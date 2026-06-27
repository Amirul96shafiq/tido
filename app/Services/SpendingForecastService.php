<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class SpendingForecastService
{
    /**
     * @return array{forecast: float, confidence: string}
     */
    public function forecastMonthlySpend(): array
    {
        $now = now();
        $startLimit = $now->copy()->subDays(90)->startOfDay();

        $dailyTotals = Invoice::where('date_time', '>=', $startLimit)
            ->whereIn('status', ['parsed', 'reviewed'])
            ->selectRaw('DATE(date_time) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $x = [];
        $y = [];

        $dayIndex = 0;
        $dateMap = [];

        for ($i = 90; $i >= 0; $i--) {
            $dayStr = $now->copy()->subDays($i)->toDateString();
            $dateMap[$dayStr] = 0.00;
        }

        foreach ($dailyTotals as $row) {
            $dateMap[(string) $row->getAttribute('date')] = (float) $row->getAttribute('total');
        }

        foreach ($dateMap as $date => $total) {
            $x[] = $dayIndex++;
            $y[] = $total;
        }

        $n = count($x);

        if ($n === 0) {
            return ['forecast' => 0.00, 'confidence' => 'low'];
        }

        $sumX = array_sum($x);
        $sumY = array_sum($y);

        $sumXY = 0;
        $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumXX += $x[$i] * $x[$i];
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        $slope = $denominator !== 0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denominator : 0;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        $endOfMonthDays = $now->daysInMonth;
        $currentMonthStartOffset = abs((int) $startLimit->diffInDays($now->copy()->startOfMonth()));

        $projectedTotal = 0.00;

        for ($d = 0; $d < $endOfMonthDays; $d++) {
            $offset = $currentMonthStartOffset + $d;
            $dailyForecast = ($slope * $offset) + $intercept;
            $projectedTotal += max(0, $dailyForecast);
        }

        return [
            'forecast' => round($projectedTotal, 2),
            'confidence' => $slope !== 0 ? 'medium' : 'low',
        ];
    }
}
