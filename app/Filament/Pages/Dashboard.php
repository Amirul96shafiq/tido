<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasDashboardGreeting;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Support\DashboardMonthPeriod;
use App\Filament\Widgets\MonthlySpendingOverview;
use App\Support\DashboardSpenderScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\VerticalAlignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

class Dashboard extends BaseDashboard
{
    use HasDashboardGreeting;
    use HasFiltersForm;
    use PrependsHomeBreadcrumb;

    protected static bool $shouldRegisterNavigation = false;

    public const VIEW_FINANCES = 'finances';

    public const VIEW_TRAINING = 'training';

    public const VIEW_HEALTH = 'health';

    public const VIEW_TASK = 'task';

    /**
     * @var list<string>
     */
    public const VIEWS = [
        self::VIEW_FINANCES,
        self::VIEW_TRAINING,
        self::VIEW_HEALTH,
        self::VIEW_TASK,
    ];

    /**
     * @return list<array{view: string, label: string, icon: string}>
     */
    public static function viewTabs(): array
    {
        return [
            [
                'view' => self::VIEW_FINANCES,
                'label' => 'Finance',
                'icon' => 'heroicon-m-calculator',
            ],
            [
                'view' => self::VIEW_TRAINING,
                'label' => 'Training',
                'icon' => 'heroicon-m-bolt',
            ],
            [
                'view' => self::VIEW_HEALTH,
                'label' => 'Health',
                'icon' => 'heroicon-m-heart',
            ],
            [
                'view' => self::VIEW_TASK,
                'label' => 'Task',
                'icon' => 'heroicon-m-rectangle-stack',
            ],
        ];
    }

    #[Url(as: 'view', except: 'finances', history: true)]
    public string $dashboardView = self::VIEW_FINANCES;

    /**
     * @return list<array{label: string, id: string}>
     */
    public function widgetNavItems(): array
    {
        $forecastLabel = DashboardMonthPeriod::isCurrentMonth(
            DashboardMonthPeriod::fromFilters($this->filters),
        ) ? 'Spending Forecast' : 'Daily Average';

        return [
            ['label' => 'Total Spent', 'id' => MonthlySpendingOverview::SECTION_TOTAL_SPENT],
            ['label' => $forecastLabel, 'id' => MonthlySpendingOverview::SECTION_SPENDING_FORECAST],
            ['label' => 'SST Tax Paid', 'id' => MonthlySpendingOverview::SECTION_SST_TAX_PAID],
            ['label' => 'Receipts Processed', 'id' => MonthlySpendingOverview::SECTION_RECEIPTS_PROCESSED],
            ['label' => 'Monthly Spending Trend', 'id' => 'monthly-trend'],
            ['label' => 'Spending by Label', 'id' => 'spending-by-label'],
            ['label' => 'Budget Performance', 'id' => 'budget-status'],
            ['label' => 'Top Merchants', 'id' => 'top-merchants'],
            ['label' => 'Spending by Payment Method', 'id' => 'spending-by-payment-method'],
            ['label' => 'Receipts by Upload Source', 'id' => 'receipts-by-source'],
            ['label' => 'Recent Receipts', 'id' => 'recent-receipts'],
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
        if (! in_array($this->dashboardView, self::VIEWS, true)) {
            $this->dashboardView = self::VIEW_FINANCES;
        }

        if (! isset($this->filters['month'])) {
            $this->filters = [
                'month' => DashboardMonthPeriod::fromFilters($this->filters)->format('Y-m'),
                'spender' => DashboardSpenderScope::defaultFor()->value(),
            ];
        }

        if (! isset($this->filters['spender'])) {
            $this->filters['spender'] = DashboardSpenderScope::defaultFor()->value();
        }

        $allowedSpenders = array_keys(DashboardSpenderScope::filterOptionsFor());

        if (! in_array($this->filters['spender'], $allowedSpenders, true)) {
            $this->filters['spender'] = DashboardSpenderScope::defaultFor()->value();
        }
    }

    public function getDashboardView(): string
    {
        return $this->dashboardView;
    }

    public function getTitle(): string|Htmlable
    {
        $label = 'Finance';

        foreach (self::viewTabs() as $tab) {
            if ($tab['view'] === $this->dashboardView) {
                $label = $tab['label'];
                break;
            }
        }

        return new HtmlString(
            'Dashboard - <span class="text-primary-600 dark:text-primary-400">'.e($label).'</span>'
        );
    }

    public function setDashboardView(string $view): void
    {
        if (! in_array($view, self::VIEWS, true)) {
            return;
        }

        $this->dashboardView = $view;
        $this->cacheSchema('content', null);
    }

    public function updatedFiltersMonth(): void
    {
        $this->updatedFilters();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
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
                    Select::make('spender')
                        ->label('Spender')
                        ->options(fn (): array => DashboardSpenderScope::filterOptionsFor())
                        ->native(false)
                        ->required()
                        ->selectablePlaceholder(false)
                        ->grow(false)
                        ->extraFieldWrapperAttributes([
                            'class' => 'fi-dashboard-spender-filter',
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
        $comingSoon = $this->comingSoonDashboardContent();

        if ($comingSoon !== null) {
            return $schema
                ->components([
                    View::make('filament.pages.partials.coming-soon-dashboard-content')
                        ->viewData($comingSoon),
                ]);
        }

        return $schema
            ->components([
                Group::make([
                    Group::make([
                        Flex::make([
                            Group::make([
                                $this->getFiltersFormContentComponent(),
                            ])->extraAttributes([
                                'class' => 'tido-dashboard-sticky-toolbar-filters',
                            ]),
                            Group::make([
                                View::make('filament.schemas.components.section-nav')
                                    ->viewData(fn (): array => [
                                        'sections' => $this->widgetNavItems(),
                                        'ariaLabel' => 'Dashboard widgets',
                                    ]),
                            ])->extraAttributes([
                                'class' => 'tido-dashboard-sticky-toolbar-nav',
                            ]),
                        ])->extraAttributes([
                            'class' => 'tido-dashboard-sticky-toolbar',
                        ]),
                    ])->extraAttributes([
                        'class' => 'tido-sticky-marker tido-sticky-marker--top',
                    ]),
                    $this->getWidgetsContentComponent(),
                ])->extraAttributes([
                    'class' => 'tido-sticky-scope',
                ]),
            ]);
    }

    /**
     * @return array{id: string, heading: string, icon: string, description: string}|null
     */
    protected function comingSoonDashboardContent(): ?array
    {
        return match ($this->dashboardView) {
            self::VIEW_TRAINING => [
                'id' => 'training-overview',
                'heading' => 'Training',
                'icon' => 'heroicon-o-bolt',
                'description' => 'Training dashboard is not available yet. Check back later for workouts, progress, and insights.',
            ],
            self::VIEW_HEALTH => [
                'id' => 'health-overview',
                'heading' => 'Health',
                'icon' => 'heroicon-o-heart',
                'description' => 'Health dashboard is not available yet. Check back later for vitals, habits, and insights.',
            ],
            self::VIEW_TASK => [
                'id' => 'task-overview',
                'heading' => 'Task',
                'icon' => 'heroicon-o-rectangle-stack',
                'description' => 'Task dashboard is not available yet. Check back later for to-dos, priorities, and progress.',
            ],
            default => null,
        };
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
