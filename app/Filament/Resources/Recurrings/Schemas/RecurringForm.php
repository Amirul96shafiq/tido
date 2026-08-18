<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recurrings\Schemas;

use App\Enums\LabelType;
use App\Enums\RecurringFrequency;
use App\Enums\RecurringType;
use App\Filament\Forms\Components\NotesRichEditor;
use App\Helpers\MoneyDisplay;
use App\Models\FamilyMember;
use App\Models\Recurring;
use App\Support\DueRecurringPreview;
use App\Support\FieldCharacterLimits;
use App\Support\HouseholdAccess;
use App\Support\PhoneNumber;
use App\Support\RecurringFormNormalizer;
use App\Support\RecurringScheduleSummary;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class RecurringForm
{
    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return [
            ['label' => 'Summary', 'id' => 'recurring-summary'],
            ['label' => 'Ownership', 'id' => 'recurring-ownership'],
            ['label' => 'Recurring Payment Due Preview', 'id' => 'recurring-due-preview'],
            ['label' => 'Details', 'id' => 'recurring-details'],
            ['label' => 'Schedule', 'id' => 'recurring-schedule'],
            ['label' => 'Expense matching', 'id' => 'recurring-matching'],
            ['label' => 'Status and Reminders', 'id' => 'recurring-status-and-reminders'],
            ['label' => 'Notes', 'id' => 'recurring-notes'],
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
                    ->extraAttributes(['class' => 'fi-recurring-main-column'])
                    ->schema([
                        Section::make('Recurring Payment Due Preview')
                            ->id('recurring-due-preview')
                            ->extraAttributes(['class' => 'fi-recurring-due-preview-section'])
                            ->schema([
                                View::make('filament.forms.components.due-recurring-preview')
                                    ->viewData(fn (?Recurring $record, Get $get, string $operation): array => DueRecurringPreview::forForm(
                                        $record,
                                        $get,
                                        $operation,
                                    )),
                            ]),

                        Section::make('Recurring Details')
                            ->id('recurring-details')
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->characterLimit(FieldCharacterLimits::RECURRING_TITLE)
                                    ->live(onBlur: true)
                                    ->placeholder('Cursor Pro'),
                                Radio::make('type')
                                    ->options(RecurringType::options())
                                    ->descriptions(RecurringType::descriptions())
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ])
                                    ->required()
                                    ->live()
                                    ->default(RecurringType::Subscription->value),
                                Grid::make(2)
                                    ->schema([
                                        Select::make('label_id')
                                            ->label('Label')
                                            ->relationship(
                                                name: 'label',
                                                titleAttribute: 'name',
                                                modifyQueryUsing: fn ($query) => $query->where('type', LabelType::Finance)->orderBy('name'),
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->live(),
                                        TextInput::make('expected_amount')
                                            ->label(fn (Get $get): string => $get('type') === RecurringType::VariableBill->value
                                                ? 'Typical amount'
                                                : 'Expected amount')
                                            ->myr()
                                            ->placeholder('0.00')
                                            ->helperText(fn (Get $get): ?string => $get('type') === RecurringType::VariableBill->value
                                                ? 'Optional typical amount for matching and reminders.'
                                                : null)
                                            ->required(fn (Get $get): bool => $get('type') !== RecurringType::VariableBill->value
                                                && ! (
                                                    $get('type') === RecurringType::TransferInvestment->value
                                                    && ($get('tracking_mode') ?? 'open_ended') === 'open_ended'
                                                ))
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::syncInstalmentsFromGoal($get, $set)),
                                    ]),
                                ToggleButtons::make('tracking_mode')
                                    ->label('Tracking method')
                                    ->options([
                                        'open_ended' => 'Open-ended',
                                        'target_amount' => 'Target amount',
                                        'fixed_transfers' => 'Fixed number of transfers',
                                    ])
                                    ->default('open_ended')
                                    ->inline()
                                    ->live()
                                    ->visible(fn (Get $get): bool => $get('type') === RecurringType::TransferInvestment->value),
                                Group::make([
                                    TextInput::make('goal_target_amount')
                                        ->label('Goal target')
                                        ->myr()
                                        ->placeholder('0.00')
                                        ->helperText(fn (Get $get): ?string => self::goalInstalmentHelper($get))
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::syncInstalmentsFromGoal($get, $set))
                                        ->visible(fn (Get $get): bool => self::showsTargetAmountFields($get)),
                                    ToggleButtons::make('prior_contribution_mode')
                                        ->label('Already contributed')
                                        ->options([
                                            'none' => 'None',
                                            'count' => 'Number of transfers',
                                            'amount' => 'Amount',
                                        ])
                                        ->default('none')
                                        ->inline()
                                        ->live()
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::syncInstalmentsFromGoal($get, $set))
                                        ->helperText('Exclude transfers already marked paid in tido so progress is not double-counted.')
                                        ->visible(fn (Get $get): bool => self::showsTargetAmountFields($get)),
                                    TextInput::make('prior_transfer_count')
                                        ->label('Transfers already made')
                                        ->numeric()
                                        ->minValue(0)
                                        ->integer()
                                        ->nullable()
                                        ->placeholder('0')
                                        ->helperText(fn (Get $get): ?string => self::priorCountHelper($get))
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::syncInstalmentsFromGoal($get, $set))
                                        ->visible(fn (Get $get): bool => self::showsTargetAmountFields($get)
                                            && ($get('prior_contribution_mode') ?? 'none') === 'count'),
                                    TextInput::make('prior_contributed_amount')
                                        ->label('Already contributed')
                                        ->myr()
                                        ->placeholder('0.00')
                                        ->helperText(fn (Get $get): ?string => self::priorAmountHelper($get))
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::syncInstalmentsFromGoal($get, $set))
                                        ->visible(fn (Get $get): bool => self::showsTargetAmountFields($get)
                                            && ($get('prior_contribution_mode') ?? 'none') === 'amount'),
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('instalment_total')
                                                ->label(fn (Get $get): string => $get('type') === RecurringType::DebtInstalment->value
                                                    ? 'Total payments'
                                                    : 'Total transfers')
                                                ->numeric()
                                                ->minValue(1)
                                                ->nullable()
                                                ->required(fn (Get $get): bool => self::showsInstalmentFields($get))
                                                ->live(onBlur: true)
                                                ->rules([
                                                    fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                                        if (! self::showsInstalmentFields($get)) {
                                                            return;
                                                        }

                                                        $remaining = $get('instalment_remaining');

                                                        if ($value === null || $remaining === null) {
                                                            return;
                                                        }

                                                        if ((int) $remaining > (int) $value) {
                                                            $fail('Payments remaining must not exceed total payments.');
                                                        }
                                                    },
                                                ]),
                                            TextInput::make('instalment_remaining')
                                                ->label(fn (Get $get): string => $get('type') === RecurringType::DebtInstalment->value
                                                    ? 'Payments remaining'
                                                    : 'Transfers remaining')
                                                ->numeric()
                                                ->minValue(0)
                                                ->nullable()
                                                ->required(fn (Get $get): bool => self::showsInstalmentFields($get))
                                                ->live(onBlur: true),
                                        ])
                                        ->visible(fn (Get $get): bool => self::showsInstalmentFields($get)),
                                ])
                                    ->key('recurring-tracking-subfields', isInheritable: false)
                                    ->visible(fn (Get $get): bool => self::showsTrackingSubfields($get))
                                    ->extraAttributes(['class' => 'fi-nested-fields']),
                            ]),

                        Section::make('Recurring Schedule')
                            ->id('recurring-schedule')
                            ->schema([
                                ToggleButtons::make('cadence_preset')
                                    ->label('Cadence')
                                    ->options([
                                        'monthly' => 'Monthly',
                                        'quarterly' => 'Quarterly',
                                        'semiannual' => 'Every 6 months',
                                        'yearly' => 'Yearly',
                                        'custom' => 'Custom',
                                        'once' => 'Once',
                                    ])
                                    ->default('monthly')
                                    ->inline()
                                    ->live()
                                    ->afterStateHydrated(function (ToggleButtons $component, ?Recurring $record, mixed $state): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        $component->state(self::presetFromRecord($record));
                                    })
                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                        self::applyCadencePreset($state, $set);
                                    })
                                    ->required(),
                                Hidden::make('frequency')
                                    ->default(RecurringFrequency::Repeating->value)
                                    ->dehydrated(),
                                Group::make([
                                    DatePicker::make('due_date')
                                        ->label('Due date')
                                        ->native(false)
                                        ->live()
                                        ->required(fn (Get $get): bool => $get('cadence_preset') === 'once'),
                                ])
                                    ->key('recurring-once-due-date', isInheritable: false)
                                    ->visible(fn (Get $get): bool => $get('cadence_preset') === 'once')
                                    ->extraAttributes(['class' => 'fi-nested-fields']),
                                Group::make([
                                    Grid::make(2)
                                        ->schema([
                                            DatePicker::make('starts_on')
                                                ->label('Starts on')
                                                ->native(false)
                                                ->live()
                                                ->default(now()->toDateString())
                                                ->required(fn (Get $get): bool => $get('cadence_preset') !== 'once'),
                                            TextInput::make('anchor_day')
                                                ->label('Due day')
                                                ->numeric()
                                                ->minValue(1)
                                                ->maxValue(28)
                                                ->placeholder('5')
                                                ->live(onBlur: true)
                                                ->helperText('Day 1–28. Short months clamp to the last day.')
                                                ->required(fn (Get $get): bool => $get('cadence_preset') !== 'once'),
                                            ToggleButtons::make('end_rule')
                                                ->label('End rule')
                                                ->options([
                                                    'ongoing' => 'Ongoing',
                                                    'end_on_date' => 'End on date',
                                                ])
                                                ->default('ongoing')
                                                ->inline()
                                                ->live()
                                                ->afterStateHydrated(function (ToggleButtons $component, ?Recurring $record, mixed $state): void {
                                                    if (filled($state)) {
                                                        return;
                                                    }

                                                    $component->state(
                                                        $record?->ends_on !== null ? 'end_on_date' : 'ongoing'
                                                    );
                                                }),
                                            DatePicker::make('ends_on')
                                                ->label('Ends on')
                                                ->native(false)
                                                ->live()
                                                ->nullable()
                                                ->required(fn (Get $get): bool => $get('end_rule') === 'end_on_date')
                                                ->visible(fn (Get $get): bool => $get('end_rule') === 'end_on_date')
                                                ->rules([
                                                    fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                                        if ($get('end_rule') !== 'end_on_date' || blank($value) || blank($get('starts_on'))) {
                                                            return;
                                                        }

                                                        if ((string) $value < (string) $get('starts_on')) {
                                                            $fail('Ends on must be on or after the start date.');
                                                        }
                                                    },
                                                ]),
                                            TextInput::make('interval_months')
                                                ->label('Every N months')
                                                ->numeric()
                                                ->minValue(1)
                                                ->maxValue(24)
                                                ->default(1)
                                                ->live(onBlur: true)
                                                ->visible(fn (Get $get): bool => $get('cadence_preset') === 'custom')
                                                ->required(fn (Get $get): bool => $get('cadence_preset') === 'custom')
                                                ->dehydrated(),
                                        ]),
                                ])
                                    ->key('recurring-repeating-schedule', isInheritable: false)
                                    ->visible(fn (Get $get): bool => $get('cadence_preset') !== 'once')
                                    ->extraAttributes(['class' => 'fi-nested-fields']),
                            ]),

                        Section::make('Expense Matching')
                            ->id('recurring-matching')
                            ->schema([
                                TagsInput::make('merchant_aliases')
                                    ->label('Merchant aliases')
                                    ->placeholder('Cursor')
                                    ->helperText('The recurring title is matched automatically. Add alternative merchant names found on expenses.'),
                            ]),

                        Section::make('Status and Reminders')
                            ->id('recurring-status-and-reminders')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active recurring')
                                    ->default(true)
                                    ->live()
                                    ->helperText('Inactive recurrings are hidden from the due list and do not send reminders.'),
                                Toggle::make('notify_filament')
                                    ->label('In-app reminders')
                                    ->default(true)
                                    ->live()
                                    ->disabled(fn (Get $get): bool => ! ($get('is_active') ?? true))
                                    ->dehydrated()
                                    ->helperText(fn (Get $get): string => ($get('is_active') ?? true)
                                        ? 'Send in-app notifications for this recurring. Profile → Notifications sets lead days and send time.'
                                        : 'Reminders resume when the recurring is active.'),
                                Toggle::make('notify_whatsapp')
                                    ->label('WhatsApp reminders')
                                    ->default(true)
                                    ->live()
                                    ->disabled(fn (Get $get): bool => ! ($get('is_active') ?? true) || self::whatsappUnavailable($get))
                                    ->dehydrated()
                                    ->helperText(function (Get $get): string {
                                        if (! ($get('is_active') ?? true)) {
                                            return 'Reminders resume when the recurring is active.';
                                        }

                                        if (self::whatsappUnavailable($get)) {
                                            return 'No valid WhatsApp recipient is configured for this responsibility.';
                                        }

                                        return 'Send WhatsApp messages for this recurring. Profile → Notifications sets lead days and send time.';
                                    }),
                            ]),

                        Section::make('Recurring Notes')
                            ->id('recurring-notes')
                            ->schema([
                                NotesRichEditor::make('notes')
                                    ->label('Recurring Notes')
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
                    ->extraAttributes(['class' => 'fi-recurring-sidebar-sticky'])
                    ->schema([
                        Section::make('Recurring Summary')
                            ->id('recurring-summary')
                            ->schema([
                                View::make('filament.forms.components.recurring-summary')
                                    ->viewData(fn (?Recurring $record, Get $get, string $operation): array => RecurringScheduleSummary::forForm(
                                        $get,
                                        $record,
                                        $operation,
                                    )),
                            ]),

                        Section::make('Recurring Ownership')
                            ->id('recurring-ownership')
                            ->schema([
                                Radio::make('responsibility')
                                    ->hiddenLabel()
                                    ->options([
                                        'primary' => 'Primary',
                                        'family_member' => 'Family member',
                                        'household_shared' => 'Household shared',
                                    ])
                                    ->descriptions([
                                        'primary' => 'Owned by the Primary account.',
                                        'family_member' => 'Assigned to one household member.',
                                        'household_shared' => 'Visible to the household. Matching can complete from any member expense.',
                                    ])
                                    ->default('primary')
                                    ->live()
                                    ->afterStateHydrated(function (Radio $component, ?Recurring $record, mixed $state): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        if ($record === null) {
                                            $component->state('primary');

                                            return;
                                        }

                                        $component->state(match (true) {
                                            $record->is_shared => 'household_shared',
                                            $record->family_member_id !== null => 'family_member',
                                            default => 'primary',
                                        });
                                    })
                                    ->disabled(fn (): bool => HouseholdAccess::isFamilyMember())
                                    ->dehydrated(fn (): bool => ! HouseholdAccess::isFamilyMember())
                                    ->required(),
                                Group::make([
                                    Select::make('family_member_id')
                                        ->label('Family member')
                                        ->options(fn (): array => self::assigneeOptions())
                                        ->searchable()
                                        ->live()
                                        ->nullable()
                                        ->required(fn (Get $get): bool => $get('responsibility') === 'family_member')
                                        ->disabled(fn (): bool => HouseholdAccess::isFamilyMember())
                                        ->dehydrated(fn (): bool => ! HouseholdAccess::isFamilyMember()),
                                ])
                                    ->key('recurring-family-member-assignee', isInheritable: false)
                                    ->visible(fn (Get $get): bool => $get('responsibility') === 'family_member')
                                    ->extraAttributes(['class' => 'fi-nested-fields']),
                                Hidden::make('is_shared')
                                    ->default(false)
                                    ->dehydrated(),
                            ]),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function hydrateFormData(array $data, ?Recurring $record = null): array
    {
        return RecurringFormNormalizer::hydrateVirtualFields($data, $record);
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
            $set('end_rule', 'ongoing');
            $set('ends_on', null);

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
        if (! self::showsTargetAmountFields($get)) {
            return;
        }

        $targetAmount = MoneyDisplay::parse($get('goal_target_amount'));
        $expectedAmount = MoneyDisplay::parse($get('expected_amount'));

        if ($targetAmount === null || $expectedAmount === null || $targetAmount <= 0 || $expectedAmount <= 0) {
            return;
        }

        $total = (int) ceil($targetAmount / $expectedAmount);
        $set('instalment_total', $total);

        $prior = self::resolvedPriorAmount($get);
        $priorSlots = (int) floor($prior / $expectedAmount);
        $set('instalment_remaining', max(0, $total - $priorSlots));
    }

    private static function goalInstalmentHelper(Get $get): ?string
    {
        $targetAmount = MoneyDisplay::parse($get('goal_target_amount'));
        $expectedAmount = MoneyDisplay::parse($get('expected_amount'));

        if ($targetAmount === null || $expectedAmount === null || $targetAmount <= 0 || $expectedAmount <= 0) {
            return 'Optional Tabung-style target. Instalment count is calculated from expected amount.';
        }

        $prior = self::resolvedPriorAmount($get);
        $remainingAmount = max(0, $targetAmount - $prior);
        $remainingTransfers = (int) ceil($remainingAmount / $expectedAmount);

        return 'About '.$remainingTransfers.' transfers left at '
            .MoneyDisplay::withPrefix($expectedAmount)
            .' ('.MoneyDisplay::withPrefix($remainingAmount)
            .' of '.MoneyDisplay::withPrefix($targetAmount).' remaining).';
    }

    private static function priorCountHelper(Get $get): ?string
    {
        $count = max(0, (int) ($get('prior_transfer_count') ?? 0));
        $expectedAmount = MoneyDisplay::parse($get('expected_amount'));

        if ($count <= 0 || $expectedAmount === null || $expectedAmount <= 0) {
            return 'Converted to RM using the expected amount. Do not include transfers already paid in tido.';
        }

        return MoneyDisplay::withPrefix(round($count * $expectedAmount, 2))
            .' at the expected amount. Do not include transfers already paid in tido.';
    }

    private static function priorAmountHelper(Get $get): ?string
    {
        $prior = MoneyDisplay::parse($get('prior_contributed_amount'));
        $expectedAmount = MoneyDisplay::parse($get('expected_amount'));

        if ($prior === null || $prior <= 0 || $expectedAmount === null || $expectedAmount <= 0) {
            return 'Money already saved outside tido. Do not include transfers already paid in tido.';
        }

        $slots = (int) floor($prior / $expectedAmount);

        return 'About '.$slots.' transfers at the expected amount. Do not include transfers already paid in tido.';
    }

    private static function resolvedPriorAmount(Get $get): float
    {
        $mode = (string) ($get('prior_contribution_mode') ?? 'none');

        if ($mode === 'count') {
            $count = max(0, (int) ($get('prior_transfer_count') ?? 0));
            $expectedAmount = MoneyDisplay::parse($get('expected_amount')) ?? 0.0;

            return $count > 0 && $expectedAmount > 0
                ? round($count * $expectedAmount, 2)
                : 0.0;
        }

        if ($mode === 'amount') {
            return max(0.0, MoneyDisplay::parse($get('prior_contributed_amount')) ?? 0.0);
        }

        return 0.0;
    }

    private static function showsTrackingSubfields(Get $get): bool
    {
        return self::showsTargetAmountFields($get)
            || self::showsInstalmentFields($get);
    }

    private static function showsTargetAmountFields(Get $get): bool
    {
        return $get('type') === RecurringType::TransferInvestment->value
            && ($get('tracking_mode') ?? 'open_ended') === 'target_amount';
    }

    private static function showsInstalmentFields(Get $get): bool
    {
        $type = $get('type');

        if ($type === RecurringType::DebtInstalment->value) {
            return true;
        }

        return $type === RecurringType::TransferInvestment->value
            && ($get('tracking_mode') ?? 'open_ended') === 'fixed_transfers';
    }

    private static function whatsappUnavailable(Get $get): bool
    {
        $primary = PhoneNumber::primaryWhatsAppNumber();
        $responsibility = (string) ($get('responsibility') ?? 'primary');

        if ($responsibility === 'family_member') {
            $memberPhone = null;
            $memberId = $get('family_member_id');

            if (filled($memberId)) {
                $member = FamilyMember::query()->find($memberId);
                $memberPhone = $member instanceof FamilyMember
                    ? PhoneNumber::normalize(is_string($member->phone) ? $member->phone : null)
                    : null;
            }

            return $primary === null && $memberPhone === null;
        }

        return $primary === null;
    }
}
