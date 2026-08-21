<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureWhatsAppWebhookBodySize
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maxBytes = max(1, (int) config('services.evolution.webhook_max_body_bytes', 262144));

        $contentLength = $request->headers->get('Content-Length');

        if (is_numeric($contentLength) && (int) $contentLength > $maxBytes) {
            return response()->json(['error' => 'Payload too large'], 413);
        }

        if (strlen($request->getContent()) > $maxBytes) {
            return response()->json(['error' => 'Payload too large'], 413);
        }

        return $next($request);
    }
}
