<?php

declare(strict_types=1);

use App\Enums\UserDateFormat;
use Tests\TestCase;

uses(TestCase::class);

test('date format options use the pattern as the label', function () {
    expect(UserDateFormat::options())->toBe([
        'd/m/Y' => 'd/m/Y',
        'd M Y' => 'd M Y',
        'Y-m-d' => 'Y-m-d',
    ]);
});

test('date format descriptions show today in each pattern', function () {
    $this->travelTo('2026-08-14 09:00:00');

    expect(UserDateFormat::descriptions())->toBe([
        'd/m/Y' => '14/08/2026',
        'd M Y' => '14 Aug 2026',
        'Y-m-d' => '2026-08-14',
    ]);
});
