<?php

declare(strict_types=1);

use App\View\Components\ButtonComponent;
use Filament\Support\Colors\Color;
use Tests\TestCase;

uses(TestCase::class);

test('primary solid buttons use dark primary text in dark mode', function () {
    $map = (new ButtonComponent(isOutlined: false))
        ->getColorMap(Color::hex('#FFD07D'));

    expect($map['text'])->toBeGreaterThanOrEqual(800)
        ->and($map['dark:text'])->toBe(950)
        ->and($map['dark:hover:text'])->toBe($map['hover:text'])
        ->and($map['dark:bg'])->toBe($map['bg'])
        ->and($map['dark:hover:bg'])->toBe($map['hover:bg']);
});

test('danger solid buttons keep white text in dark mode', function () {
    $map = (new ButtonComponent(isOutlined: false))
        ->getColorMap(Color::Red);

    expect($map['dark:text'])->toBe(0);
});
