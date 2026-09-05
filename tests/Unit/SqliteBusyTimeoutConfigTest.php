<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

test('sqlite busy timeout is at least 15 seconds for concurrent local writers', function (): void {
    expect((int) config('database.connections.sqlite.busy_timeout'))
        ->toBeGreaterThanOrEqual(15000);
});
