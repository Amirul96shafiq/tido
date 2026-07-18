<?php

declare(strict_types=1);

use App\Filament\Forms\Components\NotesRichEditor;
use App\Filament\Resources\Labels\Pages\CreateLabel;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('label form uses notes rich editor for description', function () {
    Livewire::test(CreateLabel::class)
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            'description',
            checkComponentUsing: function (NotesRichEditor $component): bool {
                expect($component->getLabel())->toBe('Label Notes')
                    ->and($component->getExtraAttributes())->toMatchArray([
                        'class' => NotesRichEditor::EXTRA_CLASS,
                    ]);

                return true;
            },
        );
});

test('create label page saves rich description html', function () {
    Livewire::test(CreateLabel::class)
        ->fillForm([
            'name' => 'Pet Supplies',
            'slug' => 'pet-supplies',
            'description' => '<p>Pet food and grooming</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $label = Label::query()->where('slug', 'pet-supplies')->first();

    expect($label)->not->toBeNull()
        ->and($label->description)->toBe('<p>Pet food and grooming</p>');
});
