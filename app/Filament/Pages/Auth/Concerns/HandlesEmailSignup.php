<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth\Concerns;

use App\Filament\Support\SignupGreetingHeading;
use App\Services\EmailSignupOtpService;
use App\Services\GoogleOAuth\GoogleOAuthSettings;
use App\Services\GoogleOAuth\GoogleOAuthSignupPendingService;
use App\Services\HouseholdRegistrationService;
use App\Support\EmailSignupDevOtp;
use App\Support\FilamentAuthLogin;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\TextSize;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use RuntimeException;

trait HandlesEmailSignup
{
    /**
     * form | otp
     */
    public string $signupMode = 'form';

    #[Locked]
    public ?string $pendingSignupEmail = null;

    #[Locked]
    public ?string $googleVerifiedSignupEmail = null;

    public ?int $signupOtpCooldownEndsAt = null;

    public ?string $lastSignupEmail = null;

    protected function isSignUpPanel(): bool
    {
        return $this->authPanel === 'sign-up';
    }

    protected function isSignupOtpStep(): bool
    {
        return $this->isSignUpPanel() && $this->signupMode === 'otp';
    }

    protected function isSignupFormStep(): bool
    {
        return $this->isSignUpPanel() && $this->signupMode === 'form';
    }

    protected function signupHeading(): string|Htmlable|null
    {
        if ($this->isSignupOtpStep()) {
            return new HtmlString('<span wire:key="tido-signup-otp-heading">Enter The Code</span>');
        }

        if ($this->isSignupFormStep()) {
            return SignupGreetingHeading::html();
        }

        return null;
    }

    protected function signupSubheading(): string|Htmlable|null
    {
        if (! $this->isSignupOtpStep()) {
            return new HtmlString(
                'Sign up your account, start <span class="underline">ti</span>dy + <span class="underline">do</span>ne work, then "<span class="underline">tido</span>" (sleep).',
            );
        }

        if (blank($this->pendingSignupEmail)) {
            return 'Enter the 6-digit confirmation code from email.';
        }

        $message = 'A 6-digit confirmation code has been sent to '
            .'<span class="text-primary-600 underline dark:text-primary-400">'
            .e((string) $this->pendingSignupEmail)
            .'</span>.';

        $normalizedEmail = EmailSignupDevOtp::normalizeEmail($this->pendingSignupEmail);

        if ($normalizedEmail !== null && EmailSignupDevOtp::isDevAddress($normalizedEmail)) {
            $message .= ' Local test mode: use the configured EMAIL_SIGNUP_DEV_OTP code.';
        }

        return new HtmlString($message);
    }

    protected function getSignupEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email address')
            ->email()
            ->autocomplete('email')
            ->required(fn (): bool => $this->isSignupFormStep())
            ->visible(fn (): bool => $this->isSignupFormStep())
            ->disabled(fn (): bool => $this->hasGoogleVerifiedSignup())
            ->dehydrated(true)
            ->suffix(fn (): ?HtmlString => $this->hasGoogleVerifiedSignup()
                ? new HtmlString('<span class="tido-auth-email-verified text-primary-600 dark:text-primary-400">Verified</span>')
                : null)
            ->helperText(fn (): ?string => $this->hasGoogleVerifiedSignup()
                ? 'Email address verified with Google.'
                : null);
    }

    protected function getSignupPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Password')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('new-password')
            ->required(fn (): bool => $this->isSignupFormStep())
            ->visible(fn (): bool => $this->isSignupFormStep())
            ->rule('min:8');
    }

    protected function getSignupPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('password_confirmation')
            ->label('Confirm password')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('new-password')
            ->required(fn (): bool => $this->isSignupFormStep())
            ->visible(fn (): bool => $this->isSignupFormStep())
            ->same('password');
    }

    /**
     * @return array<Action>
     */
    protected function getSignupFormActions(): array
    {
        return [
            $this->getSendSignupOtpFormAction(),
            $this->getVerifySignupOtpFormAction(),
            $this->getResendSignupOtpFormAction(),
        ];
    }

    protected function getSendSignupOtpFormAction(): Action
    {
        return Action::make('sendSignupOtp')
            ->label(fn (): string|HtmlString => $this->isSignupSendOnCooldown()
                ? $this->signupOtpCooldownActionLabelHtml('Send new code in ', 'Start Sign Up')
                : 'Start Sign Up')
            ->disabled(fn (): bool => $this->isSignupSendOnCooldown())
            ->submit('sendSignupOtp')
            ->visible(fn (): bool => $this->isSignupFormStep());
    }

    protected function getResendSignupOtpFormAction(): Action
    {
        return Action::make('resendSignupOtp')
            ->label(fn (): string|HtmlString => $this->signupOtpCooldownRemainingSeconds() > 0
                ? $this->signupOtpCooldownActionLabelHtml('Resend in ', 'Resend code')
                : 'Resend code')
            ->color('gray')
            ->disabled(fn (): bool => $this->signupOtpCooldownRemainingSeconds() > 0)
            ->action(function (): void {
                $this->resendSignupOtp();
            })
            ->visible(fn (): bool => $this->isSignupOtpStep());
    }

    protected function getVerifySignupOtpFormAction(): Action
    {
        return Action::make('verifySignupOtp')
            ->label('Verify code & sign up')
            ->submit('completeSignup')
            ->disabled(fn (): bool => ! $this->isOtpCodeComplete())
            ->alpineClickHandler(<<<'JS'
                if ($el.disabled) {
                    return
                }

                $el.classList.add('tido-auth-btn-loading')

                const stop = Livewire.hook('commit', ({ respond }) => {
                    respond(() => {
                        $el.classList.remove('tido-auth-btn-loading')
                        stop()
                    })
                })
            JS)
            ->extraAttributes([
                'x-bind:disabled' => '(typeof isProcessing !== \'undefined\' && isProcessing) || ! window.Alpine || ! $store.tidoLoginOtp || $store.tidoLoginOtp.len < 6',
            ])
            ->visible(fn (): bool => $this->isSignupOtpStep());
    }

    public function useDifferentEmailAction(): Action
    {
        return Action::make('useDifferentEmail')
            ->link()
            ->label('Use a different email')
            ->requiresConfirmation()
            ->modalHidden(fn (): bool => $this->signupOtpCooldownRemainingSeconds() <= 0)
            ->modalHeading('Use a different email?')
            ->modalDescription('This will reset the current sign-up process. A new email address and confirmation code will be required.')
            ->modalSubmitActionLabel('Use a different email')
            ->action(function (): void {
                $this->showSignupFormStep();
            })
            ->visible(fn (): bool => $this->isSignupOtpStep());
    }

    protected function getUseDifferentEmailComponent(): Component
    {
        return Flex::make([
            Text::make('Did not receive a code?')
                ->color('gray')
                ->size(TextSize::Small)
                ->grow(false),
            Actions::make([
                $this->useDifferentEmailAction(),
            ])
                ->key('use-different-email-actions')
                ->alignment(Alignment::Start)
                ->fullWidth(false)
                ->grow(false),
        ])
            ->dense()
            ->extraAttributes(['class' => 'tido-auth-use-different-number'])
            ->visible(fn (): bool => blank($this->userUndertakingMultiFactorAuthentication)
                && $this->isSignupOtpStep());
    }

    protected function getSignUpFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('signup-form')
            ->key('signup-form-'.$this->signupMode)
            ->extraAttributes([
                'class' => 'tido-login-auth-panel',
            ])
            ->livewireSubmitHandler(fn (): string => match (true) {
                ! $this->isSignupFormStep() => 'completeSignup',
                $this->hasGoogleVerifiedSignup() => 'completeGoogleSignup',
                default => 'sendSignupOtp',
            })
            ->footer([
                Actions::make($this->getSignupFormActions())
                    ->alignment(Alignment::Start)
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->key('signup-form-actions-'.$this->signupMode),
            ])
            ->visible(fn (): bool => blank($this->userUndertakingMultiFactorAuthentication)
                && $this->isSignUpPanel());
    }

    protected function getGoogleSignUpComponent(): Component
    {
        return Html::make(fn (): HtmlString => new HtmlString(
            Blade::render(
                <<<'BLADE'
                <div class="tido-auth-google-sign-in-wrap">
                    <div class="tido-auth-google-divider" aria-hidden="true">
                        <span>or</span>
                    </div>

                    <button
                        type="button"
                        wire:click="continueWithGoogle"
                        class="tido-auth-google-sign-in-btn fi-btn fi-size-md fi-color-gray"
                    >
                        <x-filament::icon icon="icon-google-oauth" class="h-5 w-5 shrink-0" />
                        <span>Continue with Google</span>
                    </button>
                </div>
                BLADE
            )
        ))
            ->visible(fn (): bool => blank($this->userUndertakingMultiFactorAuthentication)
                && $this->isSignUpPanel()
                && $this->isSignupFormStep()
                && GoogleOAuthSettings::platform()->isSignInAvailable());
    }

    protected function hasGoogleVerifiedSignup(): bool
    {
        return filled($this->googleVerifiedSignupEmail)
            && app(GoogleOAuthSignupPendingService::class)->fromSession() !== null;
    }

    protected function restoreGoogleSignupPendingFromSession(): void
    {
        $pending = app(GoogleOAuthSignupPendingService::class)->fromSession();

        if ($pending === null) {
            $this->googleVerifiedSignupEmail = null;

            return;
        }

        $this->googleVerifiedSignupEmail = $pending['email'];
        $this->data['email'] = $pending['email'];
    }

    protected function clearGoogleSignupPending(): void
    {
        app(GoogleOAuthSignupPendingService::class)->forgetSession();
        $this->googleVerifiedSignupEmail = null;
    }

    public function completeGoogleSignup(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        if (! $this->isSignupFormStep() || ! $this->hasGoogleVerifiedSignup()) {
            if (session()->pull('google_signup_pending_expired')) {
                Notification::make()
                    ->title('Google verification expired')
                    ->body('Continue with Google again.')
                    ->warning()
                    ->send();
            }

            $this->clearGoogleSignupPending();
            $this->showSignupFormStep();

            throw ValidationException::withMessages([
                'data.email' => 'Continue with Google again to verify the email address.',
            ]);
        }

        $pending = app(GoogleOAuthSignupPendingService::class)->fromSession();

        if ($pending === null) {
            $this->clearGoogleSignupPending();
            $this->showSignupFormStep();

            throw ValidationException::withMessages([
                'data.email' => 'Continue with Google again to verify the email address.',
            ]);
        }

        $this->validate([
            'data.password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $password = (string) ($this->data['password'] ?? '');

        app(GoogleOAuthSignupPendingService::class)->forgetEmailOtpPending($pending['email']);

        try {
            $user = app(HouseholdRegistrationService::class)->register([
                'name' => $pending['name'],
                'email' => $pending['email'],
                'password' => $password,
                'google_id' => $pending['google_id'],
            ]);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'data.email' => 'Unable to complete sign up for this email address.',
            ]);
        }

        if ($user instanceof FilamentUser && ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            throw ValidationException::withMessages([
                'data.email' => 'Unable to complete sign up for this email address.',
            ]);
        }

        $this->clearGoogleSignupPending();
        $this->resetSignupState();

        Filament::auth()->login($user, true);

        session()->regenerate();

        $this->scheduleSessionCreatedAtStamp();
        FilamentAuthLogin::sendSignedInViaEmailSignUp();

        return app(LoginResponse::class);
    }

    public function signupOtpCooldownRemainingSeconds(): int
    {
        if ($this->signupOtpCooldownEndsAt === null) {
            return 0;
        }

        return max(0, $this->signupOtpCooldownEndsAt - time());
    }

    public function isSignupSendOnCooldown(): bool
    {
        if ($this->signupOtpCooldownRemainingSeconds() <= 0 || blank($this->lastSignupEmail)) {
            return false;
        }

        $enteredEmail = EmailSignupDevOtp::normalizeEmail((string) ($this->data['email'] ?? $this->lastSignupEmail));

        return $enteredEmail !== null && $enteredEmail === EmailSignupDevOtp::normalizeEmail($this->lastSignupEmail);
    }

    public function signupOtpCooldownActionLabelHtml(string $countingPrefix, string $readyLabel): HtmlString
    {
        $endsAt = (int) ($this->signupOtpCooldownEndsAt ?? 0);

        return new HtmlString(
            '<span'
            .' wire:key="signup-otp-cooldown-action-'.$endsAt.'-'.$this->signupMode.'"'
            .' x-data="{ endsAt: '.$endsAt.', now: Math.floor(Date.now() / 1000), refreshed: false, get remaining() { return Math.max(0, this.endsAt - this.now); } }"'
            .' x-init="setInterval(() => { now = Math.floor(Date.now() / 1000); if (remaining === 0 && ! refreshed) { refreshed = true; $wire.$refresh(); } }, 250)"'
            .'>'
            .'<span x-show="remaining > 0" x-cloak>'
            .e($countingPrefix)
            .'<span class="tabular-nums" x-text="remaining + \'s\'"></span>'
            .'</span>'
            .'<span x-show="remaining <= 0" x-cloak>'.e($readyLabel).'</span>'
            .'</span>'
        );
    }

    protected function syncSignupOtpCooldownFromEmail(string $email): void
    {
        $this->signupOtpCooldownEndsAt = app(EmailSignupOtpService::class)->cooldownEndsAt($email);
    }

    public function showSignupFormStep(): void
    {
        $this->signupMode = 'form';
        $this->pendingSignupEmail = null;
        $this->data['otp'] = null;

        if ($this->hasGoogleVerifiedSignup()) {
            $this->restoreGoogleSignupPendingFromSession();
        } elseif (filled($this->lastSignupEmail)) {
            $this->data['email'] = $this->lastSignupEmail;
        }

        $this->data['password'] = null;
        $this->data['password_confirmation'] = null;
        $this->resetErrorBag();
        $this->dispatch('$refresh');
    }

    public function showSignupOtpStep(): void
    {
        $this->signupMode = 'otp';
        $this->data['otp'] = null;
        $this->data['password'] = null;
        $this->data['password_confirmation'] = null;
        $this->resetErrorBag();
        $this->dispatch('$refresh');
    }

    protected function resetSignupState(): void
    {
        $this->signupMode = 'form';
        $this->pendingSignupEmail = null;
        $this->signupOtpCooldownEndsAt = null;
        $this->lastSignupEmail = null;
        $this->data['password_confirmation'] = null;

        if (! app(GoogleOAuthSignupPendingService::class)->fromSession()) {
            $this->googleVerifiedSignupEmail = null;
        }
    }

    public function sendSignupOtp(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $this->validate([
            'data.email' => ['required', 'email', 'max:255'],
            'data.password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = EmailSignupDevOtp::normalizeEmail((string) ($this->data['email'] ?? ''));

        if ($this->hasGoogleVerifiedSignup()) {
            $pending = app(GoogleOAuthSignupPendingService::class)->fromSession();
            $email = $pending['email'] ?? $email;
        }

        $password = (string) ($this->data['password'] ?? '');

        if ($email === null) {
            throw ValidationException::withMessages([
                'data.email' => 'Enter a valid email address.',
            ]);
        }

        $otpService = app(EmailSignupOtpService::class);

        try {
            $otpService->send($email, $password);
        } catch (RuntimeException $exception) {
            $this->syncSignupOtpCooldownFromEmail($email);

            throw ValidationException::withMessages([
                'data.email' => $exception->getMessage(),
            ]);
        }

        $this->pendingSignupEmail = $email;
        $this->lastSignupEmail = $email;
        $this->syncSignupOtpCooldownFromEmail($email);
        $this->showSignupOtpStep();

        Notification::make()
            ->title('Email code sent')
            ->body('Check email for the 6-digit confirmation code.')
            ->success()
            ->send();
    }

    public function resendSignupOtp(): void
    {
        if (! $this->isSignupOtpStep() || blank($this->pendingSignupEmail)) {
            $this->showSignupFormStep();

            return;
        }

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $email = EmailSignupDevOtp::normalizeEmail($this->pendingSignupEmail);

        if ($email === null) {
            $this->showSignupFormStep();

            throw ValidationException::withMessages([
                'data.email' => 'Enter a valid email address.',
            ]);
        }

        $pending = cache()->get('email_signup_pending:'.$email);

        if (! is_array($pending) || ! isset($pending['password'])) {
            $this->showSignupFormStep();

            throw ValidationException::withMessages([
                'data.otp' => 'Unable to resend a confirmation code for this email.',
            ]);
        }

        $decryptedPassword = decrypt((string) $pending['password']);
        $password = is_string($decryptedPassword) ? $decryptedPassword : '';

        if ($password === '') {
            $this->showSignupFormStep();

            throw ValidationException::withMessages([
                'data.otp' => 'Unable to resend a confirmation code for this email.',
            ]);
        }

        $otpService = app(EmailSignupOtpService::class);

        try {
            $otpService->send($email, $password);
        } catch (RuntimeException $exception) {
            $this->syncSignupOtpCooldownFromEmail($email);

            Notification::make()
                ->title('Could not resend code')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            throw ValidationException::withMessages([
                'data.otp' => $exception->getMessage(),
            ]);
        }

        $this->lastSignupEmail = $email;
        $this->syncSignupOtpCooldownFromEmail($email);
        $this->data['otp'] = null;
        $this->resetErrorBag();

        Notification::make()
            ->title('Email code resent')
            ->body('Check email for the new 6-digit confirmation code.')
            ->success()
            ->send();
    }

    public function completeSignup(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        if (! $this->isSignupOtpStep()) {
            $this->sendSignupOtp();

            return null;
        }

        $this->validate([
            'data.otp' => ['required', 'string'],
        ]);

        $email = EmailSignupDevOtp::normalizeEmail($this->pendingSignupEmail ?? (string) ($this->data['email'] ?? ''));
        $otp = (string) ($this->data['otp'] ?? '');

        if ($email === null) {
            $this->showSignupFormStep();

            throw ValidationException::withMessages([
                'data.email' => 'Enter a valid email address.',
            ]);
        }

        $pending = app(EmailSignupOtpService::class)->verify($email, $otp);

        if ($pending === null || $pending['password'] === '') {
            $this->throwSignupOtpFailureValidationException();
        }

        $user = app(HouseholdRegistrationService::class)->register([
            'name' => $pending['name'],
            'email' => $pending['email'],
            'password' => $pending['password'],
        ]);

        if ($user instanceof FilamentUser && ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            $this->throwSignupOtpFailureValidationException();
        }

        $this->signupOtpCooldownEndsAt = null;
        $this->lastSignupEmail = null;
        $this->pendingSignupEmail = null;

        Filament::auth()->login($user, true);

        session()->regenerate();

        $this->scheduleSessionCreatedAtStamp();
        FilamentAuthLogin::sendSignedInViaEmailSignUp();

        return app(LoginResponse::class);
    }

    protected function throwSignupOtpFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.otp' => 'Invalid or expired confirmation code.',
        ]);
    }
}
