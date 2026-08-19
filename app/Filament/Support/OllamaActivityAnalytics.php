<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class OllamaActivityAnalytics
{
    /**
     * @return array{
     *     labels: list<string>,
     *     parsed: list<float>,
     *     reviewed: list<float>,
     *     manual_review: list<float>,
     *     pdf: list<float>,
     *     image: list<float>,
     *     text_only: list<float>,
     * }
     */
    public function trend(int $months = 6, ?Carbon $endMonth = null): array
    {
        $endMonth ??= Carbon::now()->startOfMonth();
        $rangeStart = $endMonth->copy()->subMonths($months - 1)->startOfMonth();
        $rangeEnd = $endMonth->copy()->endOfMonth();
        $monthExpression = $this->monthTruncExpression('expenses.created_at');

        /** @var array<string, array<string, int>> $statusCountsByMonth */
        $statusCountsByMonth = [];

        $statusRows = Expense::query()
            ->selectRaw("{$monthExpression} as month_key, status, COUNT(*) as total")
            ->whereIn('status', ['parsed', 'reviewed', 'requires_manual_review'])
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->groupBy('month_key', 'status')
            ->get();

        foreach ($statusRows as $row) {
            $monthKey = (string) $row->month_key;
            $statusCountsByMonth[$monthKey][(string) $row->status] = (int) $row->total;
        }

        $pdfCountsByMonth = $this->monthlyCountsByMimeType(
            $monthExpression,
            $rangeStart,
            $rangeEnd,
            fn ($query) => $query->where('file_mime_type', 'application/pdf'),
        );

        $imageCountsByMonth = $this->monthlyCountsByMimeType(
            $monthExpression,
            $rangeStart,
            $rangeEnd,
            fn ($query) => $query->where('file_mime_type', 'like', 'image/%'),
        );

        $textOnlyCountsByMonth = $this->monthlyCountsByMimeType(
            $monthExpression,
            $rangeStart,
            $rangeEnd,
            fn ($query) => $query->where(function ($innerQuery): void {
                $innerQuery
                    ->whereNull('file_mime_type')
                    ->orWhere('file_mime_type', '');
            }),
        );

        $labels = [];
        $parsed = [];
        $reviewed = [];
        $manualReview = [];
        $pdf = [];
        $image = [];
        $textOnly = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $endMonth->copy()->subMonths($months - 1 - $i);
            $key = $month->format('Y-m');
            $labels[] = $month->format('m/y');
            $statusCounts = $statusCountsByMonth[$key] ?? [];

            $parsed[] = (float) ($statusCounts['parsed'] ?? 0);
            $reviewed[] = (float) ($statusCounts['reviewed'] ?? 0);
            $manualReview[] = (float) ($statusCounts['requires_manual_review'] ?? 0);
            $pdf[] = (float) ($pdfCountsByMonth[$key] ?? 0);
            $image[] = (float) ($imageCountsByMonth[$key] ?? 0);
            $textOnly[] = (float) ($textOnlyCountsByMonth[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'parsed' => $parsed,
            'reviewed' => $reviewed,
            'manual_review' => $manualReview,
            'pdf' => $pdf,
            'image' => $image,
            'text_only' => $textOnly,
        ];
    }

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  callable(Builder<Expense>): void  $constraint
     * @return array<string, int>
     */
    private function monthlyCountsByMimeType(
        string $monthExpression,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        callable $constraint,
    ): array {
        $query = Expense::query()
            ->selectRaw("{$monthExpression} as month_key, COUNT(*) as total")
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->groupBy('month_key');

        $constraint($query);

        return $query
            ->pluck('total', 'month_key')
            ->map(fn (int|string $total): int => (int) $total)
            ->all();
    }

    private function monthTruncExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR(DATE_TRUNC('month', {$column}), 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
