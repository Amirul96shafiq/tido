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

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee(IntegrationNavigation::WHATSAPP, false)
        ->assertSee(IntegrationNavigation::AI_PARSING_ENGINE, false)
        ->assertSee('Ollama (Local)', false)
        ->assertSee('fi-sidebar-item-flyout', false)
        ->assertSee('fi-sidebar-item-has-children', false)
        ->assertSee('data-tido-child-paths', false)
        ->assertSee('/admin/evolution-api', false);
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
