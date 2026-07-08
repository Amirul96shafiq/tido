<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Notifications\VerifyEmailChange;
use Filament\Auth\Notifications\NoticeOfEmailChangeRequest;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use League\Uri\Components\Query;

class EditProfile extends BaseEditProfile
{
    protected function getAvatarFormComponent(): Component
    {
        return FileUpload::make('avatar_url')
            ->label('Profile Photo')
            ->avatar()
            ->disk('public')
            ->directory('avatars')
            ->image()
            ->imageEditor()
            ->maxSize(2048)
            ->circleCropper();
    }

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                $this->getAvatarFormComponent(),
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                Toggle::make('change_password')
                    ->label('Change Password')
                    ->live()
                    ->dehydrated(false),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
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

        if (! empty($changes)) {
            $changeList = implode(', ', $changes);

            \Filament\Notifications\Notification::make()
                ->title('Profile Settings Updated')
                ->body("You updated your profile settings: {$changeList}.")
                ->success()
                ->actions([
                    \Filament\Actions\Action::make('edit_profile')
                        ->label('Edit Profile')
                        ->button()
                        ->url(static::getUrl()),
                ])
                ->sendToDatabase($record);
        }

        // Auto-refresh the page when the profile photo changes so the
        // sidebar / header avatar updates immediately.
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

        // Send notice to old email (immediate)
        $record->notify(app(NoticeOfEmailChangeRequest::class, [
            'blockVerificationUrl' => Filament::getBlockEmailChangeVerificationUrl($record, $newEmail, $verificationSignature),
            'newEmail' => $newEmail,
        ]));

        // Send verification to new email (delayed by 5 seconds to avoid Mailtrap rate limit)
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
