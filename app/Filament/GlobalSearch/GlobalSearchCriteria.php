<?php

declare(strict_types=1);

namespace App\Filament\GlobalSearch;

final class GlobalSearchCriteria
{
    private static ?self $instance = null;

    /**
     * @var list<GlobalSearchType>
     */
    private array $types = [GlobalSearchType::All];

    private string $sort = 'default';

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $filters = [];

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * @param  GlobalSearchType|list<GlobalSearchType|string>|string  $types
     * @param  array<string, mixed>  $filters
     */
    public static function apply(GlobalSearchType|array|string $types, string $sort, array $filters): void
    {
        $criteria = self::instance();
        $criteria->types = GlobalSearchType::tryFromValues($types);
        $criteria->sort = $sort;
        $criteria->filters = $filters;
    }

    /**
     * @return list<GlobalSearchType>
     */
    public function types(): array
    {
        return $this->types;
    }

    public function type(): GlobalSearchType
    {
        return count($this->types) === 1 ? $this->types[0] : GlobalSearchType::All;
    }

    public function isAll(): bool
    {
        return count($this->types) === 1 && $this->types[0] === GlobalSearchType::All;
    }

    public function isOnly(GlobalSearchType $type): bool
    {
        return count($this->types) === 1 && $this->types[0] === $type;
    }

    public function includes(GlobalSearchType $type): bool
    {
        if ($this->isAll()) {
            return true;
        }

        return in_array($type, $this->types, true);
    }

    public function includesResource(string $resourceClass): bool
    {
        if ($this->isAll()) {
            return true;
        }

        foreach ($this->types as $type) {
            if ($type->resourceClass() === $resourceClass) {
                return true;
            }
        }

        return false;
    }

    public function includesPages(): bool
    {
        return $this->includes(GlobalSearchType::Pages);
    }

    public function includesSections(): bool
    {
        return $this->includes(GlobalSearchType::Sections);
    }

    public function includesDestinations(): bool
    {
        return $this->includesPages() || $this->includesSections();
    }

    public function includesResources(): bool
    {
        if ($this->isAll()) {
            return true;
        }

        foreach ($this->types as $type) {
            if ($type->resourceClass() !== null) {
                return true;
            }
        }

        return false;
    }

    public function hasTypeFilters(): bool
    {
        return count($this->types) === 1 && $this->types[0]->hasTypeFilters();
    }

    public function usesSharedTitleSort(): bool
    {
        return in_array($this->sort, ['title_asc', 'title_desc'], true)
            && ($this->isAll() || count($this->types) > 1);
    }

    public function sort(): string
    {
        return $this->sort;
    }

    /**
     * @return array<string, mixed>
     */
    public function filtersFor(GlobalSearchType $type): array
    {
        return $this->filters[$type->value] ?? $type->defaultFilters();
    }

    /**
     * @return array<string, mixed>
     */
    public function activeFilters(): array
    {
        if (! $this->hasTypeFilters()) {
            return [];
        }

        return $this->filtersFor($this->types[0]);
    }

    public function activeFilterCount(): int
    {
        if (! $this->hasTypeFilters()) {
            return 0;
        }

        return collect($this->activeFilters())
            ->filter(fn (mixed $value): bool => filled($value))
            ->count();
    }

    public function hasActiveFilters(): bool
    {
        return $this->activeFilterCount() > 0;
    }
}
