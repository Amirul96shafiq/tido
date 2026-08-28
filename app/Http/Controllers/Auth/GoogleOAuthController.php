<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\ActiveSessionService;
use App\Services\GoogleOAuth\GoogleOAuthAuthenticator;
use App\Services\GoogleOAuth\GoogleOAuthSettings;
use App\Services\GoogleOAuth\GoogleOAuthSocialite;
use App\Support\FilamentAuthLogin;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class GoogleOAuthController extends Controller
{
    public function redirect(
        GoogleOAuthSettings $settings,
        GoogleOAuthSocialite $socialite,
        GoogleOAuthAuthenticator $authenticator,
    ): RedirectResponse {
        if (! $settings->isSignInAvailable()) {
            abort(404);
        }

        try {
            return $socialite->driver()->redirect();
        } catch (Throwable) {
            $authenticator->logFailure();

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }
    }

    public function callback(
        Request $request,
        GoogleOAuthSettings $settings,
        GoogleOAuthSocialite $socialite,
        GoogleOAuthAuthenticator $authenticator,
        ActiveSessionService $activeSessionService,
    ): RedirectResponse {
        if (! $settings->isSignInAvailable()) {
            abort(404);
        }

        if ($request->has('error')) {
            $authenticator->logFailure();

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }

        try {
            $googleUser = $socialite->driver()->user();
        } catch (Throwable) {
            $authenticator->logFailure();

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }

        $user = $authenticator->resolveUser($googleUser);

        if ($user === null) {
            $authenticator->logFailure();

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }

        if ($settings->usesCrossHostRedirect()) {
            $token = Str::random(64);
            Cache::put($this->handoffCacheKey($token), $user->getKey(), now()->addMinutes(2));

            $completeUrl = rtrim((string) config('app.url'), '/').'/admin/auth/google/complete?token='.$token;

            return redirect()->away($completeUrl);
        }

        $this->loginUser($user, $activeSessionService);

        $authenticator->logSuccess($user);

        return redirect()->to(Filament::getUrl());
    }

    public function complete(
        Request $request,
        GoogleOAuthSettings $settings,
        GoogleOAuthAuthenticator $authenticator,
        ActiveSessionService $activeSessionService,
    ): RedirectResponse {
        if (! $settings->isSignInAvailable()) {
            abort(404);
        }

        $token = $request->query('token');

        if (! is_string($token) || strlen($token) < 32) {
            $authenticator->logFailure();

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }

        $userId = Cache::pull($this->handoffCacheKey($token));

        if ($userId === null) {
            $authenticator->logFailure();

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            $authenticator->logFailure();

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }

        $this->loginUser($user, $activeSessionService);

        $authenticator->logSuccess($user);

        return redirect()->to(Filament::getUrl());
    }

    private function loginUser(User $user, ActiveSessionService $activeSessionService): void
    {
        Auth::guard(Filament::getAuthGuard())->login($user, false);
        session()->regenerate();

        FilamentAuthLogin::sendSignedInViaGoogle();

        $sessionId = session()->getId();
        app()->terminating(function () use ($activeSessionService, $sessionId): void {
            $activeSessionService->stampCreatedAt($sessionId);
        });
    }

    private function handoffCacheKey(string $token): string
    {
        return 'google_oauth_handoff:'.$token;
    }
}
