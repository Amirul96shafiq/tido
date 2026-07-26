<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('dashboard switches to coming soon views without leaving the page', function (string $view, string $overviewId, string $message) {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'name' => 'Ada',
            'display_name' => 'Ada',
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSet('dashboardView', Dashboard::VIEW_FINANCES)
        ->assertDontSee('Coming soon', false)
        ->assertSee('tido-dashboard-sticky-toolbar', false)
        ->call('setDashboardView', $view)
        ->assertSet('dashboardView', $view)
        ->assertSee($overviewId, false)
        ->assertSee('Coming soon', false)
        ->assertSee($message, false)
        ->assertDontSee('tido-dashboard-sticky-toolbar', false)
        ->call('setDashboardView', Dashboard::VIEW_FINANCES)
        ->assertSet('dashboardView', Dashboard::VIEW_FINANCES)
        ->assertSee('tido-dashboard-sticky-toolbar', false)
        ->assertDontSee('Coming soon', false);
})->with([
    'training' => [
        Dashboard::VIEW_TRAINING,
        'training-overview',
        'Training dashboard is not available yet',
    ],
    'health' => [
        Dashboard::VIEW_HEALTH,
        'health-overview',
        'Health dashboard is not available yet',
    ],
    'task' => [
        Dashboard::VIEW_TASK,
        'task-overview',
        'Task dashboard is not available yet',
    ],
]);

test('dashboard coming soon views are reachable via query string', function (string $view, string $overviewId) {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'name' => 'Ada',
            'display_name' => 'Ada',
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    $this->get(Dashboard::getUrl().'?view='.$view)
        ->assertSuccessful()
        ->assertSee('tido-dashboard-view-tabs', false)
        ->assertSee($overviewId, false)
        ->assertSee('Coming soon', false)
        ->assertSee('Good Morning, <span class="text-primary-600 dark:text-primary-400">Ada</span> ☀️', false);
})->with([
    'training' => [Dashboard::VIEW_TRAINING, 'training-overview'],
    'health' => [Dashboard::VIEW_HEALTH, 'health-overview'],
    'task' => [Dashboard::VIEW_TASK, 'task-overview'],
]);

test('dashboard ignores invalid dashboard view values', function () {
    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create();

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->call('setDashboardView', 'invalid')
        ->assertSet('dashboardView', Dashboard::VIEW_FINANCES);
});

afterEach(function () {
    Carbon::setTestNow();
});
