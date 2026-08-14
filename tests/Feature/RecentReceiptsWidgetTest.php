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
    $expense = Expense::factory()->create([
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
        ->assertDontSeeHtml('wire:poll.10s.visible')
        ->assertCanSeeTableRecords([$expense])
        ->assertSee('dashboard_....jpg')
        ->assertSee('Widget Merchant')
        ->assertSee('Cash')
        ->assertCanRenderTableColumn('original_filename')
        ->assertCanRenderTableColumn('paymentMethod.name')
        ->assertCanRenderTableColumn('source')
        ->assertCanRenderTableColumn('created_at');
});

test('recent receipts widget listens for echo expense updates without polling', function () {
    $pending = Expense::factory()->create([
        'status' => 'pending',
        'source' => 'whatsapp',
        'image_path' => null,
        'date_time' => now(),
    ]);

    $component = Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertDontSeeHtml('wire:poll.10s.visible')
        ->assertSeeHtml('tido-expense-status-pending')
        ->assertCanSeeTableRecords([$pending]);

    expect($component->instance()->getListeners())
        ->toHaveKey('echo-private:household.expenses,.ExpenseUpdated');
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

    $expense = Expense::factory()->create([
        'original_filename' => 'dashboard_receipt.jpg',
        'image_path' => $path,
        'date_time' => now(),
    ]);

    $url = Storage::temporaryUrl($path, now()->addMinutes(30));

    Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$expense])
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml(e($url));
});

test('recent receipts widget shows Manual expense plain text without file link', function () {
    $expense = Expense::factory()->create([
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
        ->assertCanSeeTableRecords([$expense])
        ->assertSee(FilenameDisplay::MANUAL_EXPENSE_LABEL)
        ->assertSee('Kedai Makan Seri Ayu');

    expect(FilenameDisplay::labelForExpense($expense))->toBe('Manual expense')
        ->and($expense->fileUrl())->toBeNull();
});

test('recent receipts widget truncates long merchant names with full name in tooltip', function () {
    $longMerchant = 'Cosmo Restaurants Sdn Bhd';
    $expense = Expense::factory()->create([
        'merchant_name' => $longMerchant,
        'date_time' => now(),
    ]);

    Livewire::test(RecentReceipts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$expense])
        ->assertSee('Cosmo Restaurants Sd...');

    $column = Livewire::test(RecentReceipts::class)
        ->instance()
        ->getTable()
        ->getColumn('merchant_name');

    expect($column)->not->toBeNull()
        ->and($column->getCharacterLimit())->toBe(20);

    $tooltip = $column->record($expense)->getTooltip($longMerchant);

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

test('recent receipts widget excludes expenses with receipt date outside selected month', function () {
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

    $familyExpense = Expense::factory()->create([
        'merchant_name' => 'Ahlong Merchant',
        'date_time' => now(),
        'family_member_id' => $member->id,
    ]);

    $primaryExpense = Expense::factory()->create([
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
        ->assertCanSeeTableRecords([$familyExpense])
        ->assertCanNotSeeTableRecords([$primaryExpense]);
});

test('recent receipts widget edit action spa navigates to expense edit', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    $expense = Expense::factory()->create([
        'date_time' => now(),
        'original_filename' => null,
        'image_path' => null,
    ]);

    $editUrl = ExpenseResource::getUrl('edit', ['record' => $expense]);
    $editAction = TestAction::make('edit')->table($expense);

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

test('family member sees the recent receipts edit action disabled for unsupported expenses', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Nor Ezrieana Harun',
        'display_name' => 'Along',
    ]);
    $familyUser = User::query()
        ->where('family_member_id', $member->id)
        ->firstOrFail();

    $ownExpense = Expense::factory()->create([
        'family_member_id' => $member->id,
        'date_time' => now(),
    ]);
    $primaryExpense = Expense::factory()->create([
        'family_member_id' => null,
        'date_time' => now(),
    ]);

    $this->actingAs($familyUser);

    Livewire::test(RecentReceipts::class, [
        'pageFilters' => [
            'month' => now()->format('Y-m'),
            'spender' => DashboardSpenderScope::ALL,
        ],
    ])
        ->assertSuccessful()
        ->assertActionVisible(TestAction::make('edit')->table($ownExpense))
        ->assertActionEnabled(TestAction::make('edit')->table($ownExpense))
        ->assertActionVisible(TestAction::make('edit')->table($primaryExpense))
        ->assertActionDisabled(TestAction::make('edit')->table($primaryExpense));
});
