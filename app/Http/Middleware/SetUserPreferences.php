<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
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

        if ($user !== null) {
            config(['app.timezone' => $user->timezone ?? 'Asia/Kuala_Lumpur']);
            app()->setLocale($user->preferredLocale());
        }

        return $next($request);
    }
}
