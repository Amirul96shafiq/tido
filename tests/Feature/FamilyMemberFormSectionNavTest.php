<?php

declare(strict_types=1);

use App\Filament\Resources\FamilyMembers\Pages\CreateFamilyMember;
use App\Filament\Resources\FamilyMembers\Pages\EditFamilyMember;
use App\Filament\Resources\FamilyMembers\Schemas\FamilyMemberForm;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('family member create page renders sticky section nav markers', function () {
    Livewire::test(CreateFamilyMember::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-sticky-marker--bottom', false)
        ->assertSee('tido-section-nav', false);
});

test('family member edit page renders sticky section nav markers', function () {
    $familyMember = FamilyMember::factory()->create();

    Livewire::test(EditFamilyMember::class, ['record' => $familyMember->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-section-nav', false);
});

test('family member section nav lists anchor tabs', function () {
    Livewire::test(CreateFamilyMember::class)
        ->assertSuccessful()
        ->assertSee('Profile Photo')
        ->assertSee('Family Member Details')
        ->assertSee('#profile-photo', false)
        ->assertSee('#family-member-details', false);
});

test('family member section nav items match sectionNavItems helper', function () {
    expect(FamilyMemberForm::sectionNavItems())->toBe([
        ['label' => 'Profile Photo', 'id' => 'profile-photo'],
        ['label' => 'Family Member Details', 'id' => 'family-member-details'],
    ]);
});

test('family member section nav smooth scrolls on tab click', function () {
    Livewire::test(CreateFamilyMember::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false);
});
