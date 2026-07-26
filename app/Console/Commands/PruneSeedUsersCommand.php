<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneSeedUsersCommand extends Command
{
    protected $signature = 'tido:prune-seed-users
                            {--keep=1 : Keep this user id (primary admin)}
                            {--force : Skip the interactive confirmation}';

    protected $description = 'Delete non-primary users and their notifications (local only)';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('tido:prune-seed-users may only run in the local or testing environment.');

            return self::FAILURE;
        }

        $keepId = max(1, (int) $this->option('keep'));
        $toDelete = User::query()->whereKeyNot($keepId)->count();

        if ($toDelete === 0) {
            $this->info("No users to prune (keeping id {$keepId}).");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$toDelete} user(s) except id {$keepId}?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $deletedUsers = 0;
        $deletedNotifications = 0;

        DB::transaction(function () use ($keepId, &$deletedUsers, &$deletedNotifications): void {
            $userIds = User::query()->whereKeyNot($keepId)->pluck('id');

            $deletedNotifications = DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->whereIn('notifiable_id', $userIds)
                ->delete();

            $deletedUsers = User::query()->whereKeyNot($keepId)->delete();
        });

        $this->info("Deleted {$deletedUsers} user(s) and {$deletedNotifications} notification(s). Kept user id {$keepId}.");

        return self::SUCCESS;
    }
}
