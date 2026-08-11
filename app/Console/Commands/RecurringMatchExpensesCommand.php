<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RecurringMatchService;
use Illuminate\Console\Command;

class RecurringMatchExpensesCommand extends Command
{
    protected $signature = 'recurring:match-expenses
                            {--dry-run : List matches without completing occurrences}';

    protected $description = 'Match parsed/reviewed expenses to open recurring occurrences';

    public function handle(RecurringMatchService $matchService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $matchService->matchParsedExpenses($dryRun);

        if ($result['matched'] === 0) {
            $this->info(sprintf(
                'Scanned %d expense(s); no new matches.',
                $result['scanned'],
            ));

            return self::SUCCESS;
        }

        $rows = array_map(
            static fn (array $match): array => [
                (string) $match['occurrence_id'],
                $match['recurring_title'],
                $match['due_on'],
                (string) $match['expense_id'],
                (string) ($match['merchant_name'] ?? ''),
                $match['actual_amount'] !== null ? 'RM '.$match['actual_amount'] : '—',
            ],
            $result['matches'],
        );

        $this->table(
            ['Occurrence', 'Recurring', 'Due on', 'Expense', 'Merchant', 'Amount'],
            $rows,
        );

        $this->info(sprintf(
            '%s %d match(es) from %d scanned expense(s).',
            $dryRun ? 'Would complete' : 'Completed',
            $result['matched'],
            $result['scanned'],
        ));

        return self::SUCCESS;
    }
}
