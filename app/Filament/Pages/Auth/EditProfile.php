<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Enums\UserDateFormat;
use App\Enums\UserLocale;
use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Models\User;
use App\Notifications\VerifyEmailChange;
use App\Services\AccountDangerZoneService;
use App\Support\FilamentAuthLogout;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Auth\Notifications\NoticeOfEmailChangeRequest;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use League\Uri\Components\Query;
use LogicException;

class EditProfile extends BaseEditProfile
{
    use HasStickyBlurFormActions;
    use PrependsHomeBreadcrumb;

    private const RESET_CONFIRMATION_PHRASE = 'CONFIRM RESET DATA';

    private const DELETE_CONFIRMATION_PHRASE = 'CONFIRM DELETE ACCOUNT';

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
        return parent::getNameFormComponent()
            ->label('Full Name')
            ->placeholder('Full name');
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
                        Section::make('Personalize')
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
                            ])
                            ->columns(1),

                        Section::make('Account & Security')
                            ->schema([
                                $this->getEmailFormComponent(),
                                Toggle::make('change_password')
                                    ->label('Change Password')
                                    ->live()
                                    ->dehydrated(false),
                                $this->getGenerateStrongPasswordActionComponent(),
                                $this->getPasswordFormComponent(),
                                $this->getPasswordConfirmationFormComponent(),
                                $this->getCurrentPasswordFormComponent(),
                            ]),

                        Section::make('Regional Preferences')
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
                                Select::make('date_format')
                                    ->label('Date Format')
                                    ->options(UserDateFormat::options())
                                    ->searchable()
                                    ->required(),
                            ]),

                        Section::make('Notifications')
                            ->schema([
                                Toggle::make('notify_budget_alerts')
                                    ->label('Budget Alerts')
                                    ->helperText('Receive in-app notifications when spending exceeds your budget threshold.'),
                                Toggle::make('notify_profile_updates')
                                    ->label('Profile Update Alerts')
                                    ->helperText('Receive in-app notifications when your profile settings change.'),
                                Toggle::make('notify_evolution_api')
                                    ->label('EvolutionAPI')
                                    ->helperText('Receive in-app notifications when EvolutionAPI connects or disconnects.'),
                                Toggle::make('notify_email_digest')
                                    ->label('Email Digest')
                                    ->helperText('Coming soon — preference saved for future digest emails.'),
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
                            ->extraAttributes(['class' => 'fi-profile-photo-section'])
                            ->schema([
                                Flex::make([
                                    $this->getAvatarFormComponent(),
                                ])->alignCenter(),
                            ]),

                        Section::make('Personal Details')
                            ->schema([
                                $this->getNameFormComponent(),
                                TextInput::make('display_name')
                                    ->label('Display Name')
                                    ->maxLength(255)
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
                                TextInput::make('date_of_birth')
                                    ->label('Date of Birth')
                                    ->mask('99/99/9999')
                                    ->placeholder('DD/MM/YYYY')
                                    ->formatStateUsing(function (mixed $state): ?string {
                                        if (blank($state)) {
                                            return null;
                                        }

                                        if ($state instanceof CarbonInterface) {
                                            return $state->format('d/m/Y');
                                        }

                                        if (! is_string($state)) {
                                            return null;
                                        }

                                        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $state) === 1) {
                                            return $state;
                                        }

                                        try {
                                            return Carbon::parse($state)->format('d/m/Y');
                                        } catch (\Throwable) {
                                            return $state;
                                        }
                                    })
                                    ->dehydrateStateUsing(function (?string $state): ?string {
                                        if (blank($state)) {
                                            return null;
                                        }

                                        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $state) !== 1) {
                                            return null;
                                        }

                                        try {
                                            $date = Carbon::createFromFormat('!d/m/Y', $state);
                                        } catch (\Throwable) {
                                            return null;
                                        }

                                        if ($date === false || $date->format('d/m/Y') !== $state) {
                                            return null;
                                        }

                                        return $date->format('Y-m-d');
                                    })
                                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                        if (blank($value)) {
                                            return;
                                        }

                                        if (! is_string($value) || preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) !== 1) {
                                            $fail('Enter a valid date as DD/MM/YYYY.');

                                            return;
                                        }

                                        try {
                                            $date = Carbon::createFromFormat('!d/m/Y', $value);
                                        } catch (\Throwable) {
                                            $fail('Enter a valid date as DD/MM/YYYY.');

                                            return;
                                        }

                                        if ($date === false || $date->format('d/m/Y') !== $value) {
                                            $fail('Enter a valid date as DD/MM/YYYY.');

                                            return;
                                        }

                                        if ($date->isFuture()) {
                                            $fail('Date of birth cannot be in the future.');
                                        }
                                    })
                                    ->suffixAction(
                                        Action::make('pickDateOfBirth')
                                            ->icon(Heroicon::CalendarDays)
                                            ->tooltip('Open calendar')
                                            ->modalWidth('sm')
                                            ->modalHeading('Date of Birth')
                                            ->modalSubmitActionLabel('Select')
                                            ->fillForm(function (Get $get): array {
                                                $current = $get('date_of_birth');
                                                $picked = null;

                                                if (is_string($current) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $current) === 1) {
                                                    try {
                                                        $date = Carbon::createFromFormat('!d/m/Y', $current);

                                                        if ($date !== false && $date->format('d/m/Y') === $current) {
                                                            $picked = $date->format('Y-m-d');
                                                        }
                                                    } catch (\Throwable) {
                                                        $picked = null;
                                                    }
                                                }

                                                return ['picked' => $picked];
                                            })
                                            ->schema([
                                                DatePicker::make('picked')
                                                    ->hiddenLabel()
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->maxDate(now())
                                                    ->required(),
                                            ])
                                            ->action(function (array $data, Set $set): void {
                                                if (blank($data['picked'] ?? null)) {
                                                    return;
                                                }

                                                $set(
                                                    'date_of_birth',
                                                    Carbon::parse((string) $data['picked'])->format('d/m/Y'),
                                                );
                                            }),
                                    ),
                            ]),
                    ]),
            ]);
    }

    protected function getDangerZoneSection(): Section
    {
        return Section::make('Danger Zone')
            ->key('dangerZone')
            ->collapsed(true)
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
                TextInput::make('reset_confirmation_phrase')
                    ->label('Confirmation phrase')
                    ->placeholder(self::RESET_CONFIRMATION_PHRASE)
                    ->helperText('Type exactly: '.self::RESET_CONFIRMATION_PHRASE)
                    ->live()
                    ->visible(fn (Get $get): bool => (bool) $get('enable_reset_data'))
                    ->dehydrated(false),
                TextInput::make('reset_confirmation_password')
                    ->label('Current password')
                    ->password()
                    ->revealable()
                    ->live()
                    ->visible(fn (Get $get): bool => (bool) $get('enable_reset_data'))
                    ->dehydrated(false),
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
                TextInput::make('delete_confirmation_phrase')
                    ->label('Confirmation phrase')
                    ->placeholder(self::DELETE_CONFIRMATION_PHRASE)
                    ->helperText('Type exactly: '.self::DELETE_CONFIRMATION_PHRASE)
                    ->live()
                    ->visible(fn (Get $get): bool => (bool) $get('enable_delete_account'))
                    ->dehydrated(false),
                TextInput::make('delete_confirmation_password')
                    ->label('Current password')
                    ->password()
                    ->revealable()
                    ->live()
                    ->visible(fn (Get $get): bool => (bool) $get('enable_delete_account'))
                    ->dehydrated(false),
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
            ->action(function (AccountDangerZoneService $accountDangerZoneService): void {
                if (! $this->isResetDataReady() || ! $this->isDangerZonePasswordValid('reset_confirmation_password')) {
                    FilamentNotification::make()
                        ->title('Unable to reset data')
                        ->danger()
                        ->send();

                    return;
                }

                $accountDangerZoneService->resetData($this->getDangerZoneUser());

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
            ->modalSubmitActionLabel('Delete account')
            ->action(function (AccountDangerZoneService $accountDangerZoneService): void {
                if (! $this->isDeleteAccountReady() || ! $this->isDangerZonePasswordValid('delete_confirmation_password')) {
                    FilamentNotification::make()
                        ->title('Unable to delete account')
                        ->danger()
                        ->send();

                    return;
                }

                $backup = $accountDangerZoneService->deleteAccount($this->getDangerZoneUser());

                $downloadUrl = URL::temporarySignedRoute(
                    'backups.download',
                    now()->addMinutes(10),
                    ['backup' => $backup],
                );

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
                ->action(function (Set $set, EditProfile $livewire): void {
                    $password = Str::password(16);

                    $set('password', $password);
                    $set('passwordConfirmation', $password);

                    $livewire->js('window.navigator.clipboard.writeText('.Js::from($password).')');

                    FilamentNotification::make()
                        ->title('Password copied to clipboard')
                        ->success()
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
            ->visible(fn (Get $get): bool => (bool) $get('change_password') || ($get('email') !== $this->getUser()->getAttributeValue('email')));
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
        $oldStylizedBackgroundEnabled = (bool) $record->stylized_background_enabled;
        $passwordChanged = filled($data['password'] ?? null);

        $updatedRecord = parent::handleRecordUpdate($record, $data);

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
        if ($oldNotifyProfileUpdates !== (bool) $updatedRecord->notify_profile_updates) {
            $changes[] = 'Profile update alerts';
        }
        if ($oldNotifyEvolutionApi !== (bool) $updatedRecord->notify_evolution_api) {
            $changes[] = 'EvolutionAPI alerts';
        }
        if ($oldNotifyEmailDigest !== (bool) $updatedRecord->notify_email_digest) {
            $changes[] = 'Email digest';
        }
        if ($oldStylizedBackgroundEnabled !== (bool) $updatedRecord->stylized_background_enabled) {
            $changes[] = 'Stylized background';
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

        if (
            $oldAvatar !== $updatedRecord->avatar_url
            || $oldStylizedBackgroundEnabled !== (bool) $updatedRecord->stylized_background_enabled
        ) {
            $this->js('window.location.reload()');
        }

        return $updatedRecord;
    }

    /**
     * Override to:
     * 1. Use custom VerifyEmailChange notification with email-change-specific content and expiry info
     * 2. Add a delay on the verification email to avoid Mailtrap rate limit
     * 3. Align cache TTL with auth.verification.expire config
     */
    protected function sendEmailChangeVerification(Model $record, string $newEmail): void
    {
        if ($record->getAttributeValue('email') === $newEmail) {
            return;
        }

        $expireMinutes = config('auth.verification.expire', 60);

        $notification = app(VerifyEmailChange::class);
        $notification->url = Filament::getVerifyEmailChangeUrl($record, $newEmail);

        $verificationSignature = Query::new($notification->url)->get('signature');

        cache()->put($verificationSignature, true, ttl: now()->addMinutes($expireMinutes));

        $record->notify(app(NoticeOfEmailChangeRequest::class, [
            'blockVerificationUrl' => Filament::getBlockEmailChangeVerificationUrl($record, $newEmail, $verificationSignature),
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
