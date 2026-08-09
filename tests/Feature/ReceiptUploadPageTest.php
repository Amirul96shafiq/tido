<?php

declare(strict_types=1);

use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\DashboardSpenderScope;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('receipt upload page lists recent expenses', function () {
    $expense = Expense::factory()->create([
        'original_filename' => 'wa_receipt_preview.jpg',
        'image_path' => 'receipts/wa_receipt_preview.jpg',
    ]);

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertSeeHtml('wire:poll.10s.visible')
        ->assertCanSeeTableRecords([$expense])
        ->assertSee('wa_receipt....jpg');
});

test('receipt upload page polls every ten seconds when no expense is pending', function () {
    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertSeeHtml('wire:poll.10s.visible');
});

test('filename links to file in a new tab', function () {
    Storage::fake();

    $path = 'receipts/wa_receipt_preview.jpg';
    Storage::put($path, 'fake-image-bytes');

    $expense = Expense::factory()->create([
        'original_filename' => 'wa_receipt_preview.jpg',
        'image_path' => $path,
    ]);

    $url = Storage::temporaryUrl($path, now()->addMinutes(30));

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$expense])
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml(e($url));
});

test('filename without file path has no link', function () {
    $expense = Expense::factory()->create([
        'original_filename' => 'missing_file.jpg',
        'image_path' => null,
    ]);

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$expense])
        ->assertDontSeeHtml('missing_file.jpg</a>');
});

test('receipt upload page truncates long merchant names with full name in tooltip', function () {
    $longMerchant = 'Cosmo Restaurants Sdn Bhd';
    $expense = Expense::factory()->create([
        'merchant_name' => $longMerchant,
    ]);

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$expense])
        ->assertSee('Cosmo Restaurants Sd...');

    $column = Livewire::test(ReceiptUploadPage::class)
        ->instance()
        ->getTable()
        ->getColumn('merchant_name');

    expect($column)->not->toBeNull()
        ->and($column->getCharacterLimit())->toBe(20);

    $tooltip = $column->record($expense)->getTooltip($longMerchant);

    expect($tooltip)->toBe($longMerchant);
});

test('upload button shows loading spinner while saving', function () {
    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertSeeHtml('wire:target="save"')
        ->assertSeeHtml('wire:loading.delay')
        ->assertSeeHtml('M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z')
        ->assertSee('Upload and Start AI Extraction');
});

test('receipt upload page filters recent uploads by from spender', function () {
    $member = FamilyMember::factory()->create([
        'name' => 'Nor Ezrieana Harun',
        'display_name' => 'Ahlong',
    ]);

    $familyExpense = Expense::factory()->create([
        'original_filename' => 'family_receipt.jpg',
        'family_member_id' => $member->id,
    ]);

    $primaryExpense = Expense::factory()->create([
        'original_filename' => 'primary_receipt.jpg',
        'family_member_id' => null,
    ]);

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$familyExpense, $primaryExpense])
        ->filterTable('spender', DashboardSpenderScope::familyValue((int) $member->id))
        ->assertCanSeeTableRecords([$familyExpense])
        ->assertCanNotSeeTableRecords([$primaryExpense])
        ->filterTable('spender', DashboardSpenderScope::PRIMARY)
        ->assertCanSeeTableRecords([$primaryExpense])
        ->assertCanNotSeeTableRecords([$familyExpense]);
});

test('receipt upload page edit action spa navigates to expense edit', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    $expense = Expense::factory()->create([
        'original_filename' => null,
        'image_path' => null,
    ]);

    $editUrl = ExpenseResource::getUrl('edit', ['record' => $expense]);
    $editAction = TestAction::make('edit')->table($expense);

    $table = Livewire::test(ReceiptUploadPage::class)
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

test('family member sees the recent upload edit action disabled for unsupported expenses', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Nor Ezrieana Harun',
        'display_name' => 'Along',
    ]);
    $familyUser = User::query()
        ->where('family_member_id', $member->id)
        ->firstOrFail();

    $ownExpense = Expense::factory()->create([
        'family_member_id' => $member->id,
    ]);
    $primaryExpense = Expense::factory()->create([
        'family_member_id' => null,
    ]);

    $this->actingAs($familyUser);

    Livewire::test(ReceiptUploadPage::class)
        ->assertSuccessful()
        ->assertActionVisible(TestAction::make('edit')->table($ownExpense))
        ->assertActionEnabled(TestAction::make('edit')->table($ownExpense))
        ->assertActionVisible(TestAction::make('edit')->table($primaryExpense))
        ->assertActionDisabled(TestAction::make('edit')->table($primaryExpense));
});
