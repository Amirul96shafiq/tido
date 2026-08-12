<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RecurringReminderService;
use Illuminate\Console\Command;

class RecurringSendRemindersCommand extends Command
{
    protected $signature = 'recurring:send-reminders';

    protected $description = 'Send Filament and WhatsApp reminders for due or overdue recurring occurrences';

    public function handle(RecurringReminderService $reminders): int
    {
        $result = $reminders->sendDueReminders();

        $this->info(sprintf('Sent %d reminder(s).', $result['reminded']));

        return self::SUCCESS;
    }
}
