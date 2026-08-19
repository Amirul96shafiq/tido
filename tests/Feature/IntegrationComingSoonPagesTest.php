<?php

declare(strict_types=1);

use App\Filament\Pages\GeminiPage;
use App\Filament\Pages\OpenAiPage;
use App\Filament\Pages\WhatsAppOfficialApiPage;
use App\Filament\Support\IntegrationNavigation;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

/**
 * @return array<string, array{0: class-string, 1: string, 2: string, 3: int, 4: string}>
 */
dataset('comingSoonIntegrationPages', [
    'official api' => [
        WhatsAppOfficialApiPage::class,
        IntegrationNavigation::WHATSAPP,
        'Official API',
        20,
        'The WhatsApp Official API is not available as a messaging integration yet.',
    ],
    'gemini' => [
        GeminiPage::class,
        IntegrationNavigation::AI_PARSING_ENGINE,
        'Gemini',
        10,
        'Google Gemini is not available as a parsing engine yet.',
    ],
    'openai' => [
        OpenAiPage::class,
        IntegrationNavigation::AI_PARSING_ENGINE,
        'OpenAI',
        30,
        'OpenAI is not available as a parsing engine yet.',
    ],
]);

test('coming soon integration pages render for the primary household', function (string $page, string $parent, string $label, int $sort, string $description): void {
    expect($page::getNavigationGroup())->toBe(IntegrationNavigation::GROUP)
        ->and($page::getNavigationParentItem())->toBe($parent)
        ->and($page::getNavigationLabel())->toBe($label)
        ->and($page::getNavigationSort())->toBe($sort)
        ->and($page::getNavigationBadge())->toBe('Coming soon')
        ->and($page::canAccess())->toBeTrue();

    Livewire::test($page)
        ->assertSuccessful()
        ->assertSee('Coming soon')
        ->assertSee($description, false);
})->with('comingSoonIntegrationPages');

test('family members cannot access coming soon integration pages', function (string $page, string $_parent, string $_label, int $_sort, string $_description): void {
    $familyMember = FamilyMember::factory()->loginEnabled()->create();
    $familyMemberUser = User::query()
        ->where('family_member_id', $familyMember->getKey())
        ->firstOrFail();

    $this->actingAs($familyMemberUser);

    expect($page::canAccess())->toBeFalse()
        ->and($page::shouldRegisterNavigation())->toBeFalse();

    $this->get($page::getUrl())
        ->assertRedirect();
})->with('comingSoonIntegrationPages');
