<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Services\SignupGreeting\VisitorCountryResolver;
use App\Support\SignupGreetingCopy;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

final class SignupGreetingHeading
{
    private const HOLD_MS = 4500;

    public static function html(?string $countryCode = null): Htmlable
    {
        $countryCode ??= app(VisitorCountryResolver::class)->resolve();
        $phrases = SignupGreetingCopy::typewriterPhrases($countryCode);

        if ($phrases === null) {
            return new HtmlString(e(SignupGreetingCopy::ENGLISH));
        }

        return new HtmlString(
            (string) view('filament.support.signup-greeting-heading', [
                'phrases' => $phrases,
                'holdMs' => self::HOLD_MS,
            ]),
        );
    }
}
