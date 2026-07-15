<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);
});

test('dashboard heading and subheading reflect morning in user timezone', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00', 'America/New_York'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'name' => 'Ada',
            'timezone' => 'America/New_York',
        ]);

    $this->actingAs($user);

    $page = Livewire::test(Dashboard::class);

    expect((string) $page->instance()->getHeading())
        ->toContain('Good Morning, <span class="text-primary-600 dark:text-primary-400">Ada</span> ☀️')
        ->and($page->instance()->getSubheading())
        ->toBe('Ready to start the day? Start by tidying up your files, then get it done.');

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee('Good Morning, <span class="text-primary-600 dark:text-primary-400">Ada</span> ☀️', false)
        ->assertSee('Ready to start the day? Start by tidying up your files, then get it done.', false)
        ->assertSee('tido-dashboard-greeting', false);
});

test('dashboard greeting uses afternoon copy when local hour is midday', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 14:00:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'name' => 'Budi',
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSee('Good Afternoon, <span class="text-primary-600 dark:text-primary-400">Budi</span> 🌤️', false)
        ->assertSee('Ready to keep going? Start by tidying up your files, then get it done.', false);
});

test('dashboard greeting uses evening copy when local hour is late night', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 22:30:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'name' => 'Citra',
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSee('Good Evening, <span class="text-primary-600 dark:text-primary-400">Citra</span> 🌙', false)
        ->assertSee('Ready to wrap up? Start by tidying up your files, then get it done.', false);
});

test('dashboard greeting shortens long user names in heading', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 22:30:00', 'Asia/Kuala_Lumpur'));

    $user = User::factory()
        ->withWhatsAppPhone('60123456789')
        ->create([
            'name' => 'Amirul Shafiq Harun',
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSee('Good Evening, <span class="text-primary-600 dark:text-primary-400">Amirul S. H.</span> 🌙', false);
});

afterEach(function () {
    Carbon::setTestNow();
});
