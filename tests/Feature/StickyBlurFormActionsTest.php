<?php

declare(strict_types=1);

use App\Enums\LabelType;
use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Labels\Pages\CreateLabel;
use App\Filament\Resources\Labels\Pages\EditLabel;
use App\Models\Budget;
use App\Models\Invoice;
use App\Models\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);

    Queue::fake();

    $this->admin = User::factory()->withWhatsAppPhone('60123456789')->create();
});

/**
 * @return array<string, array{0: class-string, 1: array<string, mixed>}>
 */
function stickyBlurFormActionPages(): array
{
    return [
        'create invoice' => [CreateInvoice::class, []],
        'edit invoice' => [EditInvoice::class, []],
        'create label' => [CreateLabel::class, []],
        'edit label' => [EditLabel::class, []],
        'create budget' => [CreateBudget::class, []],
        'edit budget' => [EditBudget::class, []],
        'edit profile' => [EditProfile::class, []],
    ];
}

test('create edit and profile pages use sticky blur form action markers', function (string $pageClass, array $params) {
    $this->actingAs($this->admin);

    if ($pageClass === EditInvoice::class) {
        $params['record'] = Invoice::factory()->create()->getRouteKey();
    }

    if ($pageClass === EditLabel::class) {
        $params['record'] = Label::factory()->create()->getRouteKey();
    }

    if ($pageClass === EditBudget::class) {
        $params['record'] = Budget::factory()->create()->getRouteKey();
    }

    Livewire::test($pageClass, $params)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--bottom', false);
})->with(stickyBlurFormActionPages());

test('create label still submits with sticky blur form actions', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateLabel::class)
        ->fillForm([
            'type' => LabelType::Finance->value,
            'name' => 'Sticky Test Label',
            'slug' => 'sticky-test-label',
            'icon' => 'heroicon-o-wallet',
            'color' => '#dbb051',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $this->assertDatabaseHas('labels', [
        'name' => 'Sticky Test Label',
        'slug' => 'sticky-test-label',
    ]);
});

test('sticky blur form actions trait is wired on resource and profile pages', function () {
    $pages = [
        CreateInvoice::class,
        EditInvoice::class,
        CreateLabel::class,
        EditLabel::class,
        CreateBudget::class,
        EditBudget::class,
        EditProfile::class,
    ];

    foreach ($pages as $pageClass) {
        expect(class_uses_recursive($pageClass))
            ->toContain(HasStickyBlurFormActions::class);
    }
});
