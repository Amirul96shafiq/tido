<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Label;
use App\Support\FieldCharacterLimits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LabelDuplicator
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

    public function duplicate(Label $source): Label
    {
        $replica = $source->replicate(self::EXCLUDED_ATTRIBUTES);
        $this->prepareReplica($replica);
        $replica->save();

        return $replica->fresh() ?? $replica;
    }

    /**
     * @param  Collection<int, Label>  $sources
     * @return Collection<int, Label>
     */
    public function duplicateMany(Collection $sources): Collection
    {
        return DB::transaction(function () use ($sources): Collection {
            return $sources
                ->map(fn (Label $source): Label => $this->duplicate($source))
                ->values();
        });
    }

    public function prepareReplica(Label $replica): void
    {
        $copyNumber = 1;
        $sourceName = (string) $replica->name;
        $sourceSlug = (string) $replica->slug;

        do {
            $suffix = $copyNumber === 1 ? 'Copy' : "Copy {$copyNumber}";
            $nameSuffix = " ({$suffix})";
            $replica->name = FieldCharacterLimits::truncate(
                $sourceName,
                FieldCharacterLimits::LABEL_NAME - mb_strlen($nameSuffix),
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

    private function slugExists(Label $replica): bool
    {
        return Label::withTrashed()
            ->where('type', $replica->type->value)
            ->where('slug', $replica->slug)
            ->exists();
    }
}
