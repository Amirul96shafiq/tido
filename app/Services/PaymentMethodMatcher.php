<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class PaymentMethodMatcher
{
    /** @var Collection<int, PaymentMethod>|null */
    private ?Collection $methods = null;

    public function matchId(mixed $value): ?int
    {
        $method = $this->match($value);

        return $method?->id;
    }

    public function match(mixed $value): ?PaymentMethod
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return null;
        }

        foreach ($this->methods() as $method) {
            if ($method->slug === $normalized || $method->slug === Str::slug($value)) {
                return $method;
            }
        }

        foreach ($this->methods() as $method) {
            if (strcasecmp($method->name, trim($value)) === 0) {
                return $method;
            }
        }

        foreach ($this->methods() as $method) {
            foreach ($method->aliases ?? [] as $alias) {
                if ($this->normalize((string) $alias) === $normalized) {
                    return $method;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, PaymentMethod>
     */
    public function whatsappTokenMap(): array
    {
        $map = [];

        foreach ($this->methods() as $method) {
            $map[$this->normalize($method->slug)] = $method;

            foreach ($method->aliases ?? [] as $alias) {
                $token = $this->normalize((string) $alias);

                if ($token === '') {
                    continue;
                }

                $map[$token] = $method;
            }
        }

        return $map;
    }

    public function defaultCash(): ?PaymentMethod
    {
        return PaymentMethod::findBySlug('cash')
            ?? $this->methods()->firstWhere('slug', 'cash');
    }

    private function normalize(string $value): string
    {
        $normalized = strtolower(trim($value));

        return str_replace([' ', '-', "'"], ['_', '_', ''], $normalized);
    }

    /** @return Collection<int, PaymentMethod> */
    private function methods(): Collection
    {
        return $this->methods ??= PaymentMethod::orderedForSelect();
    }
}
