<?php

declare(strict_types=1);

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use App\Filament\Pages\ServiceStatusPage;
use App\Models\FamilyMember;
use App\Models\ServiceHealthSample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.ollama.host' => 'http://ollama.test',
        'services.evolution.api_url' => 'http://evolution.test',
        'services.evolution.api_key' => 'test-evolution-api-key-0123456789abcdef0123456789abcdef',
        'services.evolution.instance_name' => 'tido',
        'broadcasting.default' => 'null',
    ]);

    $this->actingAs(User::factory()->create());
});

test('service status page renders summary banner and uptime labels', function (): void {
    ServiceHealthSample::query()->create([
        'service' => MonitoredService::Ollama,
        'status' => ServiceHealthStatus::Operational,
        'checked_at' => now(),
        'meta' => ['message' => 'Healthy'],
    ]);

    Livewire::test(ServiceStatusPage::class)
        ->assertSee('Summary Report (', false)
        ->assertSee('Status (', false)
        ->assertDontSee('Last 30 days', false)
        ->assertSee('Monitored services')
        ->assertSee('Ollama')
        ->assertSee('uptime')
        ->assertSee('grid-cols-3', false)
        ->assertSee('lg:items-start', false)
        ->assertSee('fi-service-status-summary-sticky', false)
        ->assertSee('allowHTML: true', false)
        ->assertSee('data-tippy-mobile', false)
        ->assertSee('x-tooltip', false);
});

test('service status page lists reverb when broadcasting uses reverb', function (): void {
    config(['broadcasting.default' => 'reverb']);

    Livewire::test(ServiceStatusPage::class)
        ->assertSee('Reverb')
        ->assertSee('Ollama');
});

test('service status page run check now records samples', function (): void {
    Http::fake([
        'http://ollama.test/api/tags' => Http::response(['models' => []]),
        'http://evolution.test/instance/connectionState/tido' => Http::response([
            'instance' => ['state' => 'open'],
        ]),
    ]);

    expect(ServiceHealthSample::query()->count())->toBe(0);

    Livewire::test(ServiceStatusPage::class)
        ->assertActionVisible('runCheck')
        ->callAction('runCheck')
        ->assertNotified();

    expect(ServiceHealthSample::query()->count())->toBeGreaterThan(0);
});

test('service status page is available in tools navigation', function (): void {
    $this->get(ServiceStatusPage::getUrl())
        ->assertSuccessful();
});

test('family member can navigate to and view service status', function (): void {
    $familyMember = FamilyMember::factory()->loginEnabled()->create();
    $familyMemberUser = User::query()
        ->where('family_member_id', $familyMember->getKey())
        ->firstOrFail();

    $this->actingAs($familyMemberUser);

    expect(ServiceStatusPage::canAccess())->toBeTrue()
        ->and(ServiceStatusPage::shouldRegisterNavigation())->toBeTrue();

    $this->get(ServiceStatusPage::getUrl())
        ->assertSuccessful()
        ->assertSee('Service Status');

    Livewire::test(ServiceStatusPage::class)
        ->assertSee('Summary Report')
        ->assertSee('Status')
        ->assertActionVisible('runCheck')
        ->assertActionDisabled('runCheck')
        ->assertSee('Only the Primary member can access this page.', false)
        ->assertSee('tido-primary-only-action', false);
});
