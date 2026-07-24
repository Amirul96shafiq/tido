<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\UserAgentDevice;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ActiveSessionService
{
    /**
     * @return Collection<int, ActiveSessionData>
     */
    public function listFor(User $user, ?string $currentSessionId = null): Collection
    {
        $currentSessionId ??= (string) session()->getId();

        $this->stampCreatedAt($currentSessionId);

        return DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $row): ActiveSessionData => $this->mapSession($row, $currentSessionId, $user));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function recordsForTable(User $user, ?string $currentSessionId = null): array
    {
        return $this->listFor($user, $currentSessionId)
            ->mapWithKeys(fn (ActiveSessionData $session): array => [
                $session->id => [
                    'id' => $session->id,
                    'device_class' => $session->deviceClass,
                    'device_detail' => $session->deviceDetail,
                    'created_at' => $session->createdAt,
                    'is_current' => $session->isCurrent,
                ],
            ])
            ->all();
    }

    public function stampCreatedAt(?string $sessionId): void
    {
        if ($sessionId === null || $sessionId === '') {
            return;
        }

        DB::table('sessions')
            ->where('id', $sessionId)
            ->whereNull('created_at')
            ->update(['created_at' => now()->timestamp]);
    }

    public function revoke(User $user, string $sessionId, string $currentSessionId): void
    {
        if ($sessionId === $currentSessionId) {
            throw new InvalidArgumentException('Cannot revoke the current session.');
        }

        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->getKey())
            ->delete();
    }

    private function mapSession(object $row, string $currentSessionId, User $user): ActiveSessionData
    {
        $device = UserAgentDevice::parse(isset($row->user_agent) ? (string) $row->user_agent : null);
        $createdAtTimestamp = $row->created_at ?? $row->last_activity;

        return new ActiveSessionData(
            id: (string) $row->id,
            deviceClass: $device->deviceClass,
            deviceDetail: $device->detail(isset($row->ip_address) ? (string) $row->ip_address : null),
            createdAt: $this->resolveCreatedAt((int) $createdAtTimestamp, $user),
            isCurrent: (string) $row->id === $currentSessionId,
        );
    }

    private function resolveCreatedAt(int $timestamp, User $user): CarbonInterface
    {
        $timezone = filled($user->timezone) ? (string) $user->timezone : config('app.timezone');

        return Carbon::createFromTimestamp($timestamp, $timezone);
    }
}
