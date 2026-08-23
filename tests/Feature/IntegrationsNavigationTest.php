<?php

declare(strict_types=1);

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\EvolutionApiPage;
use App\Filament\Pages\OllamaPage;
use App\Filament\Support\IntegrationHealthBadge;
use App\Filament\Support\IntegrationNavigation;
use App\Models\FamilyMember;
use App\Models\ServiceHealthSample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('primary household sees integration flyout parents in the sidebar', function (): void {
    $this->actingAs(User::factory()->create());

    $html = $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee(IntegrationNavigation::WHATSAPP, false)
        ->assertSee(IntegrationNavigation::AI_PARSING_ENGINE, false)
        ->assertSee('Ollama (Local)', false)
        ->assertSee('fi-sidebar-item-flyout', false)
        ->assertSee('fi-sidebar-item-has-children', false)
        ->assertSee('data-tido-child-paths', false)
        ->assertSee('/admin/evolution-api', false)
        ->getContent();

    expect($html)
        ->toContain('M13.601 2.326A7.85')
        ->toContain('M6.897 4c1.915')
        ->toContain('M20.616 10.835')
        ->toContain('M9.205 8.658v-2.26');
});

test('evolution api marks the whatsapp parent and flyout child as active', function (): void {
    $this->actingAs(User::factory()->create());

    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    $html = $this->get(EvolutionApiPage::getUrl())
        ->assertSuccessful()
        ->getContent();

    expect($html)
        ->toContain('fi-sidebar-item fi-active fi-sidebar-item-has-active-child-items')
        ->toContain('aria-current="page"')
        ->and(substr_count($html, 'fi-sidebar-item fi-active fi-sidebar-item-has-active-child-items'))->toBe(1);
});

test('ollama marks the ai parsing engine parent and flyout child as active', function (): void {
    $this->actingAs(User::factory()->create());

    config([
        'services.ollama.host' => 'http://ollama.test',
    ]);

    Http::fake([
        'http://ollama.test/api/tags' => Http::response(['models' => []]),
    ]);

    $html = $this->get(OllamaPage::getUrl())
        ->assertSuccessful()
        ->getContent();

    expect($html)
        ->toContain('fi-sidebar-item fi-active fi-sidebar-item-has-active-child-items')
        ->toContain('aria-current="page"')
        ->and(substr_count($html, 'fi-sidebar-item fi-active fi-sidebar-item-has-active-child-items'))->toBe(1);
});

test('small sidebar flyouts close sibling integration menus', function (): void {
    $this->actingAs(User::factory()->create());

    $html = $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->getContent();

    expect($html)
        ->toContain('tido-sidebar-flyout-exclusive')
        ->toContain("window.matchMedia('(min-width: 1024px)').matches")
        ->and(substr_count($html, 'tido-sidebar-flyout-exclusive.window'))->toBe(2);
});

test('small sidebar flyouts clamp to the viewport', function (): void {
    $this->actingAs(User::factory()->create());

    $html = $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->getContent();

    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($html)
        ->toContain('tidoClampSidebarFlyout')
        ->toContain('document.body')
        ->toContain("\$watch('\$store.sidebar.isOpen'")
        ->toContain("document.addEventListener('pointerdown'")
        ->toContain('trigger.getBoundingClientRect')
        ->toContain("! \$store.sidebar.isOpen && window.matchMedia('(min-width: 1024px)').matches")
        ->and($css)->toContain('.tido-sidebar-flyout-panel')
        ->and($css)->toContain('max-width: min(14rem, calc(100vw - 1rem))');
});

test('integration flyout parent labels are left aligned', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain(
            <<<'CSS'
.fi-sidebar-item-flyout .fi-sidebar-item-btn {
    width: 100%;
    /* Buttons default to text-align: center; keep labels flush with the icon. */
    justify-content: flex-start;
    text-align: start;
}

.fi-sidebar-item-flyout .fi-sidebar-item-label {
    text-align: start;
}
CSS
        )
        ->and($css)->toContain('.tido-sidebar-flyout-panel .fi-dropdown-list-item .fi-badge.fi-color-success');
});

test('operational health samples show Active pills on live integration children', function (): void {
    $this->actingAs(User::factory()->create());

    ServiceHealthSample::query()->create([
        'service' => MonitoredService::Ollama,
        'status' => ServiceHealthStatus::Operational,
        'checked_at' => now(),
        'latency_ms' => 12,
        'meta' => ['message' => 'Ollama is reachable.'],
    ]);

    ServiceHealthSample::query()->create([
        'service' => MonitoredService::Evolution,
        'status' => ServiceHealthStatus::Operational,
        'checked_at' => now(),
        'latency_ms' => 18,
        'meta' => ['message' => 'WhatsApp session is connected.'],
    ]);

    expect(OllamaPage::getNavigationBadge())->toBe(IntegrationHealthBadge::LABEL)
        ->and(OllamaPage::getNavigationBadgeColor())->toBe('success')
        ->and(EvolutionApiPage::getNavigationBadge())->toBe(IntegrationHealthBadge::LABEL)
        ->and(EvolutionApiPage::getNavigationBadgeColor())->toBe('success');

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee(IntegrationHealthBadge::LABEL, false)
        ->assertSee('Coming soon', false);
});

test('non-operational health samples do not show Active pills', function (ServiceHealthStatus $status): void {
    $this->actingAs(User::factory()->create());

    ServiceHealthSample::query()->create([
        'service' => MonitoredService::Ollama,
        'status' => $status,
        'checked_at' => now(),
        'latency_ms' => 40,
        'meta' => ['message' => 'Ollama is not operational.'],
    ]);

    ServiceHealthSample::query()->create([
        'service' => MonitoredService::Evolution,
        'status' => $status,
        'checked_at' => now(),
        'latency_ms' => 40,
        'meta' => ['message' => 'Evolution is not operational.'],
    ]);

    expect(OllamaPage::getNavigationBadge())->toBeNull()
        ->and(OllamaPage::getNavigationBadgeColor())->toBeNull()
        ->and(EvolutionApiPage::getNavigationBadge())->toBeNull()
        ->and(EvolutionApiPage::getNavigationBadgeColor())->toBeNull();
})->with([
    ServiceHealthStatus::Down,
    ServiceHealthStatus::Degraded,
]);

test('missing health samples do not show Active pills on live integration children', function (): void {
    $this->actingAs(User::factory()->create());

    expect(OllamaPage::getNavigationBadge())->toBeNull()
        ->and(EvolutionApiPage::getNavigationBadge())->toBeNull();
});

test('live ollama down clears a stale Active pill', function (): void {
    $this->actingAs(User::factory()->create());

    ServiceHealthSample::query()->create([
        'service' => MonitoredService::Ollama,
        'status' => ServiceHealthStatus::Operational,
        'checked_at' => now()->subMinutes(15),
        'latency_ms' => 12,
        'meta' => ['message' => 'Previously reachable.'],
    ]);

    config([
        'services.ollama.host' => 'http://ollama.test',
    ]);

    Http::fake([
        'http://ollama.test/api/tags' => Http::failedConnection(),
    ]);

    Livewire::test(OllamaPage::class)
        ->assertSet('connectionStatus', 'down')
        ->assertDispatched('refresh-sidebar');

    expect(OllamaPage::getNavigationBadge())->toBeNull();

    $this->assertDatabaseHas('service_health_samples', [
        'service' => MonitoredService::Ollama->value,
        'status' => ServiceHealthStatus::Down->value,
    ]);
});

test('family members see restricted integration navigation with access tooltip', function (): void {
    $familyMember = FamilyMember::factory()->loginEnabled()->create();
    $familyMemberUser = User::query()
        ->where('family_member_id', $familyMember->getKey())
        ->firstOrFail();

    $this->actingAs($familyMemberUser);

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee(IntegrationNavigation::WHATSAPP, false)
        ->assertSee(IntegrationNavigation::AI_PARSING_ENGINE, false)
        ->assertSee('Ollama (Local)', false)
        ->assertSee('tido-primary-only-navigation', false)
        ->assertSee('Only the Primary member can access this page.', false)
        ->assertDontSeeHtml('href="'.e(EvolutionApiPage::getUrl()).'"')
        ->assertDontSeeHtml('href="'.e(OllamaPage::getUrl()).'"');
});
