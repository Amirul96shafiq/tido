<?php

declare(strict_types=1);

namespace App\Filament\GlobalSearch;

final class GlobalSearchCriteria
{
    private static ?self $instance = null;

    private GlobalSearchType $type = GlobalSearchType::All;

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
     * @param  array<string, mixed>  $filters
     */
    public static function apply(GlobalSearchType $type, string $sort, array $filters): void
    {
        $criteria = self::instance();
        $criteria->type = $type;
        $criteria->sort = $sort;
        $criteria->filters = $filters;
    }

    public function type(): GlobalSearchType
    {
        return $this->type;
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
        return $this->filtersFor($this->type);
    }

    public function activeFilterCount(): int
    {
        if (! $this->type->hasTypeFilters()) {
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
