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

test('flash toast css slides in from right and out to right', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.fi-no.fi-align-end .fi-no-notification:not(.fi-inline).fi-transition-enter-start')
        ->toContain('.fi-no.fi-align-end .fi-no-notification:not(.fi-inline).fi-transition-leave-end')
        ->toContain('@apply translate-x-12 opacity-0;')
        ->toContain('@apply translate-x-12 opacity-0 scale-100;');
});

test('flash toast css enables swipe-right dismiss on all breakpoints', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('touch-action: pan-y;')
        ->toContain('.tido-no-swiping')
        ->toContain('.tido-no-settling')
        ->toContain('cursor: grab;')
        ->toContain('cursor: grabbing;');
});

test('notification swipe dismiss is registered as a panel vite asset', function () {
    $vite = file_get_contents(base_path('vite.config.js'));
    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($vite)
        ->toContain('resources/js/notification-swipe-dismiss.js')
        ->and($provider)
        ->toContain("'notification-swipe-dismiss'")
        ->toContain("Vite::asset('resources/js/notification-swipe-dismiss.js')");
});
