<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FamilyMember;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;

/**
 * One-time import of legacy PERSONAL_WHATSAPP_* env values into Profile / Family Members.
 */
class WhatsAppAllowlistFromEnvSeeder extends Seeder
{
    public function run(): void
    {
        $primary = PhoneNumber::normalize(
            is_string(config('services.evolution.personal_number'))
                ? config('services.evolution.personal_number')
                : null,
        );

        if ($primary !== null) {
            $admin = User::query()->orderBy('id')->first();

            if ($admin !== null && blank($admin->phone)) {
                $admin->forceFill(['phone' => $primary])->save();
            }
        }

        $extrasRaw = config('services.evolution.personal_extra_numbers');
        $extras = PhoneNumber::parseList(is_string($extrasRaw) ? $extrasRaw : null);

        foreach ($extras as $index => $phone) {
            if (FamilyMember::query()->where('phone', $phone)->exists()) {
                continue;
            }

            if (User::query()->where('phone', $phone)->exists()) {
                continue;
            }

            FamilyMember::query()->create([
                'name' => 'Imported contact '.($index + 1),
                'phone' => $phone,
                'allowlist_enabled' => true,
            ]);
        }
    }
}
