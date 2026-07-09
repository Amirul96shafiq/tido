<?php

declare(strict_types=1);

use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Labelings\LabelingResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);

    $this->admin = User::factory()->withWhatsAppPhone('60123456789')->create();
});

test('filament admin page requires authentication', function () {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});

test('authenticated user can access dashboard', function () {
    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertSuccessful();
});

test('authenticated user can load labelings list', function () {
    $this->actingAs($this->admin)
        ->get(LabelingResource::getUrl('index'))
        ->assertSuccessful();
});

test('authenticated user can load invoices list', function () {
    $this->actingAs($this->admin)
        ->get(InvoiceResource::getUrl('index'))
        ->assertSuccessful();
});

test('authenticated user can load budgets list', function () {
    $this->actingAs($this->admin)
        ->get(BudgetResource::getUrl('index'))
        ->assertSuccessful();
});

test('authenticated user can load upload page', function () {
    $this->actingAs($this->admin)
        ->get(ReceiptUploadPage::getUrl())
        ->assertSuccessful();
});
