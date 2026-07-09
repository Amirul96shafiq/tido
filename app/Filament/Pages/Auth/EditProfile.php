<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Enums\UserDateFormat;
use App\Enums\UserLocale;
use App\Notifications\VerifyEmailChange;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Auth\Notifications\NoticeOfEmailChangeRequest;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use League\Uri\Components\Query;

class EditProfile extends BaseEditProfile
{
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
                        Section::make('Account & Security')
                            ->description('Manage your login credentials.')
                            ->schema([
                                $this->getEmailFormComponent(),
                                Toggle::make('change_password')
                                    ->label('Change Password')
                                    ->live()
                                    ->dehydrated(false),
                                $this->getPasswordFormComponent(),
                                $this->getPasswordConfirmationFormComponent(),
                                $this->getCurrentPasswordFormComponent(),
                            ]),

                        Section::make('Regional Preferences')
                            ->description('Customize how dates and times are displayed.')
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
                            ->description('Choose which alerts you receive.')
                            ->schema([
                                Toggle::make('notify_budget_alerts')
                                    ->label('Budget Alerts')
                                    ->helperText('Receive in-app notifications when spending exceeds your budget threshold.'),
                                Toggle::make('notify_profile_updates')
                                    ->label('Profile Update Alerts')
                                    ->helperText('Receive in-app notifications when your profile settings change.'),
                                Toggle::make('notify_email_digest')
                                    ->label('Email Digest')
                                    ->helperText('Coming soon — preference saved for future digest emails.'),
                            ]),
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
                            ->description('Upload a photo to personalize your account.')
                            ->extraAttributes(['class' => 'fi-profile-photo-section'])
                            ->schema([
                                Flex::make([
                                    $this->getAvatarFormComponent(),
                                ])->alignCenter(),
                            ]),

                        Section::make('Personal Details')
                            ->description('Your display name and contact number.')
                            ->schema([
                                $this->getNameFormComponent(),
                                TextInput::make('phone')
                                    ->label('WhatsApp Number')
                                    ->tel()
                                    ->placeholder('+60123456789')
                                    ->maxLength(20)
                                    ->helperText('Used for WhatsApp login OTP. Prefer the same number as PERSONAL_WHATSAPP_NUMBER.')
                                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                        if (blank($value)) {
                                            return;
                                        }

                                        if (PhoneNumber::normalize(is_string($value) ? $value : null) === null) {
                                            $fail('Enter a valid Malaysian WhatsApp number (e.g. +60123456789, 60123456789, or 0123456789).');
                                        }
                                    })
                                    ->dehydrateStateUsing(fn (?string $state): ?string => PhoneNumber::normalize($state)),
                            ]),
                    ]),
            ]);
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
        $oldAvatar = $record->avatar_url;
        $oldEmail = $record->email;
        $oldPhone = $record->phone;
        $oldTimezone = $record->timezone;
        $oldLocale = $record->locale;
        $oldDateFormat = $record->date_format;
        $oldNotifyBudgetAlerts = $record->notify_budget_alerts;
        $oldNotifyProfileUpdates = $record->notify_profile_updates;
        $oldNotifyEmailDigest = $record->notify_email_digest;
        $passwordChanged = filled($data['password'] ?? null);

        $updatedRecord = parent::handleRecordUpdate($record, $data);

        $changes = [];
        if ($oldName !== $updatedRecord->name) {
            $changes[] = 'Name';
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
            $changes[] = 'Phone';
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
        if ($oldNotifyBudgetAlerts !== $updatedRecord->notify_budget_alerts) {
            $changes[] = 'Budget alerts';
        }
        if ($oldNotifyProfileUpdates !== $updatedRecord->notify_profile_updates) {
            $changes[] = 'Profile update alerts';
        }
        if ($oldNotifyEmailDigest !== $updatedRecord->notify_email_digest) {
            $changes[] = 'Email digest';
        }

        if (! empty($changes) && $updatedRecord->notify_profile_updates) {
            $changeList = implode(', ', $changes);

            \Filament\Notifications\Notification::make()
                ->title('Profile Settings Updated')
                ->body("You updated your profile settings: {$changeList}.")
                ->success()
                ->actions([
                    Action::make('edit_profile')
                        ->label('Edit Profile')
                        ->button()
                        ->url(static::getUrl()),
                ])
                ->sendToDatabase($record);
        }

        if ($oldAvatar !== $updatedRecord->avatar_url) {
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
