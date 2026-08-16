<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FamilyMember;
use App\Support\FieldCharacterLimits;
use Illuminate\Support\Facades\DB;

class FamilyMemberDuplicator
{
    /**
     * Attributes that must not carry over to the duplicate.
     *
     * @var list<string>
     */
    public const EXCLUDED_ATTRIBUTES = [
        'edited_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * @param  array{phone: string, allowlist_enabled: bool, login_enabled: bool}  $data
     */
    public function duplicate(FamilyMember $source, array $data): FamilyMember
    {
        return DB::transaction(function () use ($source, $data): FamilyMember {
            $replica = $source->replicate(self::EXCLUDED_ATTRIBUTES);
            $replica->fill([
                'name' => $this->copyName($source->name),
                'phone' => $data['phone'],
                'whatsapp_lid' => null,
                'avatar_url' => null,
                'allowlist_enabled' => $data['allowlist_enabled'],
                'login_enabled' => $data['login_enabled'],
            ]);
            $replica->save();

            return $replica->fresh() ?? $replica;
        });
    }

    private function copyName(string $name): string
    {
        $suffix = ' (Copy)';

        return FieldCharacterLimits::truncate(
            $name,
            FieldCharacterLimits::USER_NAME - mb_strlen($suffix),
        ).$suffix;
    }
}
