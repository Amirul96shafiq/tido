<?php

declare(strict_types=1);

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Models\FamilyMember;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('invoice create form uses uploaded by wording', function () {
    FamilyMember::factory()->create([
        'name' => 'Nor Ezrieana Harun',
        'display_name' => 'nor',
    ]);

    Livewire::test(CreateInvoice::class)
        ->assertSuccessful()
        ->assertSee('Uploaded By')
        ->assertSee('Primary username')
        ->assertSee('nor')
        ->assertDontSee('Nor Ezrieana Harun');
});

test('invoice list shows uploaded by usernames', function () {
    $member = FamilyMember::factory()->create([
        'name' => 'Nor Ezrieana Harun',
        'display_name' => 'nor',
    ]);

    Invoice::unsetEventDispatcher();

    Invoice::factory()->create([
        'merchant_name' => 'Primary Invoice',
        'family_member_id' => null,
    ]);

    Invoice::factory()->create([
        'merchant_name' => 'Family Invoice',
        'family_member_id' => $member->id,
    ]);

    $this->get(InvoiceResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('Uploaded By')
        ->assertSee('Primary username')
        ->assertSee('nor')
        ->assertDontSee('Nor Ezrieana Harun');
});
