<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureWhatsAppWebhookSource
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = self::allowedIps();

        if ($allowed === []) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $clientIp = (string) $request->ip();

        if (! in_array($clientIp, $allowed, true)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }

    /**
     * @return list<string>
     */
    public static function allowedIps(): array
    {
        $raw = trim((string) config('services.evolution.webhook_allowed_ips', ''));

        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $ips = [];

        foreach ($parts as $part) {
            $ip = trim($part);

            if ($ip !== '') {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }
}
