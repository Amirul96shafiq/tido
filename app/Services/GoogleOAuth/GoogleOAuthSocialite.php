<?php

declare(strict_types=1);

namespace App\Services\GoogleOAuth;

use App\Support\OutboundHttpCaBundle;
use GuzzleHttp\Client;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Testing\FakeProvider;
use Laravel\Socialite\Two\AbstractProvider;

final class GoogleOAuthSocialite
{
    public function __construct(
        private readonly GoogleOAuthSettings $settings,
    ) {}

    public function driver(): AbstractProvider|FakeProvider
    {
        config([
            'services.google' => array_merge(
                (array) config('services.google', []),
                $this->settings->socialiteConfig(),
            ),
        ]);

        /** @var AbstractProvider|FakeProvider $provider */
        $provider = Socialite::driver('google');

        if ($provider instanceof FakeProvider) {
            return $provider;
        }

        $provider->setHttpClient(new Client([
            'verify' => OutboundHttpCaBundle::guzzleVerifyOption(),
            'timeout' => 30,
            'connect_timeout' => 10,
        ]));

        return $provider->scopes(['openid', 'email', 'profile']);
    }
}
