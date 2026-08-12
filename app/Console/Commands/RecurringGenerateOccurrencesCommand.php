<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RecurringOccurrenceGenerator;
use Illuminate\Console\Command;

class RecurringGenerateOccurrencesCommand extends Command
{
    protected $signature = 'recurring:generate-occurrences';

    protected $description = 'Generate recurring occurrences and refresh due/overdue statuses';

    public function handle(RecurringOccurrenceGenerator $generator): int
    {
        $result = $generator->run();

        $this->info(sprintf(
            'Generated %d occurrence(s); updated %d status(es).',
            $result['generated'],
            $result['status_updates'],
        ));

        return self::SUCCESS;
    }
}
