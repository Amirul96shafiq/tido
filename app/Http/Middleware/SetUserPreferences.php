<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserDateFormat;
use App\Models\User;
use Closure;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserPreferences
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            config([
                'app.timezone' => $user->preferredTimezone(),
                'app.date_format' => $user->preferredDateFormat(),
                'app.datetime_format' => $user->preferredDateTimeFormat(),
            ]);
            app()->setLocale($user->preferredLocale());
            FilamentTimezone::set($user->preferredTimezone());
        } else {
            config([
                'app.date_format' => UserDateFormat::DmySlash->value,
                'app.datetime_format' => UserDateFormat::DmySlash->value.' H:i',
            ]);
        }

        return $next($request);
    }
}
