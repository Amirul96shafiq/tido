<?php

declare(strict_types=1);

use App\Filament\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('primary can duplicate a payment method from the list', function () {
    $source = PaymentMethod::factory()->create([
        'name' => 'Corporate Card',
        'slug' => 'corporate-card',
        'aliases' => ['corp', 'company card'],
        'notes' => '<p>Business expenses</p>',
        'icon' => 'heroicon-o-credit-card',
        'color' => '#f59e0b',
    ]);

    $page = Livewire::test(ListPaymentMethods::class)
        ->callAction(TestAction::make('replicate')->table($source))
        ->assertNotified('Payment method duplicated');

    $replica = PaymentMethod::query()
        ->where('slug', 'corporate-card-copy')
        ->first();

    expect($replica)->not->toBeNull();

    $page->assertRedirect(PaymentMethodResource::getUrl('edit', ['record' => $replica]));

    expect($replica->name)->toBe('Corporate Card (Copy)')
        ->and($replica->aliases)->toBe(['corp', 'company card'])
        ->and($replica->notes)->toBe('<p>Business expenses</p>')
        ->and($replica->icon)->toBe('heroicon-o-credit-card')
        ->and($replica->color)->toBe('#f59e0b')
        ->and($replica->is_system)->toBeFalse()
        ->and($replica->edited_by)->toBe(auth()->id());
});

test('primary can bulk duplicate payment methods from the list', function () {
    $first = PaymentMethod::factory()->create([
        'name' => 'Cash Wallet',
        'slug' => 'cash-wallet',
    ]);
    $second = PaymentMethod::factory()->create([
        'name' => 'Debit Card',
        'slug' => 'debit-card',
    ]);

    $initialCount = PaymentMethod::query()->count();

    Livewire::test(ListPaymentMethods::class)
        ->selectTableRecords([$first->getKey(), $second->getKey()])
        ->callAction(TestAction::make('duplicate')->table()->bulk())
        ->assertNotified('2 payment methods duplicated')
        ->assertNoRedirect();

    expect(PaymentMethod::query()->count())->toBe($initialCount + 2)
        ->and(PaymentMethod::query()->where('slug', 'cash-wallet-copy')->exists())->toBeTrue()
        ->and(PaymentMethod::query()->where('slug', 'debit-card-copy')->exists())->toBeTrue();
});

test('duplicating a system payment method creates a user method with a unique slug', function () {
    $systemMethod = PaymentMethod::factory()->system()->create([
        'name' => 'Corporate Visa',
        'slug' => 'corporate-visa',
    ]);
    PaymentMethod::factory()->create([
        'name' => 'Corporate Visa Copy',
        'slug' => 'corporate-visa-copy',
    ]);

    Livewire::test(ListPaymentMethods::class)
        ->callAction(TestAction::make('replicate')->table($systemMethod));

    $replica = PaymentMethod::query()
        ->where('slug', 'corporate-visa-copy-2')
        ->first();

    expect($replica)->not->toBeNull()
        ->and($replica->name)->toBe('Corporate Visa (Copy 2)')
        ->and($replica->is_system)->toBeFalse();
});

test('payment method duplicate action is available on the edit header', function () {
    $method = PaymentMethod::factory()->create();

    Livewire::test(EditPaymentMethod::class, ['record' => $method->getRouteKey()])
        ->assertActionVisible('replicate');
});

test('payment methods table supports deleted records filter and soft delete actions', function () {
    $active = PaymentMethod::factory()->create(['name' => 'Active Method']);
    $trashed = PaymentMethod::factory()->create(['name' => 'Trashed Method']);
    $trashed->delete();

    Livewire::test(ListPaymentMethods::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$trashed])
        ->assertTableFilterExists('trashed', fn ($filter): bool => $filter instanceof TrashedFilter)
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$active, $trashed])
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$trashed])
        ->assertCanNotSeeTableRecords([$active]);

    Livewire::test(EditPaymentMethod::class, ['record' => $trashed->getRouteKey()])
        ->assertSuccessful()
        ->assertActionExists('restore')
        ->assertActionExists('forceDelete');
});
