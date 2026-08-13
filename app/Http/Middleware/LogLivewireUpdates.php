<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogLivewireUpdates
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.debug')) {
            return $next($request);
        }

        $startedAt = microtime(true);

        try {
            return $next($request);
        } finally {
            $durationMilliseconds = (int) round((microtime(true) - $startedAt) * 1000);
            $logger = Log::stack(['single', 'stderr']);

            foreach ($this->componentUpdates($request) as $componentUpdate) {
                $context = [
                    ...$componentUpdate,
                    'duration_ms' => $durationMilliseconds,
                ];

                $logger->info(
                    $this->formatTerminalMessage($componentUpdate, $durationMilliseconds),
                    $context,
                );
            }
        }
    }

    /**
     * @param  array{component: string, actions: list<string>, updated_properties: int}  $componentUpdate
     */
    protected function formatTerminalMessage(array $componentUpdate, int $durationMilliseconds): string
    {
        $actions = $componentUpdate['actions'] === []
            ? 'none'
            : implode(',', $componentUpdate['actions']);

        return sprintf(
            'Livewire update component=%s actions=%s updated_properties=%d duration_ms=%d',
            $componentUpdate['component'],
            $actions,
            $componentUpdate['updated_properties'],
            $durationMilliseconds,
        );
    }

    /**
     * @return list<array{component: string, actions: list<string>, updated_properties: int}>
     */
    protected function componentUpdates(Request $request): array
    {
        $components = $request->input('components', []);

        if (! is_array($components)) {
            return [];
        }

        $updates = [];

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $snapshot = json_decode((string) ($component['snapshot'] ?? ''), true);
            $componentName = is_array($snapshot) && is_string(data_get($snapshot, 'memo.name'))
                ? data_get($snapshot, 'memo.name')
                : 'unknown';
            $calls = is_array($component['calls'] ?? null) ? $component['calls'] : [];
            $actions = [];

            foreach ($calls as $call) {
                $method = is_array($call) ? $call['method'] ?? null : null;

                if (is_string($method) && $method !== '') {
                    $actions[] = $method;
                }
            }

            $updatedProperties = is_array($component['updates'] ?? null)
                ? count($component['updates'])
                : 0;

            $updates[] = [
                'component' => $componentName,
                'actions' => array_values(array_unique($actions)),
                'updated_properties' => $updatedProperties,
            ];
        }

        return $updates;
    }
}
