<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Services\SignupGreeting\VisitorCountryResolver;
use App\Support\SignInWelcomeCopy;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

final class SignInWelcomeHeading
{
    private const HOLD_MS = 4500;

    public static function html(?string $countryCode = null): Htmlable
    {
        $countryCode ??= app(VisitorCountryResolver::class)->resolve();
        $phrases = SignInWelcomeCopy::typewriterPhrases($countryCode);

        if ($phrases === null) {
            return new HtmlString(e(SignInWelcomeCopy::ENGLISH));
        }

        return new HtmlString(
            (string) view('filament.support.signup-greeting-heading', [
                'englishPhrase' => SignInWelcomeCopy::ENGLISH,
                'wireKey' => 'tido-signin-welcome',
                'phrases' => $phrases,
                'holdMs' => self::HOLD_MS,
            ]),
        );
    }
}
