<?php

declare(strict_types=1);

use App\Enums\LabelType;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\Labels\Pages\EditLabel;
use App\Filament\Resources\Labels\Pages\ListLabels;
use App\Models\Label;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('primary can duplicate a label from the list', function () {
    $source = Label::factory()->create([
        'name' => 'Food & Dining',
        'slug' => 'food-dining',
        'icon' => 'heroicon-o-shopping-bag',
        'color' => '#f59e0b',
        'description' => '<p>Meals and groceries</p>',
    ]);

    $page = Livewire::test(ListLabels::class)
        ->callAction(TestAction::make('replicate')->table($source))
        ->assertNotified('Label duplicated');

    $replica = Label::query()
        ->where('slug', 'food-dining-copy')
        ->first();

    expect($replica)->not->toBeNull();

    $page->assertRedirect(LabelResource::getUrl('edit', ['record' => $replica]));

    expect($replica->name)->toBe('Food & Dining (Copy)')
        ->and($replica->type)->toBe($source->type)
        ->and($replica->icon)->toBe('heroicon-o-shopping-bag')
        ->and($replica->color)->toBe('#f59e0b')
        ->and($replica->description)->toBe('<p>Meals and groceries</p>')
        ->and($replica->is_system)->toBeFalse()
        ->and($replica->edited_by)->toBe(auth()->id());
});

test('primary can bulk duplicate labels from the list', function () {
    $first = Label::factory()->create([
        'name' => 'Food',
        'slug' => 'food',
    ]);
    $second = Label::factory()->create([
        'name' => 'Transport',
        'slug' => 'transport',
    ]);

    Livewire::test(ListLabels::class)
        ->selectTableRecords([$first->getKey(), $second->getKey()])
        ->callAction(TestAction::make('duplicate')->table()->bulk())
        ->assertNotified('2 labels duplicated')
        ->assertNoRedirect();

    expect(Label::query()->count())->toBe(4)
        ->and(Label::query()->where('slug', 'food-copy')->exists())->toBeTrue()
        ->and(Label::query()->where('slug', 'transport-copy')->exists())->toBeTrue();
});

test('duplicating a system label creates a user label with a unique slug', function () {
    $systemLabel = Label::factory()->create([
        'type' => LabelType::Finance,
        'name' => 'Bills',
        'slug' => 'bills',
        'is_system' => true,
    ]);
    Label::factory()->create([
        'type' => LabelType::Finance,
        'name' => 'Bills Copy',
        'slug' => 'bills-copy',
    ]);

    Livewire::test(ListLabels::class)
        ->callAction(TestAction::make('replicate')->table($systemLabel));

    $replica = Label::query()
        ->where('slug', 'bills-copy-2')
        ->first();

    expect($replica)->not->toBeNull()
        ->and($replica->name)->toBe('Bills (Copy 2)')
        ->and($replica->is_system)->toBeFalse();
});

test('label duplicate action is available on the edit header', function () {
    $label = Label::factory()->create();

    Livewire::test(EditLabel::class, ['record' => $label->getRouteKey()])
        ->assertActionVisible('replicate');
});
