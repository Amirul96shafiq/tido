<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ExpenseUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public int $expenseId,
        public string $status,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('household.expenses'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ExpenseUpdated';
    }

    /**
     * @return array{id: int, status: string}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->expenseId,
            'status' => $this->status,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'default';
    }
}
