<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Enums\UserDateFormat;
use App\Enums\UserLocale;
use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Forms\Components\DateOfBirthPicker;
use App\Models\Backup;
use App\Models\User;
use App\Notifications\VerifyEmailChange;
use App\Services\AccountDangerZoneService;
use App\Services\ActiveSessionService;
use App\Services\BackupNotificationService;
use App\Services\BackupService;
use App\Services\FamilyMemberLoginService;
use App\Services\RecurringReminderService;
use App\Support\ClipboardCopy;
use App\Support\EmailChangeVerification;
use App\Support\FieldCharacterLimits;
use App\Support\FilamentAuthLogout;
use App\Support\HouseholdAccess;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Auth\Notifications\NoticeOfEmailChangeRequest;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use League\Uri\Components\Query;
use LogicException;

class EditProfile extends BaseEditProfile implements HasTable
{
    use HasStickyBlurFormActions;
    use InteractsWithTable;
    use PrependsHomeBreadcrumb;
    use RecoversContentDraft;

    private const RESET_CONFIRMATION_PHRASE = 'CONFIRM RESET DATA';

    private const DELETE_CONFIRMATION_PHRASE = 'CONFIRM DELETE ACCOUNT';

    public ?string $pendingRestoreToken = null;

    public ?int $pendingDeleteBackupId = null;

    public function mount(): void
    {
        parent::mount();

        app(ActiveSessionService::class)->stampCreatedAt(session()->getId());
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            'fi-profile-page',
        ];
    }

    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        $items = [
            ['label' => 'Personalize & Appearance', 'id' => 'personalize-appearance'],
            ['label' => 'Account & Security', 'id' => 'account-security'],
            ['label' => 'Active Sessions', 'id' => 'active-sessions'],
            ['label' => 'Regional Preferences', 'id' => 'regional-preferences'],
            ['label' => 'Notifications', 'id' => 'notifications'],
            ['label' => 'Danger Zone', 'id' => 'danger-zone'],
        ];

        if (! HouseholdAccess::isPrimary()) {
            return array_values(array_filter(
                $items,
                fn (array $item): bool => ! in_array($item['id'], ['account-security', 'danger-zone'], true),
            ));
        }

        return $items;
    }

    public function sectionNavAriaLabel(): string
    {
        return 'Profile sections';
    }

    protected function contentDraftKey(): string
    {
        return 'profile-edit';
    }

    /**
     * @return list<string>
     */
    protected function contentDraftExcludedFields(): array
    {
        return [
            'image_path',
            'avatar_url',
            'password',
            'passwordConfirmation',
            'currentPassword',
            'change_password',
            'enable_reset_data',
            'reset_confirmation_phrase',
            'reset_confirmation_password',
            'enable_delete_account',
            'delete_confirmation_phrase',
            'delete_confirmation_password',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function timezoneOptions(): array
    {
        return [
            'Asia/Kuala_Lumpur' => 'Malaysia (Kuala Lumpur)',
            'Asia/Singapore' => 'Singapore',
            'Asia/Jakarta' => 'Indonesia (Jakarta)',
            'Asia/Bangkok' => 'Thailand (Bangkok)',
            'Asia/Manila' => 'Philippines (Manila)',
            'Asia/Hong_Kong' => 'Hong Kong',
            'Asia/Tokyo' => 'Japan (Tokyo)',
            'Asia/Shanghai' => 'China (Shanghai)',
            'Australia/Sydney' => 'Australia (Sydney)',
            'Europe/London' => 'United Kingdom (London)',
            'America/New_York' => 'United States (New York)',
            'UTC' => 'UTC',
        ];
    }

    protected function getAvatarFormComponent(): Component
    {
        return FileUpload::make('avatar_url')
            ->hiddenLabel()
            ->fieldWrapperView('filament-forms::plain-field-wrapper')
            ->extraFieldWrapperAttributes(['class' => 'fi-profile-photo-field'])
            ->avatar()
            ->disk('public')
            ->directory('avatars')
            ->image()
            ->imageEditor()
            ->maxSize(2048)
            ->circleCropper();
    }

    protected function getNameFormComponent(): Component
    {
        $component = parent::getNameFormComponent()
            ->label('Full Name')
            ->placeholder('Full name')
            ->autofocus(false);

        if ($component instanceof TextInput) {
            return FieldCharacterLimits::applyTextInput($component, FieldCharacterLimits::USER_NAME);
        }

        return $component;
    }

    public function form(Schema $schema): Schema
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
                    ->extraAttributes(['class' => 'fi-profile-main-column'])
                    ->schema([
                        Section::make('Personalize & Appearance')
                            ->id('personalize-appearance')
                            ->schema([
                                Fieldset::make('APPEARANCE')
                                    ->schema([
                                        View::make('filament.schemas.components.theme-mode-field')
                                            ->columnSpanFull(),

                                        View::make('filament.schemas.components.sidebar-mode-field')
                                            ->columnSpanFull(),

                                        Hidden::make('stylized_background_enabled'),

                                        View::make('filament.schemas.components.stylized-background-field')
                                            ->viewData(fn (Get $get): array => [
                                                'enabled' => (bool) $get('stylized_background_enabled'),
                                            ])
                                            ->columnSpanFull(),
                                    ]),

                                Fieldset::make('PREFERENCES')
                                    ->key('personalize-preferences', isInheritable: false)
                                    ->columns(1)
                                    ->schema([
                                        Toggle::make('reduce_motion')
                                            ->label('Reduce Motion')
                                            ->helperText('Disable count-up, marquee, and other decorative animation. Save to keep this preference for future sign-ins.')
                                            ->live()
                                            ->columnSpanFull()
                                            ->fieldWrapperView('profile-toggle-field-wrapper')
                                            ->extraFieldWrapperAttributes(['class' => 'fi-profile-toggle-field'])
                                            ->afterStateUpdated(function (bool $state): void {
                                                $this->js('window.tidoSetReduceMotion('.Js::from($state).')');
                                            }),

                                        Toggle::make('mobile_nav_enabled')
                                            ->label('Mobile Nav')
                                            ->helperText('On small screens, replace the top bar with a bottom navigation bar. Save to keep this preference for future sign-ins.')
                                            ->live()
                                            ->columnSpanFull()
                                            ->fieldWrapperView('profile-toggle-field-wrapper')
                                            ->extraFieldWrapperAttributes(['class' => 'fi-profile-toggle-field'])
                                            ->afterStateUpdated(function (bool $state): void {
                                                $this->js('window.tidoSetMobileNav('.Js::from($state).')');
                                            }),
                                    ]),
                            ]),

                        Section::make('Account & Security')
                            ->id('account-security')
                            ->visible(fn (): bool => HouseholdAccess::isPrimary())
                            ->schema([
                                $this->getEmailFormComponent(),
                                Toggle::make('change_password')
                                    ->label('Change Password')
                                    ->live()
                                    ->dehydrated(false),
                                Group::make([
                                    $this->getGenerateStrongPasswordActionComponent(),
                                    $this->getPasswordFormComponent(),
                                    $this->getPasswordConfirmationFormComponent(),
                                    $this->getCurrentPasswordFormComponent(),
                                ])
                                    ->key('change-password-fields', isInheritable: false)
                                    ->visible(fn (Get $get): bool => (bool) $get('change_password')
                                        || ($get('email') !== $this->getUser()->getAttributeValue('email')))
                                    ->extraAttributes(['class' => 'fi-nested-fields']),
                            ]),

                        Section::make('Active Sessions')
                            ->id('active-sessions')
                            ->schema([
                                EmbeddedTable::make()
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Regional Preferences')
                            ->id('regional-preferences')
                            ->schema([
                                Select::make('locale')
                                    ->label('Language')
                                    ->options(UserLocale::options())
                                    ->disableOptionWhen(fn (string $value): bool => $value !== UserLocale::En->value)
                                    ->helperText('Coming soon — only English is available for now.')
                                    ->searchable()
                                    ->required()
                                    ->rule(Rule::in([UserLocale::En->value])),

                                Select::make('timezone')
                                    ->label('Timezone')
                                    ->options(static::timezoneOptions())
                                    ->searchable()
                                    ->required(),

                                Radio::make('date_format')
                                    ->label('Date Format')
                                    ->options(UserDateFormat::options())
                                    ->descriptions(UserDateFormat::descriptions())
                                    ->inline()
                                    ->required(),
                            ]),

                        Section::make('Notifications')
                            ->id('notifications')
                            ->schema([
                                Fieldset::make('Finances')
                                    ->key('notifications-finances', isInheritable: false)
                                    ->columns(1)
                                    ->extraAttributes(['class' => 'fi-profile-notification-group'])
                                    ->schema([
                                        Toggle::make('notify_budget_alerts')
                                            ->label('Budget Alerts')
                                            ->helperText('In-app inbox when spending exceeds a budget threshold. WhatsApp stays on each Budget.')
                                            ->fieldWrapperView('profile-toggle-field-wrapper')
                                            ->extraFieldWrapperAttributes(['class' => 'fi-profile-toggle-field'])
                                            ->visible(fn (): bool => HouseholdAccess::isPrimary()),

                                        Toggle::make('notify_receipt_review')
                                            ->label('Receipt Review')
                                            ->helperText('In-app inbox when a receipt cannot be parsed automatically.')
                                            ->fieldWrapperView('profile-toggle-field-wrapper')
                                            ->extraFieldWrapperAttributes(['class' => 'fi-profile-toggle-field'])
                                            ->default(true),

                                        Toggle::make('notify_recurring_reminders')
                                            ->label('Recurring Reminders')
                                            ->helperText('Due and overdue reminders. In-app vs WhatsApp stays on each Recurring.')
                                            ->fieldWrapperView('profile-toggle-field-wrapper')
                                            ->extraFieldWrapperAttributes(['class' => 'fi-profile-toggle-field'])
                                            ->live()
                                            ->default(true),

                                        Group::make([
                                            Slider::make('recurring_reminder_lead_days')
                                                ->label('Days Before Due')
                                                ->range(minValue: 0, maxValue: 14)
                                                ->step(1)
                                                ->decimalPlaces(0)
                                                ->default(7)
                                                ->tooltips(RawJs::make(<<<'JS'
                                                    (() => {
                                                        const days = Math.round($value);

                                                        if (days === 0) {
                                                            return 'Due day only';
                                                        }

                                                        return days === 1 ? '1 day before' : `${days} days before`;
                                                    })()
                                                    JS))
                                                ->helperText('Remind on the due day and up to this many days before. Overdue payments still remind daily.')
                                                ->required()
                                                ->dehydrated(),

                                            TimePicker::make('recurring_reminder_time')
                                                ->label('Send At')
                                                ->helperText('Local time from the Profile timezone. Reminders send once per day at or after this time. Choosing a time that is already past today waits until tomorrow.')
                                                ->native(false)
                                                ->seconds(false)
                                                ->hoursStep(1)
                                                ->minutesStep(5)
                                                ->required()
                                                ->dehydrated(),
                                        ])
                                            ->key('recurring-reminder-schedule', isInheritable: false)
                                            ->visible(fn (Get $get): bool => (bool) $get('notify_recurring_reminders'))
                                            ->extraAttributes(['class' => 'fi-nested-fields']),
                                    ]),

                                Fieldset::make('Account')
                                    ->key('notifications-account', isInheritable: false)
                                    ->columns(1)
                                    ->extraAttributes(['class' => 'fi-profile-notification-group'])
                                    ->schema([
                                        Toggle::make('notify_profile_updates')
                                            ->label('Profile Update Alerts')
                                            ->helperText('In-app inbox when profile settings change.')
                                            ->fieldWrapperView('profile-toggle-field-wrapper')
                                            ->extraFieldWrapperAttributes(['class' => 'fi-profile-toggle-field']),
                                    ]),

                                Fieldset::make('Tools')
                                    ->key('notifications-tools', isInheritable: false)
                                    ->columns(1)
                                    ->extraAttributes(['class' => 'fi-profile-notification-group'])
                                    ->visible(fn (): bool => HouseholdAccess::isPrimary())
                                    ->schema([
                                        Toggle::make('notify_evolution_api')
                                            ->label('Evolution API')
                                            ->helperText('In-app inbox when Evolution API connects or disconnects.')
                                            ->fieldWrapperView('profile-toggle-field-wrapper')
                                            ->extraFieldWrapperAttributes(['class' => 'fi-profile-toggle-field']),

                                        Toggle::make('notify_service_status')
                                            ->label('Service Status')
                                            ->helperText('In-app inbox when a monitored service becomes degraded or down, and when it recovers.')
                                            ->fieldWrapperView('profile-toggle-field-wrapper')
                                            ->extraFieldWrapperAttributes(['class' => 'fi-profile-toggle-field'])
                                            ->default(true),

                                        Toggle::make('notify_backups')
                                            ->label('Backup Alerts')
                                            ->helperText('In-app inbox when a backup is created, restored, or deleted.')
                                            ->fieldWrapperView('profile-toggle-field-wrapper')
                                            ->extraFieldWrapperAttributes(['class' => 'fi-profile-toggle-field'])
                                            ->default(true),
                                    ]),

                                Fieldset::make('Coming soon')
                                    ->key('notifications-coming-soon', isInheritable: false)
                                    ->columns(1)
                                    ->extraAttributes(['class' => 'fi-profile-notification-group'])
                                    ->schema([
                                        Toggle::make('notify_email_digest')
                                            ->label('Email Digest')
                                            ->helperText('Coming soon — preference saved for future digest emails.')
                                            ->disabled()
                                            ->fieldWrapperView('profile-toggle-field-wrapper')
                                            ->extraFieldWrapperAttributes(['class' => 'fi-profile-toggle-field']),
                                    ]),
                            ]),

                        $this->getDangerZoneSection(),
                    ]),

                Grid::make(1)
                    ->columnSpan(1)
                    ->columnOrder([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->extraAttributes(['class' => 'fi-profile-sidebar-sticky'])
                    ->schema([
                        Section::make('Profile Photo')
                            ->id('profile-photo')
                            ->extraAttributes(['class' => 'fi-profile-photo-section'])
                            ->schema([
                                Flex::make([
                                    $this->getAvatarFormComponent(),
                                ])->alignCenter(),
                            ]),

                        Section::make('Personal Details')
                            ->id('personal-details')
                            ->schema([
                                $this->getNameFormComponent(),
                                TextInput::make('display_name')
                                    ->label('Display Name')
                                    ->characterLimit(FieldCharacterLimits::DISPLAY_NAME)
                                    ->placeholder('Display name'),

                                TextInput::make('phone')
                                    ->label('WhatsApp Number')
                                    ->tel()
                                    ->required()
                                    ->placeholder('+60123456789')
                                    ->maxLength(20)
                                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                        if (blank($value)) {
                                            return;
                                        }

                                        if (PhoneNumber::normalize(is_string($value) ? $value : null) === null) {
                                            $fail('Enter a valid Malaysian WhatsApp number (e.g. +60123456789, 60123456789, or 0123456789).');
                                        }
                                    })
                                    ->dehydrateStateUsing(fn (?string $state): ?string => PhoneNumber::normalize($state)),
                                DateOfBirthPicker::make(),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->queryStringIdentifier('activeSessions')
            ->records(fn (): array => app(ActiveSessionService::class)->recordsForTable(
                $this->getUser(),
                session()->getId(),
            ))
            ->columns([
                TextColumn::make('device_class')
                    ->label('Device')
                    ->description(fn (array $record): string => $record['device_detail'])
                    ->weight(FontWeight::Medium),

                TextColumn::make('is_current')
                    ->label('Current Session')
                    ->badge(fn (bool $state): bool => $state)
                    ->color('primary')
                    ->formatStateUsing(fn (bool $state): ?string => $state ? 'This device' : null),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->since()
                    ->dateTimeTooltip(),
            ])
            ->modifyUngroupedRecordActionsUsing(fn (Action $action): Action => $action->button())
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->button()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke session')
                    ->modalDescription('This device will be signed out immediately.')
                    ->modalSubmitActionLabel('Revoke')
                    ->disabled(fn (array $record): bool => $record['is_current'])
                    ->action(function (array $record): void {
                        app(ActiveSessionService::class)->revoke(
                            $this->getUser(),
                            $record['id'],
                            session()->getId(),
                        );

                        $this->resetTable();

                        FilamentNotification::make()
                            ->title('Session revoked')
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No active sessions found')
            ->emptyStateIcon(Heroicon::OutlinedComputerDesktop);
    }

    protected function getDangerZoneSection(): Section
    {
        return Section::make('Danger Zone')
            ->id('danger-zone')
            ->key('dangerZone')
            ->collapsed(true)
            ->visible(fn (): bool => HouseholdAccess::isPrimary())
            ->extraAttributes(['class' => 'fi-danger-zone-section'])
            ->schema([
                Toggle::make('enable_reset_data')
                    ->label('Reset data')
                    ->onColor('danger')
                    ->helperText('Deletes all application data. Your account is kept. An automatic backup is created first.')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if ($state) {
                            $set('enable_delete_account', false);
                            $set('delete_confirmation_phrase', null);
                            $set('delete_confirmation_password', null);

                            return;
                        }

                        $set('reset_confirmation_phrase', null);
                        $set('reset_confirmation_password', null);
                    }),

                Group::make([
                    TextInput::make('reset_confirmation_phrase')
                        ->label('Confirmation phrase')
                        ->placeholder(self::RESET_CONFIRMATION_PHRASE)
                        ->helperText('Type exactly: '.self::RESET_CONFIRMATION_PHRASE)
                        ->live()
                        ->dehydrated(false),

                    TextInput::make('reset_confirmation_password')
                        ->label('Current password')
                        ->password()
                        ->revealable()
                        ->live()
                        ->dehydrated(false),
                ])
                    ->key('reset-data-confirmation', isInheritable: false)
                    ->visible(fn (Get $get): bool => (bool) $get('enable_reset_data'))
                    ->extraAttributes(['class' => 'fi-nested-fields']),

                Toggle::make('enable_delete_account')
                    ->label('Delete account')
                    ->onColor('danger')
                    ->helperText('Deletes all application data and removes your account. An automatic backup is created first.')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if ($state) {
                            $set('enable_reset_data', false);
                            $set('reset_confirmation_phrase', null);
                            $set('reset_confirmation_password', null);

                            return;
                        }

                        $set('delete_confirmation_phrase', null);
                        $set('delete_confirmation_password', null);
                    }),

                Group::make([
                    TextInput::make('delete_confirmation_phrase')
                        ->label('Confirmation phrase')
                        ->placeholder(self::DELETE_CONFIRMATION_PHRASE)
                        ->helperText('Type exactly: '.self::DELETE_CONFIRMATION_PHRASE)
                        ->live()
                        ->dehydrated(false),

                    TextInput::make('delete_confirmation_password')
                        ->label('Current password')
                        ->password()
                        ->revealable()
                        ->live()
                        ->dehydrated(false),
                ])
                    ->key('delete-account-confirmation', isInheritable: false)
                    ->visible(fn (Get $get): bool => (bool) $get('enable_delete_account'))
                    ->extraAttributes(['class' => 'fi-nested-fields']),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->visible(fn (): bool => ! $this->isDangerZoneArmed()),
            $this->getCancelFormAction(),
            $this->getResetDataFormAction(),
            $this->getDeleteAccountFormAction(),
        ];
    }

    protected function getResetDataFormAction(): Action
    {
        return Action::make('resetData')
            ->label('Reset data')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => $this->isResetDataReady())
            ->action(function (): void {
                if (! $this->isDangerZonePasswordValid('reset_confirmation_password')) {
                    FilamentNotification::make()
                        ->title('Incorrect password')
                        ->body('The current password you entered is incorrect.')
                        ->danger()
                        ->send();

                    return;
                }

                $this->replaceMountedAction('confirmResetData');
            });
    }

    protected function getDeleteAccountFormAction(): Action
    {
        return Action::make('deleteAccount')
            ->label('Delete account')
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->visible(fn (): bool => $this->isDeleteAccountReady())
            ->action(function (): void {
                if (! $this->isDangerZonePasswordValid('delete_confirmation_password')) {
                    FilamentNotification::make()
                        ->title('Incorrect password')
                        ->body('The current password you entered is incorrect.')
                        ->danger()
                        ->send();

                    return;
                }

                $this->replaceMountedAction('confirmDeleteAccount');
            });
    }

    public function confirmResetDataAction(): Action
    {
        return Action::make('confirmResetData')
            ->label('Reset data')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Reset all data')
            ->modalDescription('This permanently deletes all application data. Your account will remain. You will be signed out.')
            ->modalSubmitActionLabel('Reset data')
            ->action(function (AccountDangerZoneService $accountDangerZoneService, BackupNotificationService $backupNotificationService): void {
                if (! $this->isResetDataReady() || ! $this->isDangerZonePasswordValid('reset_confirmation_password')) {
                    FilamentNotification::make()
                        ->title('Unable to reset data')
                        ->danger()
                        ->send();

                    return;
                }

                $created = $accountDangerZoneService->resetData($this->getDangerZoneUser());
                $backupNotificationService->notifyRestoreToken($created->restoreToken);

                FilamentAuthLogout::logoutToLogin($this);
            });
    }

    public function confirmDeleteAccountAction(): Action
    {
        return Action::make('confirmDeleteAccount')
            ->label('Delete account')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete account')
            ->modalDescription('This permanently deletes all data and user account. You will be signed out.')
            ->modalSubmitActionLabel('Proceed Account Deletion')
            ->action(function (AccountDangerZoneService $accountDangerZoneService): void {
                if (! $this->isDeleteAccountReady() || ! $this->isDangerZonePasswordValid('delete_confirmation_password')) {
                    FilamentNotification::make()
                        ->title('Unable to delete account')
                        ->danger()
                        ->send();

                    return;
                }

                $created = $accountDangerZoneService->createPreDeleteBackup($this->getDangerZoneUser());
                $this->pendingRestoreToken = $created->restoreToken;
                $this->pendingDeleteBackupId = $created->backup->getKey();

                $this->replaceMountedAction('storeRestoreKitThenDelete');
            });
    }

    public function storeRestoreKitThenDeleteAction(): Action
    {
        return Action::make('storeRestoreKitThenDelete')
            ->label('Delete account')
            ->color('danger')
            ->modalHeading('Save your recovery token')
            ->modalDescription('This token is shown only once. Save / copy it somewhere safe, and use it to restore your account later.')
            ->fillForm(fn (): array => [
                'restore_token' => (string) $this->pendingRestoreToken,
            ])
            ->form([
                TextInput::make('restore_token')
                    ->label('Recovery token')
                    ->readOnly()
                    ->copyable()
                    ->dehydrated(false),
            ])
            ->modalSubmitActionLabel('I have stored it, proceed download backup ZIP file')
            ->action(function (AccountDangerZoneService $accountDangerZoneService, BackupService $backupService): void {
                if (! $this->isDeleteAccountReady() || ! $this->isDangerZonePasswordValid('delete_confirmation_password')) {
                    FilamentNotification::make()
                        ->title('Unable to delete account')
                        ->danger()
                        ->send();

                    return;
                }

                $backup = Backup::query()->find($this->pendingDeleteBackupId);

                if (! $backup instanceof Backup) {
                    FilamentNotification::make()
                        ->title('Unable to delete account')
                        ->danger()
                        ->send();

                    return;
                }

                $downloadUrl = $backupService->temporaryDownloadUrl($backup);

                $accountDangerZoneService->completeAccountDeletion($this->getDangerZoneUser());

                $this->pendingRestoreToken = null;
                $this->pendingDeleteBackupId = null;

                $this->js('window.open('.Js::from($downloadUrl).', "_blank")');

                FilamentAuthLogout::logoutToLogin($this);
            });
    }

    protected function getDangerZoneUser(): User
    {
        $user = $this->getUser();

        if (! $user instanceof User) {
            throw new LogicException('The authenticated user must be an instance of '.User::class.'.');
        }

        return $user;
    }

    protected function getDangerZoneDataValue(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    protected function isResetDataReady(): bool
    {
        return (bool) $this->getDangerZoneDataValue('enable_reset_data')
            && $this->getDangerZoneDataValue('reset_confirmation_phrase') === self::RESET_CONFIRMATION_PHRASE
            && filled($this->getDangerZoneDataValue('reset_confirmation_password'));
    }

    protected function isDeleteAccountReady(): bool
    {
        return (bool) $this->getDangerZoneDataValue('enable_delete_account')
            && $this->getDangerZoneDataValue('delete_confirmation_phrase') === self::DELETE_CONFIRMATION_PHRASE
            && filled($this->getDangerZoneDataValue('delete_confirmation_password'));
    }

    protected function isDangerZoneArmed(): bool
    {
        return $this->isResetDataReady() || $this->isDeleteAccountReady();
    }

    protected function isDangerZonePasswordValid(string $field): bool
    {
        $password = $this->getDangerZoneDataValue($field);

        return is_string($password)
            && Hash::check($password, $this->getUser()->getAuthPassword());
    }

    protected function getGenerateStrongPasswordActionComponent(): Component
    {
        return Actions::make([
            Action::make('generateStrongPassword')
                ->label('Generate Strong Password')
                ->icon(Heroicon::OutlinedCodeBracketSquare)
                ->color('gray')
                ->action(function (Set $set): void {
                    $password = Str::password(16);

                    $set('password', $password);
                    $set('passwordConfirmation', $password);

                    FilamentNotification::make()
                        ->title('Password generated')
                        ->success()
                        ->persistent()
                        ->actions([
                            Action::make('copyGeneratedPassword')
                                ->label('Copy password')
                                ->button()
                                ->icon(Heroicon::OutlinedClipboardDocument)
                                ->alpineClickHandler(ClipboardCopy::alpineClickHandler(
                                    $password,
                                    'Password copied to clipboard',
                                )),
                        ])
                        ->send();
                }),
        ])
            ->alignment(Alignment::End)
            ->key('generateStrongPasswordActions')
            ->visible(fn (Get $get): bool => (bool) $get('change_password'));
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->visible(fn (Get $get): bool => (bool) $get('change_password'))
            ->required(fn (Get $get): bool => (bool) $get('change_password'))
            ->dehydrated(fn (Get $get, $state): bool => (bool) $get('change_password') && filled($state));
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->visible(fn (Get $get): bool => (bool) $get('change_password'))
            ->required(fn (Get $get): bool => (bool) $get('change_password'));
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return parent::getCurrentPasswordFormComponent()
            ->visible(fn (Get $get): bool => (bool) $get('change_password')
                || ($get('email') !== $this->getUser()->getAttributeValue('email')));
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $oldName = $record->name;
        $oldDisplayName = $record->display_name;
        $oldAvatar = $record->avatar_url;
        $oldEmail = $record->email;
        $oldPhone = $record->phone;
        $oldDateOfBirth = $record->date_of_birth?->format('Y-m-d');
        $oldTimezone = $record->timezone;
        $oldLocale = $record->locale;
        $oldDateFormat = $record->date_format;
        $oldNotifyBudgetAlerts = (bool) $record->notify_budget_alerts;
        $oldNotifyProfileUpdates = (bool) $record->notify_profile_updates;
        $oldNotifyEvolutionApi = (bool) $record->notify_evolution_api;
        $oldNotifyEmailDigest = (bool) $record->notify_email_digest;
        $oldNotifyRecurringReminders = (bool) $record->notify_recurring_reminders;
        $oldNotifyReceiptReview = (bool) $record->notify_receipt_review;
        $oldNotifyServiceStatus = (bool) $record->notify_service_status;
        $oldNotifyBackups = (bool) $record->notify_backups;
        $oldRecurringReminderLeadDays = (int) $record->recurring_reminder_lead_days;
        $oldRecurringReminderTime = $record instanceof User
            ? $record->recurringReminderTimeHi()
            : '08:00';
        $oldStylizedBackgroundEnabled = (bool) $record->stylized_background_enabled;
        $oldReduceMotion = (bool) $record->reduce_motion;
        $oldMobileNavEnabled = (bool) $record->mobile_nav_enabled;
        $passwordChanged = filled($data['password'] ?? null);
        $profileSnapshot = $this->profileSnapshotForPageRefresh($record);

        $updatedRecord = parent::handleRecordUpdate($record, $data);

        if ($updatedRecord instanceof User) {
            app(FamilyMemberLoginService::class)->syncFamilyMemberFromLoginUser($updatedRecord);

            $scheduleChanged = $oldNotifyRecurringReminders !== (bool) $updatedRecord->notify_recurring_reminders
                || $oldRecurringReminderTime !== $updatedRecord->recurringReminderTimeHi()
                || $oldTimezone !== $updatedRecord->timezone;

            if ($scheduleChanged) {
                app(RecurringReminderService::class)
                    ->suppressTodayPassIfSendTimePassed($updatedRecord);
            }

            if ($oldReduceMotion !== (bool) $updatedRecord->reduce_motion) {
                $this->js('window.tidoSetReduceMotion('.Js::from((bool) $updatedRecord->reduce_motion).')');
            }

            if ($oldMobileNavEnabled !== (bool) $updatedRecord->mobile_nav_enabled) {
                $this->js('window.tidoSetMobileNav('.Js::from((bool) $updatedRecord->mobile_nav_enabled).')');
            }
        }

        $changes = [];
        if ($oldName !== $updatedRecord->name) {
            $changes[] = 'Full Name';
        }
        if ($oldDisplayName !== $updatedRecord->display_name) {
            $changes[] = 'Display Name';
        }
        if ($oldAvatar !== $updatedRecord->avatar_url) {
            $changes[] = 'Profile photo';
        }
        if (array_key_exists('email', $data) && $oldEmail !== $data['email']) {
            $changes[] = 'Email';
        }
        if ($passwordChanged) {
            $changes[] = 'Password';
        }
        if ($oldPhone !== $updatedRecord->phone) {
            $changes[] = 'WhatsApp Number';
        }
        if ($oldDateOfBirth !== $updatedRecord->date_of_birth?->format('Y-m-d')) {
            $changes[] = 'Date of birth';
        }
        if ($oldTimezone !== $updatedRecord->timezone) {
            $changes[] = 'Timezone';
        }
        if ($oldLocale !== $updatedRecord->locale) {
            $changes[] = 'Language';
        }
        if ($oldDateFormat !== $updatedRecord->date_format) {
            $changes[] = 'Date format';
        }
        if ($oldNotifyBudgetAlerts !== (bool) $updatedRecord->notify_budget_alerts) {
            $changes[] = 'Budget alerts';
        }
        if ($oldNotifyRecurringReminders !== (bool) $updatedRecord->notify_recurring_reminders) {
            $changes[] = 'Recurring reminders';
        }
        if ($oldRecurringReminderLeadDays !== (int) $updatedRecord->recurring_reminder_lead_days) {
            $changes[] = 'Recurring reminder lead days';
        }
        if (
            $updatedRecord instanceof User
            && $oldRecurringReminderTime !== $updatedRecord->recurringReminderTimeHi()
        ) {
            $changes[] = 'Recurring reminder send time';
        }
        if ($oldNotifyProfileUpdates !== (bool) $updatedRecord->notify_profile_updates) {
            $changes[] = 'Profile update alerts';
        }
        if ($oldNotifyEvolutionApi !== (bool) $updatedRecord->notify_evolution_api) {
            $changes[] = 'Evolution API alerts';
        }
        if ($oldNotifyEmailDigest !== (bool) $updatedRecord->notify_email_digest) {
            $changes[] = 'Email digest';
        }
        if ($oldNotifyReceiptReview !== (bool) $updatedRecord->notify_receipt_review) {
            $changes[] = 'Receipt review alerts';
        }
        if ($oldNotifyServiceStatus !== (bool) $updatedRecord->notify_service_status) {
            $changes[] = 'Service Status alerts';
        }
        if ($oldNotifyBackups !== (bool) $updatedRecord->notify_backups) {
            $changes[] = 'Backup alerts';
        }
        if ($oldStylizedBackgroundEnabled !== (bool) $updatedRecord->stylized_background_enabled) {
            $changes[] = 'Stylized background';
        }
        if ($oldReduceMotion !== (bool) $updatedRecord->reduce_motion) {
            $changes[] = 'Reduce Motion';
        }
        if ($oldMobileNavEnabled !== (bool) $updatedRecord->mobile_nav_enabled) {
            $changes[] = 'Mobile Nav';
        }

        if (! empty($changes) && $updatedRecord->notify_profile_updates) {
            $changeList = implode(', ', $changes);

            FilamentNotification::make()
                ->title('Profile Settings Updated')
                ->body("You updated your profile settings: {$changeList}.")
                ->success()
                ->actions([
                    Action::make('edit_profile')
                        ->label('Edit Profile')
                        ->button()
                        ->url(static::getUrl(), shouldOpenInNewTab: true),
                ])
                ->sendToDatabase($record);
        }

        if ($this->profilePageRefreshRequired($profileSnapshot, $updatedRecord)) {
            $this->js('window.location.reload()');
        }

        return $updatedRecord;
    }

    /**
     * Profile fields whose changes require a full page reload after save.
     *
     * @return list<string>
     */
    protected static function profileFieldsRequiringPageRefresh(): array
    {
        return [
            // Personalize & Appearance → Preferences
            'reduce_motion',
            'mobile_nav_enabled',
            // Regional Preferences
            'locale',
            'timezone',
            'date_format',
            // Appearance / identity
            'avatar_url',
            'stylized_background_enabled',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function profileSnapshotForPageRefresh(Model $record): array
    {
        $snapshot = [];

        foreach (static::profileFieldsRequiringPageRefresh() as $field) {
            $snapshot[$field] = $this->profileFieldValueForPageRefresh($record, $field);
        }

        return $snapshot;
    }

    protected function profileFieldValueForPageRefresh(Model $record, string $field): mixed
    {
        return match ($field) {
            'reduce_motion', 'stylized_background_enabled', 'mobile_nav_enabled' => (bool) $record->getAttributeValue($field),
            default => $record->getAttributeValue($field),
        };
    }

    /**
     * @param  array<string, mixed>  $before
     */
    protected function profilePageRefreshRequired(array $before, Model $after): bool
    {
        foreach (static::profileFieldsRequiringPageRefresh() as $field) {
            if ($before[$field] !== $this->profileFieldValueForPageRefresh($after, $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Override to:
     * 1. Use custom VerifyEmailChange notification with email-change-specific content and expiry info
     * 2. Add a delay on the verification email to avoid Mailtrap rate limit
     * 3. Align signed URL + cache TTL with auth.verification.expire (seconds)
     */
    protected function sendEmailChangeVerification(Model $record, string $newEmail): void
    {
        if ($record->getAttributeValue('email') === $newEmail) {
            return;
        }

        $notification = app(VerifyEmailChange::class);
        $notification->url = EmailChangeVerification::verifyUrl($record, $newEmail);

        $verificationSignature = Query::new($notification->url)->get('signature');

        cache()->put($verificationSignature, true, ttl: EmailChangeVerification::expiresAt());

        $record->notify(app(NoticeOfEmailChangeRequest::class, [
            'blockVerificationUrl' => EmailChangeVerification::blockUrl($record, $newEmail, $verificationSignature),
            'newEmail' => $newEmail,
        ]));

        $newEmailRecipient = $this->getEmailChangeVerificationRecipientWithNewEmail($record, $notification, $newEmail);

        if ($record instanceof HasLocalePreference) {
            $notification->locale($record->preferredLocale());
        }

        $notification->delay(now()->addSeconds(5));

        Notification::route('mail', $newEmailRecipient)
            ->notify($notification);

        $this->getEmailChangeVerificationSentNotification($newEmail)?->send();

        $this->data['email'] = $record->getAttributeValue('email');
    }
}
