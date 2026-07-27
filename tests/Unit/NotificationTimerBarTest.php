<?php

declare(strict_types=1);

use App\Filament\Notifications\Notification as AppNotification;
use Filament\Notifications\Notification;
use Tests\TestCase;

uses(TestCase::class);

test('timed toast html includes left-to-right timer bar', function () {
    $html = Notification::make()
        ->title('Saved successfully')
        ->success()
        ->duration(5000)
        ->toEmbeddedHtml();

    expect($html)
        ->toContain('tido-no-timer')
        ->toContain('tido-no-timer-bar')
        ->toContain('--tido-no-duration: 5000ms')
        ->and(Notification::make())->toBeInstanceOf(AppNotification::class);
});

test('default duration toast uses six second timer', function () {
    $html = Notification::make()
        ->title('Saved successfully')
        ->toEmbeddedHtml();

    expect($html)->toContain('--tido-no-duration: 6000ms');
});

test('persistent toast omits timer bar', function () {
    $html = Notification::make()
        ->title('Unsaved draft found')
        ->persistent()
        ->toEmbeddedHtml();

    expect($html)
        ->not->toContain('tido-no-timer')
        ->not->toContain('tido-no-timer-bar');
});

test('inline toast omits timer bar', function () {
    $html = Notification::make()
        ->title('Database notification')
        ->duration(4000)
        ->inline()
        ->toEmbeddedHtml();

    expect($html)
        ->not->toContain('tido-no-timer')
        ->not->toContain('tido-no-timer-bar');
});
