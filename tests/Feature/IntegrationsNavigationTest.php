<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\EvolutionApiPage;
use App\Filament\Pages\OllamaPage;
use App\Filament\Support\IntegrationNavigation;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

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
        ->toContain("document.body")
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
        );
});

test('family members do not see integration flyout parents', function (): void {
    $familyMember = FamilyMember::factory()->loginEnabled()->create();
    $familyMemberUser = User::query()
        ->where('family_member_id', $familyMember->getKey())
        ->firstOrFail();

    $this->actingAs($familyMemberUser);

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertDontSee(IntegrationNavigation::WHATSAPP, false)
        ->assertDontSee(IntegrationNavigation::AI_PARSING_ENGINE, false)
        ->assertDontSee('Ollama (Local)', false)
        ->assertDontSee('fi-sidebar-item-flyout', false);
});
