<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\NotFound;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('guest can open not found page', function (): void {
    $this->get(route('filament.admin.auth.not-found'))
        ->assertSuccessful()
        ->assertSee('Page Not Found', false)
        ->assertSee('Return to Login', false)
        ->assertSee('fi-simple-layout', false)
        ->assertSee('fi-auth-menu', false)
        ->assertSee('fi-theme-switcher', false);
});

test('not found page shows heading and guest cta', function (): void {
    Livewire::test(NotFound::class)
        ->assertSee('Page Not Found')
        ->assertSee('Return to Login')
        ->assertSee('requested page could not be found');
});

test('authenticated not found page shows return to home cta', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(NotFound::class)
        ->assertSee('Page Not Found')
        ->assertSee('Return to Home')
        ->assertDontSee('Return to Login');
});

test('not found page hides filament simple user menu topbar', function (): void {
    $page = Livewire::test(NotFound::class);

    expect($page->instance()->hasTopbar())->toBeFalse();

    $html = $this->actingAs(User::factory()->create())
        ->get(route('filament.admin.auth.not-found'))
        ->assertSuccessful()
        ->assertSee('fi-auth-menu', false)
        ->getContent();

    expect($html)
        ->not->toContain('class="fi-simple-layout-header"')
        ->not->toContain('class="fi-simple-user-menu-ctn"')
        ->not->toContain('>User menu<');
});

test('missing html paths redirect to not found page', function (string $path): void {
    $this->get($path)
        ->assertRedirect(route('filament.admin.auth.not-found'));

    $this->get(route('filament.admin.auth.not-found'))
        ->assertSuccessful()
        ->assertSee('Page Not Found', false)
        ->assertSee('fi-auth-menu', false);
})->with([
    '/admin/this-does-not-exist',
    '/nope',
]);

test('api not found responses remain json', function (): void {
    $this->getJson('/api/nope')
        ->assertNotFound()
        ->assertJsonStructure(['message'])
        ->assertHeaderMissing('Location');
});

test('guest auth pages show auth menu including not found', function (): void {
    $this->get(route('filament.admin.auth.not-found'))
        ->assertSuccessful()
        ->assertSee('fi-auth-menu', false)
        ->assertSee('fi-theme-switcher', false)
        ->assertSee('Changelogs 🡥');
});
