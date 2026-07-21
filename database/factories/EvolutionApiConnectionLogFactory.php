<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WhatsAppConnectionEvent;
use App\Models\WhatsAppConnectionLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppConnectionLog>
 */
class WhatsAppConnectionLogFactory extends Factory
{
    protected $model = WhatsAppConnectionLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $event = fake()->randomElement(WhatsAppConnectionEvent::cases());

        return [
            'event' => $event,
            'status' => match ($event) {
                WhatsAppConnectionEvent::Connected => 'open',
                WhatsAppConnectionEvent::Disconnected, WhatsAppConnectionEvent::Logout => 'close',
            },
            'connected_number' => '601'.fake()->numerify('########'),
            'profile_name' => fake()->userName(),
            'instance_name' => 'tido',
            'message' => $event->label(),
            'meta' => [
                'source' => 'page',
            ],
        ];
    }

    public function connected(): static
    {
        return $this->state(fn (): array => [
            'event' => WhatsAppConnectionEvent::Connected,
            'status' => 'open',
            'message' => 'WhatsApp session connected.',
        ]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (): array => [
            'event' => WhatsAppConnectionEvent::Disconnected,
            'status' => 'close',
            'message' => 'WhatsApp session disconnected.',
        ]);
    }

    public function logout(): static
    {
        return $this->state(fn (): array => [
            'event' => WhatsAppConnectionEvent::Logout,
            'status' => 'close',
            'message' => 'WhatsApp session logged out.',
            'meta' => ['source' => 'logout'],
        ]);
    }
}
