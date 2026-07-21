<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EvolutionApiConnectionEvent;
use App\Models\EvolutionApiConnectionLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvolutionApiConnectionLog>
 */
class EvolutionApiConnectionLogFactory extends Factory
{
    protected $model = EvolutionApiConnectionLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $event = fake()->randomElement(EvolutionApiConnectionEvent::cases());

        return [
            'event' => $event,
            'status' => match ($event) {
                EvolutionApiConnectionEvent::Connected => 'open',
                EvolutionApiConnectionEvent::Disconnected, EvolutionApiConnectionEvent::Logout => 'close',
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
            'event' => EvolutionApiConnectionEvent::Connected,
            'status' => 'open',
            'message' => 'EvolutionAPI session connected.',
        ]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (): array => [
            'event' => EvolutionApiConnectionEvent::Disconnected,
            'status' => 'close',
            'message' => 'EvolutionAPI session disconnected.',
        ]);
    }

    public function logout(): static
    {
        return $this->state(fn (): array => [
            'event' => EvolutionApiConnectionEvent::Logout,
            'status' => 'close',
            'message' => 'EvolutionAPI session logged out.',
            'meta' => ['source' => 'logout'],
        ]);
    }
}
