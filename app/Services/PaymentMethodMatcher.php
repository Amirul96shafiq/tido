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
        return $this->match($value)?->id
            ?? $this->defaultOther()?->id;
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

        $candidates = array_values(array_unique(array_filter([
            $normalized,
            $this->normalize(Str::slug($value)),
            $this->baseToken($normalized),
        ])));

        foreach ($candidates as $candidate) {
            foreach ($this->methods() as $method) {
                if ($method->slug === $candidate) {
                    return $method;
                }
            }
        }

        foreach ($this->methods() as $method) {
            if (strcasecmp($method->name, trim($value)) === 0) {
                return $method;
            }
        }

        foreach ($candidates as $candidate) {
            foreach ($this->methods() as $method) {
                foreach ($method->aliases ?? [] as $alias) {
                    if ($this->normalize((string) $alias) === $candidate) {
                        return $method;
                    }
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

    public function defaultOther(): ?PaymentMethod
    {
        return PaymentMethod::findBySlug('other')
            ?? $this->methods()->firstWhere('slug', 'other');
    }

    private function normalize(string $value): string
    {
        $normalized = strtolower(trim($value));

        return str_replace([' ', '-', "'"], ['_', '_', ''], $normalized);
    }

    /**
     * Strip trailing parenthetical / slash codes (e.g. "MyDebit (009659/587840)" → "mydebit").
     */
    private function baseToken(string $normalized): string
    {
        $withoutParen = preg_replace('/_?\([^)]*\)\z/u', '', $normalized) ?? $normalized;
        $withoutSlashCodes = preg_replace('/_+\d.*\z/u', '', $withoutParen) ?? $withoutParen;

        return rtrim($withoutSlashCodes, '_');
    }

    /** @return Collection<int, PaymentMethod> */
    private function methods(): Collection
    {
        return $this->methods ??= PaymentMethod::orderedForSelect();
    }
}
