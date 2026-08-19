<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Filament\Support\IntegrationNavigation;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('primary household sees integration flyout parents in the sidebar', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee(IntegrationNavigation::WHATSAPP, false)
        ->assertSee(IntegrationNavigation::AI_PARSING_ENGINE, false)
        ->assertSee('Ollama (Local)', false)
        ->assertSee('fi-sidebar-item-flyout', false)
        ->assertSee('fi-sidebar-item-has-children', false);
});

test('family members do not see integration flyout parents', function (): void {
    $familyMember = FamilyMember::factory()->loginEnabled()->create();
    $familyMemberUser = User::query()
        ->where('family_member_id', $familyMember->getKey())
        ->firstOrFail();

    $this->actingAs($familyMemberUser);

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertDontSee(IntegrationNavigation::AI_PARSING_ENGINE, false)
        ->assertDontSee('Ollama (Local)', false)
        ->assertDontSee('fi-sidebar-item-has-children', false);
});
