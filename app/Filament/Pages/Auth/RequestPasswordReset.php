<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Notifications\ResetPassword as ResetPasswordNotification;
use App\Support\EmailChangeVerification;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Password;
use LogicException;
use SensitiveParameter;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function getSubheading(): string|Htmlable|null
    {
        return 'Enter the registered email address to receive a password reset link.';
    }

    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $data = $this->form->getState();

        $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            $this->getCredentialsFromFormData($data),
            function (CanResetPassword $user, #[SensitiveParameter] string $token): void {
                if (
                    ($user instanceof FilamentUser) &&
                    (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel()))
                ) {
                    return;
                }

                if (! method_exists($user, 'notify')) {
                    $userClass = $user::class;

                    throw new LogicException("Model [{$userClass}] does not have a [notify()] method.");
                }

                $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                $notification->url = EmailChangeVerification::passwordResetUrl($token, $user);

                $user->notify($notification);

                if (class_exists(PasswordResetLinkSent::class)) {
                    event(new PasswordResetLinkSent($user));
                }
            },
        );

        if ($status !== Password::RESET_LINK_SENT) {
            $this->getFailureNotification($status)?->send();

            return;
        }

        $this->getSentNotification($status)?->send();

        $this->form->fill();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                RenderHook::make(PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_BEFORE),
                $this->getFormContentComponent(),
                Actions::make([
                    $this->loginAction(),
                ])
                    ->alignment(Alignment::Start)
                    ->fullWidth(false)
                    ->visible(fn (): bool => filament()->hasLogin()),
                RenderHook::make(PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_AFTER),
            ]);
    }
}
