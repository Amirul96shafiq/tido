<?php

declare(strict_types=1);

namespace App\Filament\Livewire;

use App\Enums\NotificationResource;
use App\Enums\UserDateFormat;
use App\Helpers\UserDateDisplay;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Livewire\DatabaseNotifications as BaseDatabaseNotifications;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Support\Carbon;

class DatabaseNotifications extends BaseDatabaseNotifications
{
    public const NOTIFICATIONS_PER_PAGE = 10;

    public string $search = '';

    public bool $filtersOpen = false;

    /**
     * @var array{resource: ?string, from: ?string, until: ?string, status: ?string}
     */
    public array $filters = [
        'resource' => null,
        'from' => null,
        'until' => null,
        'status' => null,
    ];

    public function updated(string $property): void
    {
        if ($property === 'search' || str_starts_with($property, 'filters.')) {
            $this->resetPage(pageName: 'database-notifications-page');
        }
    }

    public function toggleFilters(): void
    {
        $this->filtersOpen = ! $this->filtersOpen;
    }

    public function closeFilters(): void
    {
        $this->filtersOpen = false;
    }

    public function resetFilters(): void
    {
        $this->filters = [
            'resource' => null,
            'from' => null,
            'until' => null,
            'status' => null,
        ];

        $this->resetPage(pageName: 'database-notifications-page');
    }

    public function clearSearchAndFilters(): void
    {
        $this->search = '';
        $this->filtersOpen = false;
        $this->resetFilters();

        $this->dispatch('database-notifications-search-cleared');
    }

    public function getNotifications(): DatabaseNotificationCollection|Paginator
    {
        if (! $this->isPaginated()) {
            /** @phpstan-ignore-next-line */
            return $this->getFilteredNotificationsQuery()->get();
        }

        return $this->getFilteredNotificationsQuery()->paginate(
            self::NOTIFICATIONS_PER_PAGE,
            pageName: 'database-notifications-page',
        );
    }

    public function hasAnyNotifications(): bool
    {
        return $this->getNotificationsQuery()->exists();
    }

    public function getActiveFiltersCount(): int
    {
        return collect($this->filters)
            ->filter(fn (mixed $value): bool => filled($value))
            ->count();
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->live()
            ->components([
                Select::make('resource')
                    ->label('Resource')
                    ->options(NotificationResource::options())
                    ->searchable()
                    ->native(false)
                    ->placeholder('All resources'),
                DatePicker::make('from')
                    ->label('From')
                    ->native(false)
                    ->placeholder(fn (): string => $this->dateFilterPlaceholder()),
                DatePicker::make('until')
                    ->label('Until')
                    ->native(false)
                    ->placeholder(fn (): string => $this->dateFilterPlaceholder()),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'unread' => 'Unread',
                        'read' => 'Read',
                    ])
                    ->searchable()
                    ->native(false)
                    ->placeholder('All'),
            ]);
    }

    protected function dateFilterPlaceholder(): string
    {
        return match (UserDateDisplay::dateFormat()) {
            UserDateFormat::DmySlash->value => 'dd/mm/yyyy',
            UserDateFormat::DmyLong->value => 'dd M yyyy',
            UserDateFormat::Iso->value => 'yyyy-mm-dd',
            default => UserDateDisplay::dateFormat(),
        };
    }

    protected function getFilteredNotificationsQuery(): Builder|Relation
    {
        $query = $this->getNotificationsQuery();

        if (filled($this->search)) {
            $search = addcslashes(trim($this->search), '%_\\');

            $query->where('data', 'like', "%{$search}%");
        }

        if (filled($this->filters['resource'] ?? null)) {
            $resource = NotificationResource::tryFrom((string) $this->filters['resource']);

            if ($resource instanceof NotificationResource) {
                $titlePrefix = rtrim($resource->titleSearchPattern(), '%');

                $query->where('data', 'like', '%"title":"'.$titlePrefix.'%');
            }
        }

        if (filled($this->filters['from'] ?? null)) {
            $query->whereDate(
                'created_at',
                '>=',
                Carbon::parse((string) $this->filters['from'])->toDateString(),
            );
        }

        if (filled($this->filters['until'] ?? null)) {
            $query->whereDate(
                'created_at',
                '<=',
                Carbon::parse((string) $this->filters['until'])->toDateString(),
            );
        }

        match ($this->filters['status'] ?? null) {
            'unread' => $query->unread(),
            'read' => $query->whereNotNull('read_at'),
            default => null,
        };

        return $query;
    }

    public function render(): View
    {
        return view('filament.livewire.database-notifications');
    }
}
