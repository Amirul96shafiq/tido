<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recurrings\Schemas;

use App\Enums\HouseholdRole;
use App\Enums\LabelType;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringType;
use App\Filament\Forms\Components\NotesRichEditor;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RecurringForm
{
    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return [
            ['label' => 'Basics', 'id' => 'recurring-basics'],
            ['label' => 'Schedule', 'id' => 'recurring-schedule'],
            ['label' => 'Goal & instalments', 'id' => 'recurring-goal'],
            ['label' => 'Ownership', 'id' => 'recurring-ownership'],
            ['label' => 'Matching', 'id' => 'recurring-matching'],
            ['label' => 'Alerts', 'id' => 'recurring-alerts'],
            ['label' => 'Notes', 'id' => 'recurring-notes'],
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Basics')
                    ->id('recurring-basics')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Cursor Pro'),
                        Select::make('type')
                            ->options(RecurringType::options())
                            ->required()
                            ->live()
                            ->searchable(),
                        Select::make('label_id')
                            ->label('Label')
                            ->relationship(
                                name: 'label',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->where('type', LabelType::Finance)->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        TextInput::make('expected_amount')
                            ->myr()
                            ->placeholder('0.00')
                            ->helperText('Expected amount per occurrence. Leave empty for variable bills.')
                            ->required(fn (Get $get): bool => $get('type') !== RecurringType::VariableBill->value)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::syncInstalmentsFromGoal($get, $set)),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),

                Section::make('Schedule')
                    ->id('recurring-schedule')
                    ->schema([
                        Select::make('cadence_preset')
                            ->label('Cadence')
                            ->options([
                                'monthly' => 'Monthly',
                                'quarterly' => 'Quarterly',
                                'semiannual' => 'Every 6 months',
                                'yearly' => 'Yearly',
                                'custom' => 'Custom (N months)',
                                'once' => 'Once',
                            ])
                            ->default('monthly')
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Select $component, ?Recurring $record): void {
                                $component->state(self::presetFromRecord($record));
                            })
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                self::applyCadencePreset($state, $set);
                            })
                            ->required(),
                        TextInput::make('interval_months')
                            ->label('Every N months')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(24)
                            ->default(1)
                            ->visible(fn (Get $get): bool => $get('cadence_preset') === 'custom')
                            ->required(fn (Get $get): bool => $get('cadence_preset') === 'custom')
                            ->dehydrated(),
                        Select::make('frequency')
                            ->options(RecurringFrequency::options())
                            ->default(RecurringFrequency::Repeating->value)
                            ->dehydrated()
                            ->hidden(),
                        TextInput::make('anchor_day')
                            ->label('Due day of month')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(28)
                            ->placeholder('5')
                            ->helperText('Day 1–28. Short months clamp to the last day.'),
                        DatePicker::make('starts_on')
                            ->label('Starts on')
                            ->native(false)
                            ->default(now()->toDateString()),
                        DatePicker::make('ends_on')
                            ->label('Ends on')
                            ->native(false)
                            ->nullable(),
                        DatePicker::make('next_due_on')
                            ->label('Next due on')
                            ->native(false)
                            ->nullable()
                            ->helperText('Leave empty to derive from start date and due day.'),
                    ]),

                Section::make('Goal & instalments')
                    ->id('recurring-goal')
                    ->schema([
                        TextInput::make('goal_target_amount')
                            ->label('Goal target')
                            ->myr()
                            ->placeholder('0.00')
                            ->helperText('Optional Tabung-style target. Used for progress.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::syncInstalmentsFromGoal($get, $set)),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('instalment_total')
                                    ->label('Instalment total')
                                    ->numeric()
                                    ->minValue(1)
                                    ->nullable(),
                                TextInput::make('instalment_remaining')
                                    ->label('Instalment remaining')
                                    ->numeric()
                                    ->minValue(0)
                                    ->nullable(),
                            ]),
                    ])
                    ->visible(fn (Get $get): bool => in_array($get('type'), [
                        RecurringType::TransferInvestment->value,
                        RecurringType::DebtInstalment->value,
                    ], true)),

                Section::make('Ownership')
                    ->id('recurring-ownership')
                    ->schema([
                        Select::make('family_member_id')
                            ->label('Assigned to')
                            ->options(fn (): array => self::assigneeOptions())
                            ->searchable()
                            ->nullable()
                            ->placeholder(self::primaryUsername())
                            ->helperText('Empty = Primary. Shared overrides personal ownership for visibility.'),
                        Toggle::make('is_shared')
                            ->label('Shared')
                            ->helperText('Visible to the household. Matching can complete from any member expense.')
                            ->default(false),
                    ]),

                Section::make('Matching')
                    ->id('recurring-matching')
                    ->schema([
                        TagsInput::make('merchant_aliases')
                            ->label('Merchant aliases')
                            ->placeholder('Cursor')
                            ->helperText('Matched against expense merchant names (case-insensitive).'),
                    ]),

                Section::make('Alerts')
                    ->id('recurring-alerts')
                    ->schema([
                        Toggle::make('notify_filament')
                            ->label('Filament reminders')
                            ->default(true),
                        Toggle::make('notify_whatsapp')
                            ->label('WhatsApp reminders')
                            ->default(true),
                    ]),

                Section::make('Notes')
                    ->id('recurring-notes')
                    ->schema([
                        NotesRichEditor::make('notes')
                            ->label('Notes')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    /**
     * @return array<int|string, string>
     */
    private static function assigneeOptions(): array
    {
        return FamilyMember::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (FamilyMember $member): array => [
                $member->id => filled($member->display_name)
                    ? (string) $member->display_name
                    : (string) $member->name,
            ])
            ->all();
    }

    private static function primaryUsername(): string
    {
        $primary = User::query()
            ->where('household_role', HouseholdRole::Primary)
            ->orderBy('id')
            ->first();

        if ($primary === null) {
            return 'Primary';
        }

        return filled($primary->display_name)
            ? (string) $primary->display_name
            : (string) $primary->name;
    }

    private static function presetFromRecord(?Recurring $record): string
    {
        if ($record === null) {
            return 'monthly';
        }

        if ($record->frequency === RecurringFrequency::Once) {
            return 'once';
        }

        return match ((int) ($record->interval_months ?? 1)) {
            1 => 'monthly',
            3 => 'quarterly',
            6 => 'semiannual',
            12 => 'yearly',
            default => 'custom',
        };
    }

    private static function applyCadencePreset(?string $preset, Set $set): void
    {
        if ($preset === 'once') {
            $set('frequency', RecurringFrequency::Once->value);
            $set('interval_months', null);

            return;
        }

        $set('frequency', RecurringFrequency::Repeating->value);

        $set('interval_months', match ($preset) {
            'quarterly' => 3,
            'semiannual' => 6,
            'yearly' => 12,
            'custom' => 1,
            default => 1,
        });
    }

    private static function syncInstalmentsFromGoal(Get $get, Set $set): void
    {
        $target = $get('goal_target_amount');
        $expected = $get('expected_amount');

        if ($target === null || $expected === null || $get('instalment_total') !== null) {
            return;
        }

        $targetAmount = (float) str_replace(',', '', (string) $target);
        $expectedAmount = (float) str_replace(',', '', (string) $expected);

        if ($targetAmount <= 0 || $expectedAmount <= 0) {
            return;
        }

        $total = (int) ceil($targetAmount / $expectedAmount);
        $set('instalment_total', $total);

        if ($get('instalment_remaining') === null) {
            $set('instalment_remaining', $total);
        }
    }
}
