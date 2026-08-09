<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\HouseholdRole;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class DashboardSpenderScope
{
    public const ALL = 'all';

    public const PRIMARY = 'primary';

    private const FAMILY_PREFIX = 'family:';

    public function __construct(
        private readonly string $spender,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function fromFilters(array $filters): self
    {
        $spender = $filters['spender'] ?? self::ALL;

        if (! is_string($spender) || ! self::isValid($spender)) {
            return new self(self::ALL);
        }

        return new self($spender);
    }

    public static function defaultFor(?User $user = null): self
    {
        $user ??= Auth::user();

        if ($user instanceof User && $user->isFamilyMember() && $user->family_member_id !== null) {
            return new self(self::familyValue((int) $user->family_member_id));
        }

        return new self(self::ALL);
    }

    public function value(): string
    {
        return $this->spender;
    }

    public static function familyValue(int $familyMemberId): string
    {
        return self::FAMILY_PREFIX.$familyMemberId;
    }

    public static function isValid(string $spender): bool
    {
        if ($spender === self::ALL || $spender === self::PRIMARY) {
            return true;
        }

        if (! str_starts_with($spender, self::FAMILY_PREFIX)) {
            return false;
        }

        $id = substr($spender, strlen(self::FAMILY_PREFIX));

        return $id !== '' && ctype_digit($id);
    }

    /**
     * @return array<string, string>
     */
    public static function filterOptionsFor(?User $user = null): array
    {
        return (new self(self::ALL))->optionsFor($user);
    }

    /**
     * @return array<string, string>
     */
    public function optionsFor(?User $user = null): array
    {
        $user ??= Auth::user();

        $primaryLabel = self::primaryUserLabel();

        if ($user instanceof User && $user->isPrimary()) {
            $primaryLabel .= ' (me)';
        }

        $options = [
            self::ALL => 'All',
            self::PRIMARY => $primaryLabel,
        ];

        $members = FamilyMember::query()
            ->orderBy('name')
            ->get(['id', 'name', 'display_name']);

        foreach ($members as $member) {
            $key = self::familyValue((int) $member->id);
            $label = filled($member->display_name)
                ? (string) $member->display_name
                : (string) $member->name;

            if ($user instanceof User && $user->isFamilyMember()) {
                if ((int) $user->family_member_id === (int) $member->id) {
                    $options[$key] = $label.' (me)';
                }

                continue;
            }

            $options[$key] = $label;
        }

        if ($user instanceof User && $user->isFamilyMember()) {
            unset($options[self::PRIMARY]);

            foreach (array_keys($options) as $key) {
                if (str_starts_with($key, self::FAMILY_PREFIX) && $key !== self::familyValue((int) $user->family_member_id)) {
                    unset($options[$key]);
                }
            }
        }

        return $options;
    }

    private static function primaryUserLabel(): string
    {
        $primaryUser = User::query()
            ->where(function ($query): void {
                $query
                    ->where('household_role', HouseholdRole::Primary->value)
                    ->orWhereNull('household_role');
            })
            ->orderBy('id')
            ->first(['name']);

        if (! $primaryUser instanceof User || ! filled($primaryUser->name)) {
            return 'Primary';
        }

        return (string) $primaryUser->name;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyToExpenseQuery(Builder $query): Builder
    {
        if ($this->spender === self::ALL) {
            return $query;
        }

        if ($this->spender === self::PRIMARY) {
            return $query->whereNull('family_member_id');
        }

        if (str_starts_with($this->spender, self::FAMILY_PREFIX)) {
            $id = (int) substr($this->spender, strlen(self::FAMILY_PREFIX));

            return $query->where('family_member_id', $id);
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyToExpensesJoin(Builder $query, string $expensesAlias = 'expenses'): Builder
    {
        if ($this->spender === self::ALL) {
            return $query;
        }

        if ($this->spender === self::PRIMARY) {
            return $query->whereNull("{$expensesAlias}.family_member_id");
        }

        if (str_starts_with($this->spender, self::FAMILY_PREFIX)) {
            $id = (int) substr($this->spender, strlen(self::FAMILY_PREFIX));

            return $query->where("{$expensesAlias}.family_member_id", $id);
        }

        return $query;
    }
}
