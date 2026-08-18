<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets\Schemas;

use App\Enums\HouseholdRole;
use App\Enums\LabelType;
use App\Filament\Forms\Components\IconPicker;
use App\Filament\Forms\Components\NotesRichEditor;
use App\Models\Budget;
use App\Models\FamilyMember;
use App\Models\Label;
use App\Models\User;
use App\Support\FieldCharacterLimits;
use App\Support\HouseholdAccess;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class BudgetForm
{
    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return [
            ['label' => 'Budget Appearance', 'id' => 'budget-appearance'],
            ['label' => 'Budget Performance Preview', 'id' => 'budget-performance'],
            ['label' => 'Limit & Period', 'id' => 'limit-period'],
            ['label' => 'Budget Settings', 'id' => 'budget-settings'],
            ['label' => 'Alert Settings', 'id' => 'alert-settings'],
            ['label' => 'Budget Notes', 'id' => 'budget-notes'],
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(2)
                    ->columnOrder([
                        'default' => 2,
                        'lg' => 1,
                    ])
                    ->extraAttributes(['class' => 'fi-budget-main-column'])
                    ->schema([
                        Section::make('Budget Performance Preview')
                            ->id('budget-performance')
                            ->extraAttributes(['class' => 'fi-budget-performance-section'])
                            ->schema([
                                View::make('filament.forms.components.budget-performance')
                                    ->viewData(fn (?Budget $record, Get $get): array => self::performanceViewData($record, $get)),
                            ]),

                        Section::make('Limit & Period')
                            ->id('limit-period')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('amount')
                                            ->myr()
                                            ->required()
                                            ->placeholder('0.00')
                                            ->live(onBlur: true)
                                            ->helperText('Maximum spend allowed in MYR for this period.'),

                                        Select::make('period')
                                            ->options([
                                                'daily' => 'Daily',
                                                'weekly' => 'Weekly',
                                                'monthly' => 'Monthly',
                                                'quarterly' => 'Quarterly',
                                                'yearly' => 'Yearly',
                                            ])
                                            ->default('monthly')
                                            ->live()
                                            ->searchable()
                                            ->required()
                                            ->helperText('Daily/weekly/monthly use the current window within the selected year.'),

                                        Select::make('quarter')
                                            ->options([
                                                1 => 'Q1 (Jan - Mar)',
                                                2 => 'Q2 (Apr - Jun)',
                                                3 => 'Q3 (Jul - Sep)',
                                                4 => 'Q4 (Oct - Dec)',
                                            ])
                                            ->visible(fn (Get $get): bool => $get('period') === 'quarterly')
                                            ->required(fn (Get $get): bool => $get('period') === 'quarterly')
                                            ->live(),

                                        TextInput::make('year')
                                            ->numeric()
                                            ->default((int) date('Y'))
                                            ->required()
                                            ->live(onBlur: true)
                                            ->helperText('Calendar year this budget belongs to.'),
                                    ]),
                            ]),

                        Section::make('Budget Settings')
                            ->id('budget-settings')
                            ->schema([
                                Select::make('label_id')
                                    ->label('Label')
                                    ->relationship(
                                        name: 'label',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn ($query) => $query->where('type', LabelType::Finance)->orderBy('name'),
                                    )
                                    ->placeholder('Overall (All Labels)')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                        self::syncAppearanceFromLabel($state, $get, $set);
                                    })
                                    ->helperText('Leave empty for an overall spending cap across all labels. Selecting a Label fills empty Title and Icon.'),

                                Select::make('family_member_id')
                                    ->label('Assigned to')
                                    ->options(fn (): array => FamilyMember::query()
                                        ->orderBy('name')
                                        ->get(['id', 'name', 'display_name'])
                                        ->mapWithKeys(fn (FamilyMember $familyMember): array => [
                                            $familyMember->getKey() => filled($familyMember->display_name)
                                                ? (string) $familyMember->display_name
                                                : (string) $familyMember->name,
                                        ])
                                        ->all())
                                    ->placeholder(fn (): string => self::primaryUsername())
                                    ->searchable()
                                    ->live()
                                    ->nullable()
                                    ->disabled(fn (): bool => HouseholdAccess::isFamilyMember())
                                    ->dehydrated(fn (): bool => ! HouseholdAccess::isFamilyMember())
                                    ->helperText('Who this budget belongs to. Leave empty for the Primary user.'),

                                Toggle::make('is_shared')
                                    ->label('Shared with household')
                                    ->default(false)
                                    ->live()
                                    ->disabled(fn (): bool => HouseholdAccess::isFamilyMember())
                                    ->dehydrated(fn (): bool => ! HouseholdAccess::isFamilyMember())
                                    ->helperText('When shared, everyone’s spending counts toward this budget. When personal, only the assignee’s spending counts.'),

                                Toggle::make('is_active')
                                    ->label('Active budget')
                                    ->default(true)
                                    ->required()
                                    ->helperText('Inactive budgets are hidden from the dashboard and alerts.'),
                            ]),

                        Section::make('Alert Settings')
                            ->id('alert-settings')
                            ->schema([
                                Slider::make('alert_threshold')
                                    ->label('Warn Threshold (%)')
                                    ->range(minValue: 10, maxValue: 100)
                                    ->step(5)
                                    ->decimalPlaces(0)
                                    ->default(80)
                                    ->tooltips(RawJs::make(<<<'JS'
                                                `${Math.round($value)}%`
                                                JS))
                                    ->live()
                                    ->required(),

                                Slider::make('critical_threshold')
                                    ->label('Critical Threshold (%)')
                                    ->range(minValue: 10, maxValue: 100)
                                    ->step(5)
                                    ->decimalPlaces(0)
                                    ->default(100)
                                    ->tooltips(RawJs::make(<<<'JS'
                                                `${Math.round($value)}%`
                                                JS))
                                    ->live()
                                    ->required()
                                    ->rules([
                                        fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                            $warn = (int) round((float) ($get('alert_threshold') ?? 0));
                                            $critical = (int) round((float) $value);

                                            if ($critical < $warn) {
                                                $fail('Critical threshold must be greater than or equal to the warn threshold.');
                                            }
                                        },
                                    ]),

                                Toggle::make('notify_filament')
                                    ->label('Notify in tido App')
                                    ->default(true)
                                    ->helperText('Send in-app database notifications when a threshold is reached.'),

                                Toggle::make('notify_whatsapp')
                                    ->label('Notify via WhatsApp')
                                    ->default(true)
                                    ->helperText('Send WhatsApp messages when a threshold is reached.'),
                            ]),

                        Section::make('Budget Notes')
                            ->id('budget-notes')
                            ->schema([
                                NotesRichEditor::make('notes')
                                    ->label('Budget Notes')
                                    ->hiddenLabel()
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Grid::make(1)
                    ->columnSpan(1)
                    ->columnOrder([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->extraAttributes(['class' => 'fi-budget-sidebar-sticky'])
                    ->schema([
                        Section::make('Budget Appearance')
                            ->id('budget-appearance')
                            ->schema([
                                View::make('filament.forms.components.label-icon-preview')
                                    ->viewData(fn (Get $get): array => [
                                        'icon' => filled($get('icon'))
                                            ? (string) $get('icon')
                                            : (self::previewLabelIcon($get('label_id')) ?? 'heroicon-o-banknotes'),
                                        'color' => self::previewColor($get('label_id')),
                                        'name' => filled($get('title'))
                                            ? (string) $get('title')
                                            : (self::previewLabelName($get('label_id')) ?? 'Budget preview'),
                                    ]),

                                IconPicker::make('icon')
                                    ->label('Icon')
                                    ->live()
                                    ->helperText('Auto-fills from the Label when empty. Clear to use the Label icon at display time.'),

                                TextInput::make('title')
                                    ->label('Title')
                                    ->characterLimit(
                                        FieldCharacterLimits::BUDGET_TITLE,
                                        'Auto-fills from the Label when empty. Clear to use the Label name at display time.',
                                    )
                                    ->live(onBlur: true)
                                    ->placeholder('e.g. Pet Supplies — Monthly'),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array{
     *     hasData: bool,
     *     emptyHeading?: string,
     *     emptyDescription?: string,
     *     budget?: array{
     *         name: string,
     *         icon: string,
     *         color: string,
     *         amount: float,
     *         spent: float,
     *         percentage: float,
     *         raw_percentage: float,
     *         period: string,
     *         is_shared: bool,
     *         status_color: string
     *     }
     * }
     */
    private static function performanceViewData(?Budget $record, ?Get $get = null): array
    {
        $formAmountRaw = $get !== null ? $get('amount') : null;
        $amount = filled($formAmountRaw)
            ? (float) $formAmountRaw
            : (float) ($record?->amount ?? 0);

        if ($amount <= 0) {
            return [
                'hasData' => false,
                'emptyHeading' => 'Set a budget limit to preview',
                'emptyDescription' => 'Enter an amount under Limit & Period to see how current spending would track against this budget.',
            ];
        }

        $preview = self::resolvePerformancePreviewBudget($record, $get);
        $spent = Budget::spentForPreview($preview);
        $rawPercentage = ($spent / $amount) * 100;

        $alertThreshold = (float) ($get !== null
            ? ($get('alert_threshold') ?? $preview->alert_threshold)
            : $preview->alert_threshold);
        $criticalThreshold = (float) ($get !== null
            ? ($get('critical_threshold') ?? $preview->critical_threshold)
            : $preview->critical_threshold);

        $statusColor = match (true) {
            $rawPercentage >= $criticalThreshold => 'red',
            $rawPercentage >= $alertThreshold => 'amber',
            default => 'emerald',
        };

        return [
            'hasData' => true,
            'budget' => [
                'name' => self::previewDisplayTitle($record, $get),
                'icon' => self::previewDisplayIcon($record, $get),
                'color' => self::previewColor($preview->label_id),
                'amount' => $amount,
                'spent' => $spent,
                'percentage' => min(100, $rawPercentage),
                'raw_percentage' => $rawPercentage,
                'period' => ucfirst((string) $preview->period),
                'is_shared' => (bool) $preview->is_shared,
                'status_color' => $statusColor,
            ],
        ];
    }

    private static function resolvePerformancePreviewBudget(?Budget $record, ?Get $get): Budget
    {
        $preview = $record instanceof Budget ? $record->replicate() : new Budget;

        if ($get === null) {
            $preview->loadMissing('label');

            return $preview;
        }

        $amount = $get('amount');
        if (filled($amount)) {
            $preview->amount = $amount;
        }

        $labelId = $get('label_id');
        $preview->label_id = filled($labelId) ? (int) $labelId : null;

        $familyMemberId = $get('family_member_id');
        $preview->family_member_id = filled($familyMemberId) ? (int) $familyMemberId : null;

        $isShared = $get('is_shared');
        if ($isShared !== null) {
            $preview->is_shared = (bool) $isShared;
        }

        $period = $get('period');
        if (filled($period)) {
            $preview->period = (string) $period;
        }

        $year = $get('year');
        if (filled($year)) {
            $preview->year = (int) $year;
        }

        $quarter = $get('quarter');
        if (filled($quarter)) {
            $preview->quarter = (int) $quarter;
        }

        $alertThreshold = $get('alert_threshold');
        if (filled($alertThreshold)) {
            $preview->alert_threshold = (int) round((float) $alertThreshold);
        }

        $criticalThreshold = $get('critical_threshold');
        if (filled($criticalThreshold)) {
            $preview->critical_threshold = (int) round((float) $criticalThreshold);
        }

        $preview->loadMissing('label');

        return $preview;
    }

    private static function previewDisplayTitle(?Budget $record, ?Get $get): string
    {
        $title = $get !== null ? $get('title') : $record?->title;

        if (filled($title)) {
            return (string) $title;
        }

        $labelId = $get !== null ? ($get('label_id') ?? $record?->label_id) : $record?->label_id;

        return self::previewLabelName($labelId) ?? 'Overall Budget';
    }

    private static function previewDisplayIcon(?Budget $record, ?Get $get): string
    {
        $icon = $get !== null ? $get('icon') : $record?->icon;

        if (filled($icon)) {
            return (string) $icon;
        }

        $labelId = $get !== null ? ($get('label_id') ?? $record?->label_id) : $record?->label_id;

        return self::previewLabelIcon($labelId) ?? 'heroicon-o-banknotes';
    }

    private static function syncAppearanceFromLabel(mixed $labelId, Get $get, Set $set): void
    {
        if (blank($labelId)) {
            return;
        }

        $label = Label::query()->find($labelId);

        if (! $label instanceof Label) {
            return;
        }

        if (blank($get('title'))) {
            $set('title', (string) $label->name);
        }

        if (blank($get('icon')) && filled($label->icon)) {
            $set('icon', (string) $label->icon);
        }
    }

    private static function previewColor(mixed $labelId): string
    {
        if (blank($labelId)) {
            return '#FFD07D';
        }

        $color = Label::query()->whereKey($labelId)->value('color');

        return filled($color) ? (string) $color : '#FFD07D';
    }

    private static function previewLabelName(mixed $labelId): ?string
    {
        if (blank($labelId)) {
            return null;
        }

        $name = Label::query()->whereKey($labelId)->value('name');

        return filled($name) ? (string) $name : null;
    }

    private static function previewLabelIcon(mixed $labelId): ?string
    {
        if (blank($labelId)) {
            return null;
        }

        $icon = Label::query()->whereKey($labelId)->value('icon');

        return filled($icon) ? (string) $icon : null;
    }

    protected static function primaryUsername(): string
    {
        $primaryUser = User::query()
            ->where(function ($query): void {
                $query
                    ->where('household_role', HouseholdRole::Primary->value)
                    ->orWhereNull('household_role');
            })
            ->orderBy('id')
            ->first(['name', 'display_name']);

        if (! $primaryUser instanceof User) {
            return 'Primary';
        }

        return filled($primaryUser->display_name)
            ? (string) $primaryUser->display_name
            : (string) $primaryUser->name;
    }
}
