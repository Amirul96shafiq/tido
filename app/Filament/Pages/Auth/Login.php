<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\WhatsAppLoginOtpService;
use App\Support\PhoneNumber;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\TextSize;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use RuntimeException;
use SensitiveParameter;

class Login extends BaseLogin
{
    /**
     * phone | otp | password
     */
    public string $loginMode = 'phone';

    #[Locked]
    public ?string $pendingPhone = null;

    public ?int $otpCooldownEndsAt = null;

    public ?string $lastOtpPhone = null;

    public function getHeading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getHeading();
        }

        return match ($this->loginMode) {
            'otp' => 'Enter the code',
            default => 'Keep it tidy. Get it done.',
        };
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        return match ($this->loginMode) {
            'otp' => filled($this->pendingPhone)
                ? "A One-Time Password (OTP) code has been sent via WhatsApp to {$this->pendingPhone}. You can use the OTP code here."
                : 'Enter the 6-digit code from WhatsApp.',
            default => 'Where tidy preparation meets finished work, then "tido" (sleep).',
        };
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getPhoneFormComponent(),
                $this->getOtpFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label('WhatsApp number')
            ->tel()
            ->placeholder('0123456789 or +60123456789')
            ->autocomplete('tel')
            ->autofocus(fn (): bool => $this->loginMode === 'phone')
            ->required(fn (): bool => $this->loginMode === 'phone')
            ->visible(fn (): bool => $this->loginMode === 'phone')
            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                if (PhoneNumber::normalize(is_string($value) ? $value : null) === null) {
                    $fail('Enter a valid Malaysian WhatsApp number (e.g. +60123456789 or 0123456789).');
                }
            });
    }

    protected function getOtpFormComponent(): Component
    {
        return OneTimeCodeInput::make('otp')
            ->label('WhatsApp code')
            ->length(6)
            ->autofocus(fn (): bool => $this->loginMode === 'otp')
            ->required(fn (): bool => $this->loginMode === 'otp')
            ->visible(fn (): bool => $this->loginMode === 'otp')
            ->extraInputAttributes([
                // Paint digits into each cell — input letter-spacing paint drifts visually even when metrics match.
                'x-init' => <<<'JS'
                    const paintOtpDigits = () => {
                        const ctn = $el.closest('.fi-one-time-code-input-ctn')
                        if (! ctn) {
                            return
                        }

                        const cells = Array.from(ctn.children).filter((node) => node.tagName === 'DIV')
                        const digits = String($el.value || '').replace(/\D/g, '')

                        cells.forEach((cell, index) => {
                            let label = cell.querySelector('.tido-otp-digit')
                            if (! label) {
                                label = document.createElement('span')
                                label.className = 'tido-otp-digit'
                                label.setAttribute('aria-hidden', 'true')
                                cell.appendChild(label)
                            }
                            label.textContent = digits[index] || ''
                        })

                        $el.style.color = 'transparent'
                        $el.style.webkitTextFillColor = 'transparent'
                        $el.style.caretColor = 'transparent'
                    }

                    $nextTick(() => paintOtpDigits())
                    $el.addEventListener('input', () => paintOtpDigits())
                    $el.addEventListener('focus', () => paintOtpDigits())
                    $el.addEventListener('blur', () => paintOtpDigits())
                    $el.addEventListener('paste', () => {
                        requestAnimationFrame(() => paintOtpDigits())
                    })
                JS,
            ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::auth/pages/login.form.email.label'))
            ->email()
            ->autocomplete('username')
            ->autofocus(fn (): bool => $this->loginMode === 'password')
            ->required(fn (): bool => $this->loginMode === 'password')
            ->visible(fn (): bool => $this->loginMode === 'password');
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::auth/pages/login.form.password.label'))
            ->hint(filament()->hasPasswordReset() ? new HtmlString(Blade::render('<x-filament::link :href="filament()->getRequestPasswordResetUrl()" tabindex="-1"> {{ __(\'filament-panels::auth/pages/login.actions.request_password_reset.label\') }}</x-filament::link>')) : null)
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required(fn (): bool => $this->loginMode === 'password')
            ->visible(fn (): bool => $this->loginMode === 'password');
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()
            ->visible(fn (): bool => in_array($this->loginMode, ['otp', 'password'], true));
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        // Keep every step action mounted with visibility toggles so Livewire/Filament
        // does not reuse a stale password-step "authenticate" button on the phone step.
        return [
            $this->getSendOtpFormAction(),
            $this->getVerifyOtpFormAction(),
            $this->getPasswordSignInFormAction(),
            $this->getResendOtpFormAction(),
        ];
    }

    protected function getSendOtpFormAction(): Action
    {
        return Action::make('sendOtp')
            ->label(fn (): string|HtmlString => $this->isPhoneSendOnCooldown()
                ? $this->otpCooldownActionLabelHtml('Send new code in ', 'Send WhatsApp code')
                : 'Send WhatsApp code')
            ->disabled(fn (): bool => $this->isPhoneSendOnCooldown())
            ->submit('sendOtp')
            ->visible(fn (): bool => $this->loginMode === 'phone');
    }

    protected function getResendOtpFormAction(): Action
    {
        return Action::make('resendOtp')
            ->label(fn (): string|HtmlString => $this->otpCooldownRemainingSeconds() > 0
                ? $this->otpCooldownActionLabelHtml('Resend in ', 'Resend code')
                : 'Resend code')
            ->color('gray')
            ->disabled(fn (): bool => $this->otpCooldownRemainingSeconds() > 0)
            ->action(function (): void {
                $this->resendOtp();
            })
            ->visible(fn (): bool => $this->loginMode === 'otp');
    }

    protected function getVerifyOtpFormAction(): Action
    {
        return Action::make('verifyOtp')
            ->label('Verify code & sign in')
            ->submit('authenticate')
            ->visible(fn (): bool => $this->loginMode === 'otp');
    }

    protected function getPasswordSignInFormAction(): Action
    {
        return Action::make('passwordSignIn')
            ->label(__('filament-panels::auth/pages/login.form.actions.authenticate.label'))
            ->submit('authenticate')
            ->visible(fn (): bool => $this->loginMode === 'password');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return match ($this->loginMode) {
            'otp' => $this->getVerifyOtpFormAction(),
            'password' => $this->getPasswordSignInFormAction(),
            default => $this->getSendOtpFormAction(),
        };
    }

    public function useDifferentNumberAction(): Action
    {
        return Action::make('useDifferentNumber')
            ->link()
            ->label('Use a different number')
            ->requiresConfirmation()
            ->modalHidden(fn (): bool => $this->otpCooldownRemainingSeconds() <= 0)
            ->modalHeading('Use a different number?')
            ->modalDescription('This will reset the current login process. You will need to enter a WhatsApp number and request a new code.')
            ->modalSubmitActionLabel('Use a different number')
            ->action(function (): void {
                $this->showPhoneStep();
            })
            ->visible(fn (): bool => $this->loginMode === 'otp');
    }

    protected function getUseDifferentNumberComponent(): Component
    {
        return Flex::make([
            Text::make('Not receive OTP code?')
                ->color('gray')
                ->size(TextSize::Small)
                ->grow(false),
            Actions::make([
                $this->useDifferentNumberAction(),
            ])
                ->key('use-different-number-actions')
                ->alignment(Alignment::Start)
                ->fullWidth(false)
                ->grow(false),
        ])
            ->dense()
            ->extraAttributes(['class' => 'tido-auth-use-different-number'])
            ->visible(fn (): bool => blank($this->userUndertakingMultiFactorAuthentication)
                && $this->loginMode === 'otp');
    }

    public function selectOtpLoginTab(): void
    {
        if ($this->loginMode !== 'password') {
            return;
        }

        if (filled($this->pendingPhone)) {
            $this->showOtpStep();

            return;
        }

        $this->showPhoneStep();
    }

    public function selectPasswordLoginTab(): void
    {
        if ($this->loginMode !== 'password') {
            $this->showPasswordStep();
        }
    }

    protected function getLoginModeTabsComponent(): Component
    {
        return Html::make(fn (): HtmlString => new HtmlString(
            Blade::render(
                '<x-auth-login-tabs :login-mode="$loginMode" />',
                ['loginMode' => $this->loginMode],
            )
        ))
            ->visible(fn (): bool => blank($this->userUndertakingMultiFactorAuthentication));
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->key('login-form-'.$this->loginMode)
            ->extraAttributes([
                'class' => 'tido-login-auth-panel',
                'wire:transition' => 'tido-login-auth',
            ])
            ->livewireSubmitHandler(fn (): string => $this->loginMode === 'phone' ? 'sendOtp' : 'authenticate')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(Alignment::Start)
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->key('form-actions-'.$this->loginMode),
            ])
            ->visible(fn (): bool => blank($this->userUndertakingMultiFactorAuthentication));
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE),
                $this->getLoginModeTabsComponent(),
                $this->getFormContentComponent(),
                $this->getMultiFactorChallengeFormContentComponent(),
                $this->getUseDifferentNumberComponent(),
                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER),
            ]);
    }

    public function otpCooldownRemainingSeconds(): int
    {
        if ($this->otpCooldownEndsAt === null) {
            return 0;
        }

        return max(0, $this->otpCooldownEndsAt - time());
    }

    public function isPhoneSendOnCooldown(): bool
    {
        if ($this->otpCooldownRemainingSeconds() <= 0 || blank($this->lastOtpPhone)) {
            return false;
        }

        $enteredPhone = PhoneNumber::normalize((string) ($this->data['phone'] ?? $this->lastOtpPhone));

        return $enteredPhone !== null && $enteredPhone === $this->lastOtpPhone;
    }

    public function otpCooldownActionLabelHtml(string $countingPrefix, string $readyLabel): HtmlString
    {
        $endsAt = (int) ($this->otpCooldownEndsAt ?? 0);

        return new HtmlString(
            '<span'
            .' wire:key="otp-cooldown-action-'.$endsAt.'-'.$this->loginMode.'"'
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

    protected function syncOtpCooldownFromUser(User $user): void
    {
        $this->otpCooldownEndsAt = app(WhatsAppLoginOtpService::class)->cooldownEndsAt($user);
    }

    public function showPasswordStep(): void
    {
        $this->loginMode = 'password';
        $this->data['otp'] = null;
        $this->resetErrorBag();
        $this->dispatch('$refresh');
    }

    public function showOtpStep(): void
    {
        $this->loginMode = 'otp';
        $this->data['otp'] = null;
        $this->data['email'] = null;
        $this->data['password'] = null;
        $this->resetErrorBag();
        $this->dispatch('$refresh');
    }

    public function showPhoneStep(): void
    {
        $this->loginMode = 'phone';
        $this->pendingPhone = null;
        $this->data['otp'] = null;
        $this->data['email'] = null;
        $this->data['password'] = null;

        if (filled($this->lastOtpPhone)) {
            $this->data['phone'] = $this->lastOtpPhone;
        }

        $this->resetErrorBag();
        $this->dispatch('$refresh');
    }

    public function sendOtp(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $this->validate([
            'data.phone' => ['required', 'string'],
        ]);

        $phone = PhoneNumber::normalize((string) ($this->data['phone'] ?? ''));

        if ($phone === null) {
            throw ValidationException::withMessages([
                'data.phone' => 'Enter a valid Malaysian WhatsApp number.',
            ]);
        }

        $user = $this->findUserByPhone($phone);

        if ($user === null) {
            throw ValidationException::withMessages([
                'data.phone' => 'Unable to send a login code for this number.',
            ]);
        }

        $otpService = app(WhatsAppLoginOtpService::class);

        try {
            $otpService->send($user);
        } catch (RuntimeException $exception) {
            $this->syncOtpCooldownFromUser($user);

            throw ValidationException::withMessages([
                'data.phone' => $exception->getMessage(),
            ]);
        }

        $this->pendingPhone = $phone;
        $this->lastOtpPhone = $phone;
        $this->syncOtpCooldownFromUser($user);
        $this->loginMode = 'otp';
        $this->data['otp'] = null;
        $this->resetErrorBag();

        Notification::make()
            ->title('WhatsApp code sent')
            ->body('Check WhatsApp for the 6-digit login code.')
            ->success()
            ->send();
    }

    public function resendOtp(): void
    {
        if ($this->loginMode !== 'otp' || blank($this->pendingPhone)) {
            $this->showPhoneStep();

            return;
        }

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $phone = PhoneNumber::normalize($this->pendingPhone);

        if ($phone === null) {
            $this->showPhoneStep();

            throw ValidationException::withMessages([
                'data.phone' => 'Enter a valid Malaysian WhatsApp number.',
            ]);
        }

        $user = $this->findUserByPhone($phone);

        if ($user === null) {
            throw ValidationException::withMessages([
                'data.otp' => 'Unable to resend a login code for this number.',
            ]);
        }

        $otpService = app(WhatsAppLoginOtpService::class);

        try {
            $otpService->send($user);
        } catch (RuntimeException $exception) {
            $this->syncOtpCooldownFromUser($user);

            Notification::make()
                ->title('Could not resend code')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            throw ValidationException::withMessages([
                'data.otp' => $exception->getMessage(),
            ]);
        }

        $this->lastOtpPhone = $phone;
        $this->syncOtpCooldownFromUser($user);
        $this->data['otp'] = null;
        $this->resetErrorBag();

        Notification::make()
            ->title('WhatsApp code resent')
            ->body('Check WhatsApp for the new 6-digit login code.')
            ->success()
            ->send();
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        if ($this->loginMode === 'password') {
            return $this->authenticateWithPassword();
        }

        if ($this->loginMode !== 'otp') {
            $this->sendOtp();

            return null;
        }

        return $this->authenticateWithOtp();
    }

    protected function authenticateWithPassword(): ?LoginResponse
    {
        $this->validate([
            'data.email' => ['required', 'email'],
            'data.password' => ['required', 'string'],
        ]);

        $data = [
            'email' => $this->data['email'] ?? null,
            'password' => $this->data['password'] ?? null,
            'remember' => (bool) ($this->data['remember'] ?? false),
        ];

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();
        $authProvider = $authGuard->getProvider(); /** @phpstan-ignore-line */
        $credentials = $this->getCredentialsFromFormData($data);
        $remember = $data['remember'];

        $user = $authProvider->retrieveByCredentials($credentials);

        if ((! $user) || (! $authProvider->validateCredentials($user, $credentials))) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwPasswordFailureValidationException();
        }

        if (! $authGuard->attemptWhen($credentials, function ($candidate): bool {
            if (! ($candidate instanceof FilamentUser)) {
                return true;
            }

            return $candidate->canAccessPanel(Filament::getCurrentOrDefaultPanel());
        }, $remember)) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwPasswordFailureValidationException();
        }

        session()->regenerate();

        $this->sendLoginSuccessNotification();

        return app(LoginResponse::class);
    }

    protected function authenticateWithOtp(): ?LoginResponse
    {
        $this->validate([
            'data.otp' => ['required', 'string'],
        ]);

        $phone = PhoneNumber::normalize($this->pendingPhone ?? (string) ($this->data['phone'] ?? ''));
        $otp = (string) ($this->data['otp'] ?? '');
        $remember = (bool) ($this->data['remember'] ?? true);

        if ($phone === null) {
            $this->showPhoneStep();

            throw ValidationException::withMessages([
                'data.phone' => 'Enter a valid Malaysian WhatsApp number.',
            ]);
        }

        $user = $this->findUserByPhone($phone);

        if ($user === null || ! app(WhatsAppLoginOtpService::class)->verify($user, $otp)) {
            $this->throwOtpFailureValidationException();
        }

        if ($user instanceof FilamentUser && ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            $this->throwOtpFailureValidationException();
        }

        $this->otpCooldownEndsAt = null;
        $this->lastOtpPhone = null;

        Filament::auth()->login($user, $remember);

        session()->regenerate();

        $this->sendLoginSuccessNotification();

        return app(LoginResponse::class);
    }

    protected function findUserByPhone(string $normalizedPhone): ?User
    {
        $localForm = '0'.substr($normalizedPhone, 2);

        return User::query()
            ->where('phone', $normalizedPhone)
            ->orWhere('phone', '+'.$normalizedPhone)
            ->orWhere('phone', $localForm)
            ->first();
    }

    protected function sendLoginSuccessNotification(): void
    {
        Notification::make()
            ->title('Signed in successfully')
            ->success()
            ->send();
    }

    protected function throwFailureValidationException(): never
    {
        $this->throwOtpFailureValidationException();
    }

    protected function throwOtpFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.otp' => 'Invalid or expired login code.',
        ]);
    }

    protected function throwPasswordFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'email' => $data['email'],
            'password' => $data['password'],
        ];
    }
}
