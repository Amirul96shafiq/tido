<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Label;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LabelMatcher
{
    /** @var Collection<int, Label>|null */
    private ?Collection $financeLabels = null;

    public function matchId(?string $labelName): ?int
    {
        if ($labelName === null || trim($labelName) === '') {
            return null;
        }

        $normalized = trim($labelName);
        $slug = Str::slug($normalized);

        foreach ($this->financeLabels() as $label) {
            if ($label->slug === $slug) {
                return $label->id;
            }
        }

        foreach ($this->financeLabels() as $label) {
            if (strcasecmp($label->name, $normalized) === 0) {
                return $label->id;
            }
        }

        return null;
    }

    /** @return Collection<int, Label> */
    private function financeLabels(): Collection
    {
        return $this->financeLabels ??= Label::financeLabels();
    }
}
