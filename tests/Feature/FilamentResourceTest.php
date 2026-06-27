<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
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

test('authenticated user can load categories list', function () {
    $this->actingAs($this->admin)
        ->get(\App\Filament\Resources\Categories\CategoryResource::getUrl('index'))
        ->assertSuccessful();
});

test('authenticated user can load invoices list', function () {
    $this->actingAs($this->admin)
        ->get(\App\Filament\Resources\Invoices\InvoiceResource::getUrl('index'))
        ->assertSuccessful();
});

test('authenticated user can load budgets list', function () {
    $this->actingAs($this->admin)
        ->get(\App\Filament\Resources\Budgets\BudgetResource::getUrl('index'))
        ->assertSuccessful();
});

test('authenticated user can load upload page', function () {
    $this->actingAs($this->admin)
        ->get(\App\Filament\Pages\ReceiptUploadPage::getUrl())
        ->assertSuccessful();
});
