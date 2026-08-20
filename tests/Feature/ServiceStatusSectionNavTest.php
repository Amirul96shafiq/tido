<?php

declare(strict_types=1);

use App\Filament\Pages\ServiceStatusPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('service status page renders sticky section nav markers', function () {
    Livewire::test(ServiceStatusPage::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-section-nav', false);
});

test('service status section nav lists anchor tabs', function () {
    Livewire::test(ServiceStatusPage::class)
        ->assertSuccessful()
        ->assertSee('Summary Report')
        ->assertSee('Status')
        ->assertSee('#service-summary-report', false)
        ->assertSee('#service-status', false);
});

test('service status section nav items match sectionNavItems helper', function () {
    expect(ServiceStatusPage::sectionNavItems())->toBe([
        ['label' => 'Summary Report', 'id' => 'service-summary-report'],
        ['label' => 'Status', 'id' => 'service-status'],
    ]);
});

test('service status section nav smooth scrolls on tab click', function () {
    Livewire::test(ServiceStatusPage::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false);
});
