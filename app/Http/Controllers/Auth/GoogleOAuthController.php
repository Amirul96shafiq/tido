<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Filament\Pages\GoogleOAuthPage;
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
    public const SESSION_INTENT_KEY = 'google_oauth_intent';

    public const INTENT_LOGIN = 'login';

    public const INTENT_LINK = 'link';

    public function redirect(
        GoogleOAuthSocialite $socialite,
        GoogleOAuthAuthenticator $authenticator,
    ): RedirectResponse {
        $settings = GoogleOAuthSettings::platform();

        if (! $settings->isSignInAvailable()) {
            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }

        session([self::SESSION_INTENT_KEY => self::INTENT_LOGIN]);

        try {
            return $socialite->driver($settings)->redirect();
        } catch (Throwable) {
            $authenticator->logFailure();

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }
    }

    public function link(
        Request $request,
        GoogleOAuthSocialite $socialite,
        GoogleOAuthAuthenticator $authenticator,
    ): RedirectResponse {
        $settings = GoogleOAuthSettings::platform();
        $user = $this->resolveLinkUser($request, $settings);

        if (! $user instanceof User) {
            return redirect()->to(Filament::getLoginUrl());
        }

        if (! $settings->isSignInAvailable()) {
            return $this->redirectToGoogleOAuthPage(linked: false, error: true);
        }

        // Keep Socialite state on the OAuth callback host (e.g. localhost).
        if ($settings->usesCrossHostRedirect() && ! $settings->requestHostMatchesRedirectHost($request->getHost())) {
            $token = Str::random(64);
            Cache::put($this->linkHandoffCacheKey($token), [
                'user_id' => $user->getKey(),
                'household_id' => $user->household_id,
            ], now()->addMinutes(10));

            return redirect()->away($settings->linkUrlOnRedirectHost().'?token='.$token);
        }

        session([self::SESSION_INTENT_KEY => self::INTENT_LINK]);

        try {
            return $socialite->driver($settings)->redirect();
        } catch (Throwable) {
            $authenticator->logFailure(householdId: $user->household_id);

            return $this->redirectToGoogleOAuthPage(linked: false, error: true);
        }
    }

    public function callback(
        Request $request,
        GoogleOAuthSocialite $socialite,
        GoogleOAuthAuthenticator $authenticator,
        ActiveSessionService $activeSessionService,
    ): RedirectResponse {
        $settings = GoogleOAuthSettings::platform();
        $intent = session(self::SESSION_INTENT_KEY, self::INTENT_LOGIN);

        if (! $settings->isSignInAvailable()) {
            $authenticator->logFailure();

            return $this->failRedirect($intent);
        }

        if ($request->has('error')) {
            $authenticator->logFailure();

            return $this->failRedirect($intent);
        }

        try {
            $googleUser = $socialite->driver($settings)->user();
        } catch (Throwable) {
            $authenticator->logFailure();

            return $this->failRedirect($intent);
        }

        if ($intent === self::INTENT_LINK) {
            return $this->handleLinkCallback($googleUser, $authenticator);
        }

        $user = $authenticator->resolveUser($googleUser);

        if ($user === null) {
            $authenticator->logFailure();

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }

        session()->forget(self::SESSION_INTENT_KEY);

        if ($settings->usesCrossHostRedirect()) {
            $token = Str::random(64);
            Cache::put($this->handoffCacheKey($token), [
                'user_id' => $user->getKey(),
                'household_id' => $user->household_id,
            ], now()->addMinutes(2));

            $completeUrl = rtrim((string) config('app.url'), '/').'/admin/auth/google/complete?token='.$token;

            return redirect()->away($completeUrl);
        }

        $this->loginUser($user, $activeSessionService);
        $authenticator->logSuccess($user);

        return redirect()->to(Filament::getUrl());
    }

    public function complete(
        Request $request,
        GoogleOAuthAuthenticator $authenticator,
        ActiveSessionService $activeSessionService,
    ): RedirectResponse {
        $settings = GoogleOAuthSettings::platform();

        if (! $settings->isSignInAvailable()) {
            $authenticator->logFailure();

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }

        $token = $request->query('token');

        if (! is_string($token) || strlen($token) < 32) {
            $authenticator->logFailure();

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }

        $payload = Cache::pull($this->handoffCacheKey($token));

        if (! is_array($payload) || ! isset($payload['user_id'], $payload['household_id'])) {
            $authenticator->logFailure();

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }

        $user = User::query()->find($payload['user_id']);

        if ($user === null || (int) $user->household_id !== (int) $payload['household_id']) {
            $authenticator->logFailure(householdId: (int) $payload['household_id']);

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }

        $this->loginUser($user, $activeSessionService);
        $authenticator->logSuccess($user);

        return redirect()->to(Filament::getUrl());
    }

    private function handleLinkCallback(
        \Laravel\Socialite\Contracts\User $googleUser,
        GoogleOAuthAuthenticator $authenticator,
    ): RedirectResponse {
        $user = Auth::guard(Filament::getAuthGuard())->user();

        if (! $user instanceof User) {
            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('google_oauth_error', true);
        }

        session()->forget(self::SESSION_INTENT_KEY);

        if (! $authenticator->linkToUser($user, $googleUser)) {
            $authenticator->logFailure(
                message: 'Could not link Google account.',
                householdId: $user->household_id,
            );

            return $this->redirectToGoogleOAuthPage(linked: false, error: true);
        }

        $authenticator->logLinked($user->fresh() ?? $user);

        return $this->redirectToGoogleOAuthPage(linked: true, error: false);
    }

    private function resolveLinkUser(Request $request, GoogleOAuthSettings $settings): ?User
    {
        $token = $request->query('token');

        if (is_string($token) && strlen($token) >= 32) {
            $payload = Cache::pull($this->linkHandoffCacheKey($token));

            if (is_array($payload) && isset($payload['user_id'], $payload['household_id'])) {
                $user = User::query()->find($payload['user_id']);

                if ($user instanceof User && (int) $user->household_id === (int) $payload['household_id']) {
                    Auth::guard(Filament::getAuthGuard())->login($user, false);
                    session()->regenerate();

                    return $user;
                }
            }
        }

        $user = Auth::guard(Filament::getAuthGuard())->user();

        return $user instanceof User ? $user : null;
    }

    private function redirectToGoogleOAuthPage(bool $linked, bool $error): RedirectResponse
    {
        $settings = GoogleOAuthSettings::platform();
        $pageUrl = rtrim((string) config('app.url'), '/').'/admin/google-oauth';

        if ($settings->usesCrossHostRedirect() && ! $settings->requestHostMatchesRedirectHost()) {
            if ($linked) {
                return redirect()->away($pageUrl.'?google_oauth_linked=1');
            }

            if ($error) {
                return redirect()->away($pageUrl.'?google_oauth_link_error=1');
            }

            return redirect()->away($pageUrl);
        }

        $redirect = redirect()->to(GoogleOAuthPage::getUrl());

        if ($linked) {
            return $redirect->with('google_oauth_linked', true);
        }

        if ($error) {
            return $redirect->with('google_oauth_link_error', true);
        }

        return $redirect;
    }

    private function failRedirect(mixed $intent): RedirectResponse
    {
        session()->forget(self::SESSION_INTENT_KEY);

        if ($intent === self::INTENT_LINK) {
            return $this->redirectToGoogleOAuthPage(linked: false, error: true);
        }

        return redirect()
            ->to(Filament::getLoginUrl())
            ->with('google_oauth_error', true);
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

    private function linkHandoffCacheKey(string $token): string
    {
        return 'google_oauth_link_handoff:'.$token;
    }
}
