<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FamilyRelationship;
use App\Models\FamilyMember;
use App\Models\Invoice;
use App\Services\FamilyMemberLoginService;
use Illuminate\Database\Seeder;

class FamilyMemberLoginTestSeeder extends Seeder
{
    public const SAMPLE_PHONE = '60111222333';

    public function run(): void
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            return;
        }

        $member = FamilyMember::query()->updateOrCreate(
            ['phone' => self::SAMPLE_PHONE],
            [
                'name' => 'Sample Spouse',
                'display_name' => 'Spouse',
                'relationship' => FamilyRelationship::Spouse,
                'allowlist_enabled' => true,
                'login_enabled' => true,
            ],
        );

        app(FamilyMemberLoginService::class)->syncLoginUser($member);

        if (Invoice::query()->where('family_member_id', $member->id)->exists()) {
            return;
        }

        Invoice::factory()->create([
            'merchant_name' => 'Family Groceries',
            'source' => 'whatsapp',
            'whatsapp_sender' => self::SAMPLE_PHONE,
            'family_member_id' => $member->id,
            'status' => 'parsed',
            'total_amount' => 85.50,
            'subtotal' => 85.50,
        ]);

        Invoice::factory()->create([
            'merchant_name' => 'Family Cafe',
            'source' => 'whatsapp',
            'whatsapp_sender' => self::SAMPLE_PHONE,
            'family_member_id' => $member->id,
            'status' => 'reviewed',
            'total_amount' => 42.00,
            'subtotal' => 42.00,
        ]);
    }
}
