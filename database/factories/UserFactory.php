<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'display_name' => fake()->optional()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => '60'.fake()->unique()->numerify('1########'),
            'timezone' => 'Asia/Kuala_Lumpur',
            'locale' => 'en',
            'date_format' => 'd/m/Y',
            'notify_budget_alerts' => true,
            'notify_profile_updates' => true,
            'notify_email_digest' => false,
            'notify_evolution_api' => true,
            'stylized_background_enabled' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Set a specific WhatsApp phone for OTP login and bot allowlist tests.
     */
    public function withWhatsAppPhone(?string $phone = null): static
    {
        return $this->state(function () use ($phone): array {
            $resolved = $phone ?? '60123456789';

            return [
                'phone' => PhoneNumber::normalize($resolved),
            ];
        });
    }
}
