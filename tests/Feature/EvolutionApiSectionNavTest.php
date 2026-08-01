<?php

declare(strict_types=1);

use App\Filament\Pages\EvolutionApiPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('evolution api page renders sticky section nav markers', function () {
    Livewire::test(EvolutionApiPage::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-section-nav', false);
});

test('evolution api section nav lists anchor tabs', function () {
    Livewire::test(EvolutionApiPage::class)
        ->assertSuccessful()
        ->assertSee('Link device')
        ->assertSee('Connection')
        ->assertSee('WhatsApp LID')
        ->assertSee('Connection history')
        ->assertSee('#evolution-link-device', false)
        ->assertSee('#evolution-connection', false)
        ->assertSee('#evolution-whatsapp-lid', false)
        ->assertSee('#evolution-connection-history', false);
});

test('evolution api section nav items match sectionNavItems helper', function () {
    expect(EvolutionApiPage::sectionNavItems())->toBe([
        ['label' => 'Link device', 'id' => 'evolution-link-device'],
        ['label' => 'Connection', 'id' => 'evolution-connection'],
        ['label' => 'WhatsApp LID', 'id' => 'evolution-whatsapp-lid'],
        ['label' => 'Connection history', 'id' => 'evolution-connection-history'],
    ]);
});

test('evolution api section nav smooth scrolls on tab click', function () {
    Livewire::test(EvolutionApiPage::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false);
});
