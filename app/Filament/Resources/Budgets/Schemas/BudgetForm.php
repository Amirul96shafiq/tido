<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets\Schemas;

use App\Enums\LabelType;
use App\Filament\Forms\Components\IconPicker;
use App\Filament\Forms\Components\NotesRichEditor;
use App\Models\Budget;
use App\Models\Label;
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
                        Section::make('Limit & Period')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('amount')
                                            ->myr()
                                            ->required()
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
                                            ->required(fn (Get $get): bool => $get('period') === 'quarterly'),

                                        TextInput::make('year')
                                            ->numeric()
                                            ->default((int) date('Y'))
                                            ->required()
                                            ->helperText('Calendar year this budget belongs to.'),
                                    ]),
                            ]),

                        Section::make('Alert Settings')
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
                            ->schema([
                                NotesRichEditor::make('notes')
                                    ->hiddenLabel()
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Budget Performance')
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->schema([
                                View::make('filament.forms.components.budget-performance')
                                    ->viewData(fn (?Budget $record, Get $get): array => self::performanceViewData($record, $get)),
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
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->placeholder('e.g. Pet Supplies — Monthly')
                                    ->helperText('Auto-fills from the Label when empty. Clear to use the Label name at display time.'),
                            ]),

                        Section::make('Budget Settings')
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

                                Toggle::make('is_active')
                                    ->label('Active budget')
                                    ->default(true)
                                    ->required()
                                    ->helperText('Inactive budgets are hidden from the dashboard and alerts.'),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array{
     *     hasData: bool,
     *     periodLabel: ?string,
     *     status: string,
     *     statusColor: string,
     *     spent: float,
     *     amount: float,
     *     remaining: float,
     *     percentage: float,
     *     rawPercentage: float
     * }
     */
    private static function performanceViewData(?Budget $record, ?Get $get = null): array
    {
        if (! $record instanceof Budget) {
            return [
                'hasData' => false,
                'periodLabel' => null,
                'status' => 'On track',
                'statusColor' => 'emerald',
                'spent' => 0.0,
                'amount' => 0.0,
                'remaining' => 0.0,
                'percentage' => 0.0,
                'rawPercentage' => 0.0,
            ];
        }

        $spent = $record->spentInPeriod();
        $recordAmount = (float) $record->amount;
        $formAmountRaw = $get !== null ? $get('amount') : null;
        $amount = filled($formAmountRaw)
            ? (float) $formAmountRaw
            : $recordAmount;
        $rawPercentage = $amount > 0 ? ($spent / $amount) * 100 : 0.0;
        $remaining = $amount - $spent;

        $alertThreshold = (float) ($get !== null
            ? ($get('alert_threshold') ?? $record->alert_threshold)
            : $record->alert_threshold);
        $criticalThreshold = (float) ($get !== null
            ? ($get('critical_threshold') ?? $record->critical_threshold)
            : $record->critical_threshold);

        $statusColor = match (true) {
            $rawPercentage >= $criticalThreshold => 'red',
            $rawPercentage >= $alertThreshold => 'amber',
            default => 'emerald',
        };

        $status = match ($statusColor) {
            'red' => 'Critical',
            'amber' => 'Warning',
            default => 'On track',
        };

        return [
            'hasData' => true,
            'periodLabel' => $record->getStartDate()->format('d M Y').' – '.$record->getEndDate()->format('d M Y'),
            'status' => $status,
            'statusColor' => $statusColor,
            'spent' => $spent,
            'amount' => $amount,
            'remaining' => $remaining,
            'percentage' => min(100, $rawPercentage),
            'rawPercentage' => $rawPercentage,
        ];
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
}
