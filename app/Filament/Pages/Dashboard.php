<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Support\DashboardMonthPeriod;
use App\Models\User;
use App\Support\TimeOfDayGreeting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Enums\VerticalAlignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;
    use PrependsHomeBreadcrumb;

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            'tido-dashboard-greeting',
        ];
    }

    /**
     * @return int|array<string, int>
     */
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 12,
        ];
    }

    public function booted(): void
    {
        if (! isset($this->filters['month'])) {
            $this->filters = [
                'month' => DashboardMonthPeriod::fromFilters($this->filters)->format('Y-m'),
            ];
        }
    }

    public function updatedFiltersMonth(): void
    {
        $this->updatedFilters();
    }

    public function getHeading(): string|Htmlable
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return parent::getHeading();
        }

        $now = now()->timezone($user->preferredTimezone());

        return TimeOfDayGreeting::headingHtmlFor($now, $user->name);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return parent::getSubheading();
        }

        $now = now()->timezone($user->preferredTimezone());

        return TimeOfDayGreeting::subheadingHtml($now);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('profile')
                ->label('Profile')
                ->icon('heroicon-o-user')
                ->color('primary')
                ->url(EditProfile::getUrl()),
            Action::make('changelogs')
                ->label('Changelogs')
                ->icon('heroicon-o-code-bracket')
                ->color('primary')
                ->action(fn (): mixed => $this->js('window.showChangelogModal()')),
        ];
    }

    public function getFiltersForm(): Schema
    {
        if ((! $this->isCachingSchemas) && $this->hasCachedSchema('filtersForm')) {
            return $this->getSchema('filtersForm');
        }

        $schema = $this->makeSchema()
            ->columns(1)
            ->extraAttributes(['wire:partial' => 'table-filters-form'])
            ->live()
            ->statePath('filters');

        return $this->filtersForm($schema);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Flex::make([
                    Select::make('month')
                        ->label('Month')
                        ->options(DashboardMonthPeriod::options())
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->selectablePlaceholder(false)
                        ->grow(false)
                        ->prefixAction(
                            Action::make('previousMonth')
                                ->label('Previous month')
                                ->tooltip('Previous month')
                                ->icon('heroicon-m-chevron-left')
                                ->iconButton()
                                ->action(function (): void {
                                    $this->shiftDashboardMonth(-1);
                                }),
                            isInline: true,
                        )
                        ->suffixAction(
                            Action::make('nextMonth')
                                ->label('Next month')
                                ->tooltip('Next month')
                                ->icon('heroicon-m-chevron-right')
                                ->iconButton()
                                ->disabled(fn (): bool => DashboardMonthPeriod::isCurrentMonth(
                                    DashboardMonthPeriod::fromFilters($this->filters),
                                ))
                                ->action(function (): void {
                                    $this->shiftDashboardMonth(1);
                                }),
                            isInline: true,
                        )
                        ->extraFieldWrapperAttributes([
                            'class' => 'fi-dashboard-month-filter',
                        ]),
                    Actions::make([
                        Action::make('resetMonth')
                            ->label('Reset')
                            ->tooltip('Reset')
                            ->icon('heroicon-o-arrow-path')
                            ->button()
                            ->hiddenLabel()
                            ->color('primary')
                            ->disabled(fn (): bool => DashboardMonthPeriod::isCurrentMonth(
                                DashboardMonthPeriod::fromFilters($this->filters),
                            ))
                            ->action(function (): void {
                                $this->resetDashboardMonth();
                            }),
                    ])
                        ->key('resetMonthActions')
                        ->grow(false)
                        ->fullWidth(false)
                        ->verticalAlignment(VerticalAlignment::End),
                ])->extraAttributes(['class' => 'items-end gap-5']),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Group::make([
                        $this->getFiltersFormContentComponent(),
                    ])->extraAttributes([
                        'class' => 'tido-sticky-marker tido-sticky-marker--top',
                    ]),
                    $this->getWidgetsContentComponent(),
                ])->extraAttributes([
                    'class' => 'tido-sticky-scope',
                ]),
            ]);
    }

    protected function shiftDashboardMonth(int $months): void
    {
        $this->filters = [
            'month' => DashboardMonthPeriod::fromFilters($this->filters)
                ->copy()
                ->addMonths($months)
                ->format('Y-m'),
        ];

        $this->updatedFilters();
    }

    protected function resetDashboardMonth(): void
    {
        $this->filters = [
            'month' => now()->format('Y-m'),
        ];

        $this->updatedFilters();
    }
}
