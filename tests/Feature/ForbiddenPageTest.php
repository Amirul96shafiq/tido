<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Forbidden;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('guest can open forbidden page', function (): void {
    $this->get(route('filament.admin.auth.forbidden'))
        ->assertSuccessful()
        ->assertSee('Access Denied', false)
        ->assertSee('Return to Login', false)
        ->assertSee('fi-simple-layout', false)
        ->assertSee('fi-auth-menu', false)
        ->assertSee('fi-theme-switcher', false);
});

test('forbidden page shows heading and guest cta', function (): void {
    Livewire::test(Forbidden::class)
        ->assertSee('Access Denied')
        ->assertSee('Return to Login')
        ->assertSee('Access to this page is not permitted');
});

test('authenticated forbidden page shows return to home cta', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(Forbidden::class)
        ->assertSee('Access Denied')
        ->assertSee('Return to Home')
        ->assertDontSee('Return to Login');
});

test('forbidden page hides filament simple user menu topbar', function (): void {
    $page = Livewire::test(Forbidden::class);

    expect($page->instance()->hasTopbar())->toBeFalse();

    $html = $this->actingAs(User::factory()->create())
        ->get(route('filament.admin.auth.forbidden'))
        ->assertSuccessful()
        ->assertSee('fi-auth-menu', false)
        ->getContent();

    expect($html)
        ->not->toContain('class="fi-simple-layout-header"')
        ->not->toContain('class="fi-simple-user-menu-ctn"')
        ->not->toContain('>User menu<');
});

test('html forbidden responses redirect to forbidden page', function (): void {
    Route::middleware('web')->get('/__test-forbidden', function () {
        abort(403);
    });

    $this->get('/__test-forbidden')
        ->assertRedirect(route('filament.admin.auth.forbidden'));

    $this->get(route('filament.admin.auth.forbidden'))
        ->assertSuccessful()
        ->assertSee('Access Denied', false)
        ->assertSee('fi-auth-menu', false);
});

test('api forbidden responses remain json', function (): void {
    Route::middleware('api')->get('/api/__test-forbidden', function () {
        abort(403);
    });

    $this->getJson('/api/__test-forbidden')
        ->assertForbidden()
        ->assertJsonStructure(['message'])
        ->assertHeaderMissing('Location');
});

test('guest auth pages show auth menu including forbidden', function (): void {
    $this->get(route('filament.admin.auth.forbidden'))
        ->assertSuccessful()
        ->assertSee('fi-auth-menu', false)
        ->assertSee('fi-theme-switcher', false)
        ->assertSee('Changelogs 🡥');
});
