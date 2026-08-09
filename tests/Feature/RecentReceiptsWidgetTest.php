<?php

declare(strict_types=1);

use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Widgets\RecentReceipts;
use App\Helpers\FilenameDisplay;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\DashboardSpenderScope;
use Database\Seeders\PaymentMethodSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PaymentMethodSeeder::class);

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('recent receipts widget shows upload table columns', function () {
    $invoice = Expense::factory()->create([
        'original_filename' => 'dashboard_receipt.jpg',
        'image_path' => 'receipts/dashboard_receipt.jpg',
        'merchant_name' => 'Widget Merchant',
        'payment_method_id' => PaymentMethod::findBySlug('cash')->id,
        'source' => 'manual',
        'status' => 'reviewed',
        'date_time' => now(),
        'total_amount' => 12.50,
    ]);

    Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertSeeHtml('fi-wi-recent-receipts')
        ->assertSeeHtml('wire:poll.10s.visible')
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee('dashboard_....jpg')
        ->assertSee('Widget Merchant')
        ->assertSee('Cash')
        ->assertCanRenderTableColumn('original_filename')
        ->assertCanRenderTableColumn('paymentMethod.name')
        ->assertCanRenderTableColumn('source')
        ->assertCanRenderTableColumn('created_at');
});

test('recent receipts widget polls every ten seconds for historical months', function () {
    Livewire::test(RecentReceipts::class, [
        'pageFilters' => [
            'month' => now()->subMonth()->format('Y-m'),
        ],
    ])
        ->assertSuccessful()
        ->assertSeeHtml('wire:poll.10s.visible');
});

test('recent receipts widget shows a primary link to recent uploads without table controls', function () {
    $component = Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertSee('View all')
        ->assertSee(ReceiptUploadPage::getUrl().'#recent-uploads', false)
        ->assertDontSee('Search')
        ->assertDontSee('Filter')
        ->assertDontSee('Per page')
        ->assertDontSee('Next');

    $table = $component->instance()->getTable();
    $headerAction = $table->getAction('viewRecentUploads');

    expect($table->isSearchable())->toBeFalse()
        ->and($table->isFilterable())->toBeFalse()
        ->and($table->isPaginated())->toBeFalse()
        ->and($headerAction)->not->toBeNull()
        ->and($headerAction?->getUrl())->toBe(ReceiptUploadPage::getUrl().'#recent-uploads')
        ->and($headerAction?->getColor())->toBe('primary');
});

test('recent receipts widget filename links to file in a new tab', function () {
    Storage::fake();

    $path = 'receipts/dashboard_receipt.jpg';
    Storage::put($path, 'fake-image-bytes');

    $invoice = Expense::factory()->create([
        'original_filename' => 'dashboard_receipt.jpg',
        'image_path' => $path,
        'date_time' => now(),
    ]);

    $url = Storage::temporaryUrl($path, now()->addMinutes(30));

    Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$invoice])
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml(e($url));
});

test('recent receipts widget shows Manual expense plain text without file link', function () {
    $invoice = Expense::factory()->create([
        'merchant_name' => 'Kedai Makan Seri Ayu',
        'original_filename' => null,
        'image_path' => null,
        'source' => 'whatsapp',
        'payment_method_id' => PaymentMethod::findBySlug('cash')->id,
        'status' => 'reviewed',
        'date_time' => now(),
        'total_amount' => 26.00,
    ]);

    Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee(FilenameDisplay::MANUAL_EXPENSE_LABEL)
        ->assertSee('Kedai Makan Seri Ayu');

    expect(FilenameDisplay::labelForExpense($invoice))->toBe('Manual expense')
        ->and($invoice->fileUrl())->toBeNull();
});

test('recent receipts widget truncates long merchant names with full name in tooltip', function () {
    $longMerchant = 'Cosmo Restaurants Sdn Bhd';
    $invoice = Expense::factory()->create([
        'merchant_name' => $longMerchant,
        'date_time' => now(),
    ]);

    Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee('Cosmo Restaurants Sd...');

    $column = Livewire::test(RecentReceipts::class)
        ->instance()
        ->getTable()
        ->getColumn('merchant_name');

    expect($column)->not->toBeNull()
        ->and($column->getCharacterLimit())->toBe(20);

    $tooltip = $column->record($invoice)->getTooltip($longMerchant);

    expect($tooltip)->toBe($longMerchant);
});

test('recent receipts widget shows only the five latest receipts without pagination', function () {
    $oldest = Expense::factory()->create([
        'merchant_name' => 'Oldest receipt',
        'date_time' => now(),
        'created_at' => now()->subMinutes(6),
    ]);
    $latest = collect(range(0, 4))->map(
        fn (int $index): Expense => Expense::factory()->create([
            'merchant_name' => 'Latest receipt '.($index + 1),
            'date_time' => now(),
            'created_at' => now()->subMinutes(5 - $index),
        ]),
    );

    $component = Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($latest)
        ->assertCanNotSeeTableRecords([$oldest]);

    expect($component->instance()->getTableRecords())->toHaveCount(5);
});

test('recent receipts widget excludes invoices with receipt date outside selected month', function () {
    $inMonth = Expense::factory()->create([
        'merchant_name' => 'This Month Receipt',
        'date_time' => now(),
        'created_at' => now()->subYear(),
    ]);

    $outOfMonth = Expense::factory()->create([
        'merchant_name' => 'Last Year Receipt',
        'date_time' => now()->subYear(),
        'created_at' => now(),
    ]);

    Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$inMonth])
        ->assertCanNotSeeTableRecords([$outOfMonth]);
});

test('recent receipts widget includes late uploads whose receipt date is in selected month', function () {
    $lateUpload = Expense::factory()->create([
        'merchant_name' => 'Tenaga Nasional',
        'date_time' => now(),
        'created_at' => now()->addMonths(2),
    ]);

    Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$lateUpload])
        ->assertSee('Tenaga Nasional');
});

test('recent receipts widget filters by family member spender scope', function () {
    $member = FamilyMember::factory()->create([
        'name' => 'Nor Ezrieana Harun',
        'display_name' => 'Ahlong',
    ]);

    $familyInvoice = Expense::factory()->create([
        'merchant_name' => 'Ahlong Merchant',
        'date_time' => now(),
        'family_member_id' => $member->id,
    ]);

    $primaryInvoice = Expense::factory()->create([
        'merchant_name' => 'Primary Merchant',
        'date_time' => now(),
        'family_member_id' => null,
    ]);

    Livewire::test(RecentReceipts::class, [
        'pageFilters' => [
            'month' => now()->format('Y-m'),
            'spender' => DashboardSpenderScope::familyValue((int) $member->id),
        ],
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$familyInvoice])
        ->assertCanNotSeeTableRecords([$primaryInvoice]);
});

test('recent receipts widget edit action spa navigates to invoice edit', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    $invoice = Expense::factory()->create([
        'date_time' => now(),
        'original_filename' => null,
        'image_path' => null,
    ]);

    $editUrl = ExpenseResource::getUrl('edit', ['record' => $invoice]);
    $editAction = TestAction::make('edit')->table($invoice);

    $table = Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertActionExists($editAction)
        ->assertActionHasUrl($editAction, $editUrl)
        ->assertActionShouldNotOpenUrlInNewTab($editAction)
        ->assertSee($editUrl, false)
        ->assertSee('wire:navigate', false)
        ->instance()
        ->getTable();

    $action = $table->getAction('edit');

    expect($action)->not->toBeNull()
        ->and($action->isIconButton())->toBeTrue()
        ->and($action->getTooltip())->toBe($action->getLabel());
});

test('family member sees the recent receipts edit action disabled for unsupported invoices', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Nor Ezrieana Harun',
        'display_name' => 'Along',
    ]);
    $familyUser = User::query()
        ->where('family_member_id', $member->id)
        ->firstOrFail();

    $ownInvoice = Expense::factory()->create([
        'family_member_id' => $member->id,
        'date_time' => now(),
    ]);
    $primaryInvoice = Expense::factory()->create([
        'family_member_id' => null,
        'date_time' => now(),
    ]);

    $this->actingAs($familyUser);

    Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertActionVisible(TestAction::make('edit')->table($ownInvoice))
        ->assertActionEnabled(TestAction::make('edit')->table($ownInvoice))
        ->assertActionVisible(TestAction::make('edit')->table($primaryInvoice))
        ->assertActionDisabled(TestAction::make('edit')->table($primaryInvoice));
});
