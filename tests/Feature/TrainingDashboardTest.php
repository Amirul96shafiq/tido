<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\TrainingDashboard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('training dashboard renders empty coming soon panel and view tabs', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'name' => 'Ada',
            'display_name' => 'Ada',
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    $trainingHtml = $this->get(TrainingDashboard::getUrl())
        ->assertSuccessful()
        ->assertSee('tido-dashboard-view-tabs', false)
        ->assertSee('Finances', false)
        ->assertSee('Training', false)
        ->assertSee(Dashboard::getUrl(), false)
        ->assertSee('training-overview', false)
        ->assertSee('Coming soon', false)
        ->assertSee('Training dashboard is not available yet', false)
        ->assertSee('Good Morning, <span class="text-primary-600 dark:text-primary-400">Ada</span> ☀️', false)
        ->getContent();

    expect($trainingHtml)->toMatch('/\/admin\/training"[^>]*class="fi-tabs-item fi-active"/');

    Livewire::test(TrainingDashboard::class)
        ->assertSee('Coming soon', false)
        ->assertSee('Training dashboard is not available yet', false);
});

afterEach(function () {
    Carbon::setTestNow();
});
