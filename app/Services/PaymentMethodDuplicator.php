<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentMethod;
use App\Support\FieldCharacterLimits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentMethodDuplicator
{
    /**
     * Attributes that must not carry over to the replica.
     *
     * @var list<string>
     */
    public const EXCLUDED_ATTRIBUTES = [
        'edited_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function duplicate(PaymentMethod $source): PaymentMethod
    {
        $replica = $source->replicate(self::EXCLUDED_ATTRIBUTES);
        $this->prepareReplica($replica);
        $replica->save();

        return $replica->fresh() ?? $replica;
    }

    /**
     * @param  Collection<int, PaymentMethod>  $sources
     * @return Collection<int, PaymentMethod>
     */
    public function duplicateMany(Collection $sources): Collection
    {
        return DB::transaction(function () use ($sources): Collection {
            return $sources
                ->map(fn (PaymentMethod $source): PaymentMethod => $this->duplicate($source))
                ->values();
        });
    }

    public function prepareReplica(PaymentMethod $replica): void
    {
        $copyNumber = 1;
        $sourceName = (string) $replica->name;
        $sourceSlug = (string) $replica->slug;

        do {
            $suffix = $copyNumber === 1 ? 'Copy' : "Copy {$copyNumber}";
            $nameSuffix = " ({$suffix})";
            $replica->name = FieldCharacterLimits::truncate(
                $sourceName,
                FieldCharacterLimits::PAYMENT_METHOD_NAME - mb_strlen($nameSuffix),
            ).$nameSuffix;
            $replica->slug = $this->copySlug($sourceSlug, $copyNumber);
            $copyNumber++;
        } while ($this->slugExists($replica));

        $replica->is_system = false;
    }

    private function copySlug(string $slug, int $copyNumber): string
    {
        $suffix = $copyNumber === 1 ? '-copy' : "-copy-{$copyNumber}";

        return mb_substr($slug, 0, 255 - mb_strlen($suffix)).$suffix;
    }

    private function slugExists(PaymentMethod $replica): bool
    {
        return PaymentMethod::withTrashed()
            ->where('slug', $replica->slug)
            ->exists();
    }
}
