<?php

declare(strict_types=1);

use App\Filament\Resources\Labels\Pages\CreateLabel;
use App\Filament\Resources\Labels\Pages\EditLabel;
use App\Filament\Resources\Labels\Schemas\LabelForm;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('label create page renders sticky section nav markers', function () {
    Livewire::test(CreateLabel::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-sticky-marker--bottom', false)
        ->assertSee('tido-section-nav', false);
});

test('label edit page renders sticky section nav markers', function () {
    $label = Label::factory()->create();

    Livewire::test(EditLabel::class, ['record' => $label->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-section-nav', false);
});

test('label section nav lists anchor tabs', function () {
    Livewire::test(CreateLabel::class)
        ->assertSuccessful()
        ->assertSee('Label Appearance')
        ->assertSee('Label Details')
        ->assertSee('Label Notes')
        ->assertSee('#label-appearance', false)
        ->assertSee('#label-details', false)
        ->assertSee('#label-notes', false);
});

test('label section nav items match sectionNavItems helper', function () {
    expect(LabelForm::sectionNavItems())->toBe([
        ['label' => 'Label Appearance', 'id' => 'label-appearance'],
        ['label' => 'Label Details', 'id' => 'label-details'],
        ['label' => 'Label Notes', 'id' => 'label-notes'],
    ]);
});

test('label section nav smooth scrolls on tab click', function () {
    Livewire::test(CreateLabel::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false);
});
