<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Labels\Pages\EditLabel;
use App\Models\Label;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('edit-record form action translations use Save and Back', function () {
    expect(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
        ->toBe('Save')
        ->and(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
        ->toBe('Back');
});

test('edit profile form action translations use Save and Back', function () {
    expect(__('filament-panels::auth/pages/edit-profile.form.actions.save.label'))
        ->toBe('Save')
        ->and(__('filament-panels::auth/pages/edit-profile.actions.cancel.label'))
        ->toBe('Back');
});

test('edit label form actions resolve Save and Back labels', function () {
    $label = Label::factory()->create(['name' => 'Food & Dining']);

    $page = Livewire::test(EditLabel::class, ['record' => $label->getRouteKey()]);
    $instance = $page->instance();

    $method = new ReflectionMethod($instance, 'getFormActions');
    /** @var list<Action> $actions */
    $actions = $method->invoke($instance);

    $labels = array_map(
        static fn (Action $action): string => (string) $action->getLabel(),
        $actions,
    );

    expect($labels)
        ->toContain('Save')
        ->toContain('Back')
        ->not->toContain('Save changes')
        ->not->toContain('Cancel');
});

test('edit profile form actions resolve Save and Back labels', function () {
    $page = Livewire::test(EditProfile::class);
    $instance = $page->instance();

    $method = new ReflectionMethod($instance, 'getFormActions');
    /** @var list<Action> $actions */
    $actions = $method->invoke($instance);

    $labels = array_map(
        static fn (Action $action): string => (string) $action->getLabel(),
        $actions,
    );

    expect($labels)
        ->toContain('Save')
        ->toContain('Back')
        ->not->toContain('Save changes')
        ->not->toContain('Cancel');
});
