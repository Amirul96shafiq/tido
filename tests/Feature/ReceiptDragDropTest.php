<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ReceiptUploadPage;
use App\Jobs\ExtractReceiptDataJob;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => '60123456789',
    ]);

    $this->actingAs(User::factory()->withWhatsAppPhone('60123456789')->create());
});

test('drag drop source files exist', function () {
    expect(resource_path('js/drag-drop-upload.js'))->toBeReadableFile()
        ->and(resource_path('js/receipt-upload-handler.js'))->toBeReadableFile()
        ->and(resource_path('js/receipt-image-preview.js'))->toBeReadableFile()
        ->and(resource_path('views/components/drag-drop-config.blade.php'))->toBeReadableFile();
});

test('drag drop upload ignores non-file and sortable list drags', function () {
    $source = (string) file_get_contents(resource_path('js/drag-drop-upload.js'));

    expect($source)
        ->toContain('isFileDrag(event)')
        ->toContain("includes('Files')")
        ->toContain('[wire\\\\:sort]')
        ->toContain('shouldIgnoreEvent(event)');
});

test('drag drop upload includes FilePond-style drip blob that follows the cursor', function () {
    $source = (string) file_get_contents(resource_path('js/drag-drop-upload.js'));

    expect($source)
        ->toContain('drag-drop-drip-blob')
        ->toContain('moveDrip(event)')
        ->toContain('clientX')
        ->toContain('clientY')
        ->toContain('hideDrip(')
        ->toContain('DRIP_INITIAL_SCALE')
        ->toContain('springStep')
        ->toContain('dropDrip()')
        ->toContain('scale3d');
});

test('drag drop upload script includes expected copy', function () {
    $source = (string) file_get_contents(resource_path('js/drag-drop-upload.js'));

    expect($source)
        ->toContain("dropReceipt: 'Drop receipt to upload'")
        ->toContain('5MB')
        ->toContain('JPEG');
});

test('admin dashboard includes drag drop config bootstrap', function () {
    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee('window.dragDropConfig', false)
        ->assertSee(ReceiptUploadPage::getUrl(), false);
});

test('upload receipts page includes drag drop config bootstrap', function () {
    $this->get(ReceiptUploadPage::getUrl())
        ->assertSuccessful()
        ->assertSee('window.dragDropConfig', false);
});

test('vite config includes drag drop scripts', function () {
    $viteConfig = (string) file_get_contents(base_path('vite.config.js'));

    expect($viteConfig)
        ->toContain('resources/js/drag-drop-upload.js')
        ->toContain('resources/js/receipt-upload-handler.js')
        ->toContain('resources/js/receipt-image-preview.js');
});

test('admin panel registers receipt image preview script', function () {
    $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($provider)
        ->toContain('receipt-image-preview')
        ->toContain('resources/js/receipt-image-preview.js');
});

test('receipt image preview script raises filepond max height for receipt uploads', function () {
    $source = (string) file_get_contents(resource_path('js/receipt-image-preview.js'));

    expect($source)
        ->toContain('.fi-receipt-image-upload')
        ->toContain('tido-receipt-native-preview')
        ->toContain('FilePond.find')
        ->toContain('MAX_PREVIEW_HEIGHT = 500');
});

test('receipt upload page save creates pending invoice and dispatches extraction job', function () {
    Storage::fake('public');
    Queue::fake();

    $file = UploadedFile::fake()->image('receipt.jpg');

    Livewire::test(ReceiptUploadPage::class)
        ->set('data.receipts', [$file])
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotified();

    $invoice = Invoice::query()->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe('pending')
        ->and($invoice->source)->toBe('manual')
        ->and($invoice->merchant_name)->toBe('Pending AI Extraction...')
        ->and($invoice->image_path)->toStartWith('receipts/')
        ->and($invoice->original_filename)->toEndWith('.jpg');

    Queue::assertPushed(ExtractReceiptDataJob::class);
});

test('receipt upload page clears required error after file is selected', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('receipt.jpg');

    Livewire::test(ReceiptUploadPage::class)
        ->call('save')
        ->assertHasErrors(['data.receipts'])
        ->set('data.receipts', [$file])
        ->assertHasNoErrors(['data.receipts']);
});

test('app css hides filepond drop label when files are present', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('.filepond--root:has(.filepond--item) .filepond--drop-label')
        ->toContain('display: none !important')
        ->toContain('min-height: 0 !important')
        ->toContain('.filepond--list-scroller');
});
