<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use App\Support\CurrentHousehold;
use Database\Seeders\LabelSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * MH-007: Register creates a new household + Primary (never joins household #1).
 */
final class HouseholdRegistrationService
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function register(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $household = Household::query()->create([
                'name' => $attributes['name'].' Household',
            ]);

            CurrentHousehold::set((int) $household->id);

            try {
                $user = new User;
                $user->forceFill([
                    'household_id' => $household->id,
                    'name' => $attributes['name'],
                    'display_name' => $attributes['name'],
                    'email' => $attributes['email'],
                    'password' => Hash::make($attributes['password']),
                    'household_role' => HouseholdRole::Primary,
                    'email_verified_at' => now(),
                    'remember_token' => Str::random(10),
                    'timezone' => 'Asia/Kuala_Lumpur',
                    'locale' => 'en',
                    'date_format' => 'd/m/Y',
                ])->save();

                (new LabelSeeder)->run();
                (new PaymentMethodSeeder)->run();

                return $user->fresh();
            } finally {
                CurrentHousehold::clear();
            }
        });
    }
}
