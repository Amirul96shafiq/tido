<?php

declare(strict_types=1);

use App\Filament\Pages\OllamaPage;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function fakeOllamaTagsResponse(): array
{
    return [
        'models' => [
            [
                'name' => 'qwen2.5vl:7b',
                'model' => 'qwen2.5vl:7b',
                'size' => 5_969_245_856,
                'details' => [
                    'family' => 'qwen25vl',
                    'parameter_size' => '8.3B',
                    'quantization_level' => 'Q4_K_M',
                    'context_length' => 128000,
                ],
            ],
        ],
    ];
}

beforeEach(function (): void {
    config([
        'services.ollama.host' => 'http://ollama.test',
        'services.ollama.model' => 'qwen2.5vl:7b',
        'services.ollama.timeout' => 120,
        'services.ollama.num_ctx' => 8192,
        'services.ollama.max_image_dimension' => 1280,
        'services.documents.pdfinfo_binary' => 'pdfinfo',
        'services.documents.pdftocairo_binary' => 'pdftocairo',
        'services.documents.pdftotext_binary' => 'pdftotext',
    ]);

    Http::fake([
        'http://ollama.test/api/tags' => Http::response(fakeOllamaTagsResponse()),
    ]);

    $this->actingAs(User::factory()->create());
});

test('ollama page is accessible by primary user', function (): void {
    $this->get(OllamaPage::getUrl())
        ->assertSuccessful();
});

test('ollama page shows status and configured model', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('Operational')
        ->assertSee('qwen2.5vl:7b')
        ->assertSee('Configured model');
});

test('ollama page lists available models', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('qwen2.5vl:7b')
        ->assertSee('qwen25vl')
        ->assertSee('8.3B')
        ->assertSee('Q4_K_M');
});

test('ollama page marks configured model as active', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('Active');
});

test('ollama page shows model count in status message', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('Your Ollama instance is connected and ready. 1 model is installed and available for receipt parsing')
        ->assertSee('run a test extraction anytime to confirm OCR and structured output.');
});

test('ollama page shows degraded status when no models installed', function (): void {
    Livewire::test(OllamaPage::class)
        ->set('connectionStatus', 'degraded')
        ->set('statusMessage', 'Ollama is reachable but no models are installed.')
        ->set('availableModels', [])
        ->assertSee('Degraded')
        ->assertSee('no models are installed', false);
});

test('ollama page shows down status when ollama is unreachable', function (): void {
    Http::fake([
        'http://ollama.test/api/tags' => Http::response(null, 500),
    ]);

    Livewire::test(OllamaPage::class)
        ->assertSee('Down');
});

test('ollama page refresh action re-fetches status and notifies', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertActionVisible('refresh')
        ->callAction('refresh')
        ->assertNotified('Status refreshed');
});

test('ollama page shows view details cta', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('View details');
});

test('ollama page shows pipeline, models, and activity sections', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('Pipeline readiness')
        ->assertSee('Models')
        ->assertSee('Receipt activity')
        ->assertSee('Current use in tido')
        ->assertSee('Also suitable for')
        ->assertSee('#ollama-pipeline', false)
        ->assertSee('#ollama-models', false)
        ->assertSee('#ollama-activity', false)
        ->assertSee('Suitable for structured extraction workflows that need consistent JSON output.');
});

test('ollama page renders sticky section nav markers', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-section-nav', false);
});

test('ollama page section nav items match helper', function (): void {
    expect(OllamaPage::sectionNavItems())->toBe([
        ['label' => 'Status', 'id' => 'ollama-status'],
        ['label' => 'Pipeline', 'id' => 'ollama-pipeline'],
        ['label' => 'Models', 'id' => 'ollama-models'],
        ['label' => 'Activity', 'id' => 'ollama-activity'],
    ]);
});

test('ollama page section nav smooth scrolls on tab click', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false);
});

test('ollama page shows receipt activity stats from stored expenses', function (): void {
    Expense::withoutEvents(function (): void {
        Expense::factory()->create([
            'merchant_name' => 'PDF Grocer',
            'source' => 'whatsapp',
            'status' => 'parsed',
            'file_mime_type' => 'application/pdf',
        ]);

        Expense::factory()->create([
            'merchant_name' => 'Image Mart',
            'source' => 'manual',
            'status' => 'reviewed',
            'file_mime_type' => 'image/jpeg',
        ]);

        Expense::factory()->create([
            'merchant_name' => 'Text Expense',
            'source' => 'whatsapp',
            'status' => 'requires_manual_review',
            'file_mime_type' => null,
        ]);
    });

    Livewire::test(OllamaPage::class)
        ->assertSee('Recent receipt activity')
        ->assertSee('Latest processed receipt updated')
        ->assertSee('PDF receipts')
        ->assertSee('Image receipts')
        ->assertSee('Text-only receipts');
});

test('family member cannot access ollama page', function (): void {
    $familyMember = FamilyMember::factory()->loginEnabled()->create();
    $familyMemberUser = User::query()
        ->where('family_member_id', $familyMember->getKey())
        ->firstOrFail();

    $this->actingAs($familyMemberUser);

    expect(OllamaPage::canAccess())->toBeFalse()
        ->and(OllamaPage::shouldRegisterNavigation())->toBeFalse();

    $this->get(OllamaPage::getUrl())
        ->assertRedirect();
});

test('ollama page is in integrations navigation group', function (): void {
    expect(OllamaPage::getNavigationGroup())->toBe('Integrations')
        ->and(OllamaPage::getNavigationSort())->toBe(10);
});
