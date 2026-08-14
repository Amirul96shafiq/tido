<?php

declare(strict_types=1);

use App\Enums\UserDateFormat;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo('2026-08-14 09:00:00');
});

test('profile date format uses inline radio options with today as the description', function () {
    $html = Livewire::test(EditProfile::class)
        ->assertSuccessful()
        ->assertSee('Date Format')
        ->assertSee('d/m/Y')
        ->assertSee('d M Y')
        ->assertSee('Y-m-d')
        ->assertSee('14/08/2026')
        ->assertSee('14 Aug 2026')
        ->assertSee('2026-08-14')
        ->html();

    expect($html)
        ->toContain('fi-fo-radio')
        ->toContain('fi-inline')
        ->toContain('fi-fo-radio-label-description')
        ->toContain('type="radio"')
        ->toContain('value="'.UserDateFormat::DmySlash->value.'"')
        ->toContain('value="'.UserDateFormat::DmyLong->value.'"')
        ->toContain('value="'.UserDateFormat::Iso->value.'"')
        ->not->toContain('09/07/2026 (d/m/Y)');
});

test('profile date format radio persists the selected pattern', function () {
    $user = User::factory()->create([
        'date_format' => UserDateFormat::DmySlash->value,
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.date_format', UserDateFormat::Iso->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->date_format)->toBe(UserDateFormat::Iso->value);
});
