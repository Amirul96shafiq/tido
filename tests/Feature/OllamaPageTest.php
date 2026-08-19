<?php

declare(strict_types=1);

use App\Filament\Pages\OllamaPage;
use App\Filament\Pages\ReceiptUploadPage;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\OllamaSetting;
use App\Models\User;
use App\Services\Ollama\OllamaSettings;
use App\Services\Ollama\PopplerDetector;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Js;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function fakeOllamaTagsResponse(): array
{
    return [
        'models' => [
            [
                'name' => 'qwen2.5vl:7b',
                'model' => 'qwen2.5vl:7b',
                'size' => 5_969_245_856,
                'details' => [
                    'family' => 'qwen25vl',
                    'parameter_size' => '8.3B',
                    'quantization_level' => 'Q4_K_M',
                    'context_length' => 128000,
                ],
            ],
        ],
    ];
}

function fakePopplerBinaryFile(string $name): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tido-poppler-'.$name;
    file_put_contents($path, '');

    return $path;
}

/**
 * @param  list<string>  $binaryNames
 * @return list<string>
 */
function stagePopplerBinariesOnPath(array $binaryNames): array
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tido-poppler-test-'.uniqid('', true);
    mkdir($directory);

    $stagedPaths = [];

    foreach ($binaryNames as $binaryName) {
        $path = $directory.DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? $binaryName.'.exe' : $binaryName);
        file_put_contents($path, '');
        $stagedPaths[] = $path;
    }

    $previousPath = getenv('Path') ?: getenv('PATH') ?: '';
    putenv('Path='.$directory.';'.$previousPath);

    Process::fake(function (PendingProcess $process) use ($stagedPaths) {
        $command = $process->command;

        if (! is_array($command) || ($command[1] ?? null) !== '-v') {
            return Process::result(exitCode: 1);
        }

        if (in_array($command[0], $stagedPaths, true)) {
            return Process::result(errorOutput: 'pdfinfo version 24.08.0');
        }

        return Process::result(exitCode: 1);
    });

    return $stagedPaths;
}

function ollamaAdvancedSettingHelperText(?TextInput $field): ?string
{
    $helper = $field?->getChildSchema(TextInput::BELOW_CONTENT_SCHEMA_KEY)?->getComponents()[0] ?? null;

    return $helper instanceof Text ? $helper->getContent() : null;
}

beforeEach(function (): void {
    config([
        'services.ollama.host' => 'http://ollama.test',
        'services.ollama.model' => 'qwen2.5vl:7b',
        'services.ollama.timeout' => 120,
        'services.ollama.num_ctx' => 8192,
        'services.ollama.max_image_dimension' => 1280,
        'services.documents.pdfinfo_binary' => 'pdfinfo',
        'services.documents.pdftocairo_binary' => 'pdftocairo',
        'services.documents.pdftotext_binary' => 'pdftotext',
    ]);

    Http::fake([
        'http://ollama.test/api/tags' => Http::response(fakeOllamaTagsResponse()),
    ]);

    Process::fake([
        '*' => Process::result(exitCode: 1),
    ]);

    $this->actingAs(User::factory()->create());
});

test('ollama page is accessible by primary user', function (): void {
    $this->get(OllamaPage::getUrl())
        ->assertSuccessful();
});

test('ollama page keeps configure action without a setup section', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('Configure')
        ->assertDontSee('#ollama-setup', false)
        ->assertDontSee('Detect Ollama')
        ->assertActionExists('configureSetup');
});

test('ollama page opens setup in a modal', function (): void {
    Livewire::test(OllamaPage::class)
        ->mountAction('configureSetup')
        ->assertActionMounted('configureSetup')
        ->assertFormFieldExists('host', 'mountedActionSchema0')
        ->assertFormFieldExists('selectedModel', 'mountedActionSchema0');
});

test('ollama setup model select spans the full fieldset width', function (): void {
    $component = Livewire::test(OllamaPage::class)
        ->mountAction('configureSetup')
        ->assertActionMounted('configureSetup')
        ->assertFormFieldExists('selectedModel', 'mountedActionSchema0');

    $select = $component->instance()->getSchema('mountedActionSchema0')?->getComponent('selectedModel');

    expect($select?->getColumnSpan('default'))->toBe('full');
});

test('ollama setup modal shows run test extraction in footer', function (): void {
    Livewire::test(OllamaPage::class)
        ->set('detectionState', 'running')
        ->set('selectedModel', 'qwen2.5vl:7b')
        ->mountAction('configureSetup')
        ->assertActionMounted('configureSetup')
        ->assertActionVisible('runTestExtraction')
        ->assertActionHasLabel('runTestExtraction', 'Run test extraction');
});

test('ollama page shows status and configured model', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('Operational')
        ->assertSee('Installed models')
        ->assertSee('qwen2.5vl:7b');
});

test('ollama page lists available models', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('qwen2.5vl:7b')
        ->assertSee('qwen25vl')
        ->assertSee('8.3B')
        ->assertSee('Q4_K_M')
        ->assertSee('128,000 tokens')
        ->assertSee('6.0 GB');
});

test('ollama page marks configured model as active', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('Active');
});

test('ollama page shows model count in status message', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('Your Ollama instance is connected and ready. 1 model is installed and available for receipt parsing')
        ->assertSee('run a test extraction anytime to confirm OCR and structured output.');
});

test('ollama page shows degraded status when no models installed', function (): void {
    Livewire::test(OllamaPage::class)
        ->set('connectionStatus', 'degraded')
        ->set('statusMessage', 'Ollama is reachable but no models are installed.')
        ->set('availableModels', [])
        ->assertSee('Degraded')
        ->assertSee('no models are installed', false);
});

test('ollama page shows down status when ollama is unreachable', function (): void {
    Http::fake([
        'http://ollama.test/api/tags' => Http::response(null, 500),
    ]);

    Livewire::test(OllamaPage::class)
        ->assertSee('Down');
});

test('ollama page refresh action re-fetches status and notifies', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertActionVisible('refresh')
        ->callAction('refresh')
        ->assertNotified('Status refreshed');
});

test('ollama setup recheck detection notifies the result', function (): void {
    Livewire::test(OllamaPage::class)
        ->call('recheckDetection', 'http://ollama.test')
        ->assertNotified(
            Notification::make()
                ->title('Detection refreshed')
                ->body('Ollama is connected.')
                ->success(),
        );
});

test('ollama setup test connection does not emit a detection toast', function (): void {
    Livewire::test(OllamaPage::class)
        ->call('testConnection', 'http://ollama.test')
        ->assertNotified('Connection successful')
        ->assertNotNotified('Detection refreshed');
});

test('ollama page shows view details cta', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('View details')
        ->assertSee('Using environment defaults')
        ->assertSee('Model Use case')
        ->assertSee('Model Family')
        ->assertSee('Model Parameter size')
        ->assertSee('Model Quantization')
        ->assertSee('Model Context')
        ->assertSee('Model Size')
        ->assertSee('Suitable for structured extraction workflows that need consistent JSON output.')
        ->assertSee('Active')
        ->assertSee('fi-ollama-detail-row', false);
});

test('ollama page shows supported tasks slide-over cta', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('Supported tasks')
        ->assertSee('id="ollama-supported-tasks"', false)
        ->assertSee('slide-over', false)
        ->assertSee('fi-ollama-supported-tasks-list divide-y', false)
        ->assertSee('fi-ollama-detail-row__value--long', false)
        ->assertSee('Current use in tido')
        ->assertSee('Also suitable for')
        ->assertSee('Receipt image extraction')
        ->assertSee('Local document summarisation workflows');
});

test('ollama page shows pipeline and activity sections', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSee('Pipeline Readiness')
        ->assertSee('Receipt & Parsing Activity')
        ->assertSee('#ollama-pipeline', false)
        ->assertSee('#ollama-activity', false)
        ->assertDontSee('#ollama-models', false);
});

test('ollama page renders sticky section nav markers', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSuccessful()
        ->assertSee('tido-sticky-scope', false)
        ->assertSee('tido-sticky-marker--top', false)
        ->assertSee('tido-section-nav', false);
});

test('ollama page section nav items match helper', function (): void {
    expect(OllamaPage::sectionNavItems())->toBe([
        ['label' => 'Status', 'id' => 'ollama-status'],
        ['label' => 'Pipeline Readiness', 'id' => 'ollama-pipeline'],
        ['label' => 'Receipt & Parsing Activity', 'id' => 'ollama-activity'],
    ]);
});

test('ollama page section nav smooth scrolls on tab click', function (): void {
    Livewire::test(OllamaPage::class)
        ->assertSuccessful()
        ->assertSee('scrollToSection', false)
        ->assertSee("behavior: 'smooth'", false)
        ->assertSee('onNavClick($event)', false);
});

test('ollama page shows receipt & parsing activity stats from stored expenses', function (): void {
    Expense::withoutEvents(function (): void {
        Expense::factory()->create([
            'merchant_name' => 'PDF Grocer',
            'source' => 'whatsapp',
            'status' => 'parsed',
            'file_mime_type' => 'application/pdf',
        ]);

        Expense::factory()->create([
            'merchant_name' => 'Image Mart',
            'source' => 'manual',
            'status' => 'reviewed',
            'file_mime_type' => 'image/jpeg',
        ]);

        Expense::factory()->create([
            'merchant_name' => 'Text Expense',
            'source' => 'whatsapp',
            'status' => 'requires_manual_review',
            'file_mime_type' => null,
        ]);
    });

    Livewire::test(OllamaPage::class)
        ->assertSee('Recent receipt & parsing activity', false)
        ->assertSee('Latest processed receipt updated')
        ->assertSee('PDF receipts')
        ->assertSee('Image receipts')
        ->assertSee('Text-only receipts')
        ->assertSee('Upload Receipts')
        ->assertSee(ReceiptUploadPage::getUrl(), false);

    $html = Livewire::test(OllamaPage::class)
        ->assertSuccessful()
        ->html();

    expect(substr_count($html, 'statsOverviewStatChart({'))->toBe(6)
        ->and(substr_count($html, '<canvas x-ref="canvas" aria-hidden="true"></canvas>'))->toBe(6);
});

test('ollama advanced settings show min max helper text', function (): void {
    $component = Livewire::test(OllamaPage::class)
        ->set('detectionState', 'running')
        ->mountAction('configureSetup')
        ->assertActionMounted('configureSetup');

    $schema = $component->instance()->getSchema('mountedActionSchema0');
    $timeout = $schema?->getComponent('timeout');
    $numCtx = $schema?->getComponent('num_ctx');
    $maxImageDimension = $schema?->getComponent('max_image_dimension');

    expect($timeout)->toBeInstanceOf(TextInput::class)
        ->and($timeout?->getMinValue())->toBe(30)
        ->and($timeout?->getMaxValue())->toBe(600)
        ->and(ollamaAdvancedSettingHelperText($timeout))->toBe('30–600 seconds. HTTP wait for receipt extraction.')
        ->and($numCtx)->toBeInstanceOf(TextInput::class)
        ->and($numCtx?->getMinValue())->toBe(2048)
        ->and($numCtx?->getMaxValue())->toBe(131072)
        ->and(ollamaAdvancedSettingHelperText($numCtx))->toBe('2,048–131,072 tokens. Prompt and JSON answer budget.')
        ->and($maxImageDimension)->toBeInstanceOf(TextInput::class)
        ->and($maxImageDimension?->getMinValue())->toBe(512)
        ->and($maxImageDimension?->getMaxValue())->toBe(4096)
        ->and(ollamaAdvancedSettingHelperText($maxImageDimension))->toBe('512–4,096 px. Long-edge resize before OCR.');
});

test('ollama advanced settings reject values outside the allowed range', function (string $field, mixed $value): void {
    Livewire::test(OllamaPage::class)
        ->set('detectionState', 'running')
        ->callAction('configureSetup', [
            'host' => 'http://ollama.test',
            'selectedModel' => 'qwen2.5vl:7b',
            'timeout' => 120,
            'num_ctx' => 8192,
            'max_image_dimension' => 1280,
            $field => $value,
        ])
        ->assertHasActionErrors([$field]);

    expect(OllamaSetting::singleton()->{$field})->toBeNull();
})->with([
    'timeout below min' => ['timeout', 29],
    'timeout above max' => ['timeout', 601],
    'context below min' => ['num_ctx', 2047],
    'context above max' => ['num_ctx', 131073],
    'image below min' => ['max_image_dimension', 511],
    'image above max' => ['max_image_dimension', 4097],
]);

test('ollama page save settings persists to database', function (): void {
    Livewire::test(OllamaPage::class)
        ->callAction('configureSetup', [
            'host' => 'http://ollama.test',
            'selectedModel' => 'qwen2.5vl:7b',
            'timeout' => 120,
            'num_ctx' => 8192,
            'max_image_dimension' => 1280,
        ])
        ->assertNotified('Ollama settings saved')
        ->assertSee('Setup complete');

    $setting = OllamaSetting::singleton();

    expect($setting->host)->toBe('http://ollama.test')
        ->and($setting->model)->toBe('qwen2.5vl:7b')
        ->and($setting->setup_completed_at)->not->toBeNull();
});

test('ollama page rejects save when model is not installed', function (): void {
    Livewire::test(OllamaPage::class)
        ->set('availableModels', [
            [
                'name' => 'missing-model:7b',
                'family' => 'test',
                'parameterSize' => '7B',
                'quantization' => 'Q4',
                'contextLength' => 8192,
                'sizeBytes' => 1_000_000,
                'isConfigured' => true,
            ],
        ])
        ->callAction('configureSetup', [
            'host' => 'http://ollama.test',
            'selectedModel' => 'missing-model:7b',
            'timeout' => 120,
            'num_ctx' => 8192,
            'max_image_dimension' => 1280,
        ])
        ->assertNotified('Selected model is not installed');

    expect(OllamaSetting::singleton()->model)->toBeNull();
});

test('ollama page shows terminal pull command when no models are installed', function (): void {
    Livewire::test(OllamaPage::class)
        ->set('availableModels', [])
        ->set('connectionStatus', 'degraded')
        ->set('detectionState', 'running')
        ->mountAction('configureSetup')
        ->assertActionMounted('configureSetup')
        ->assertFormFieldExists('pull_command', 'mountedActionSchema0')
        ->assertFormSet([
            'pull_command' => OllamaSettings::recommendedPullCommand(),
        ], 'mountedActionSchema0');
});

test('ollama setup modal shows a poppler guide when pdf binaries are empty', function (): void {
    $component = Livewire::test(OllamaPage::class)
        ->set('pdfInfoBinary', '')
        ->set('pdfToCairoBinary', '')
        ->set('pdfToTextBinary', '')
        ->set('detectionState', 'running')
        ->mountAction('configureSetup')
        ->assertActionMounted('configureSetup');

    $schema = $component->instance()->getSchema('mountedActionSchema0');
    $pdfinfo = $schema?->getComponent('pdfinfo_binary');
    $emptyGuide = $schema?->getComponent('popplerEmptyGuide', withHidden: true);

    expect($schema?->getComponent('popplerGuide'))->not->toBeNull()
        ->and($emptyGuide)->not->toBeNull()
        ->and($emptyGuide?->isHidden())->toBeFalse()
        ->and($pdfinfo)->toBeInstanceOf(TextInput::class)
        ->and($pdfinfo?->getPlaceholder())->toContain('pdfinfo.exe');

    expect(view('filament.pages.partials.ollama-poppler-guide')->render())
        ->toContain('PDF WhatsApp receipts need Poppler. Image receipts parse without it.')
        ->and(view('filament.pages.partials.ollama-poppler-empty-guide')->render())
        ->toContain('Run Download Poppler for Windows, then Auto-detect.');

    $download = $schema?->getComponent(
        fn (mixed $component): bool => $component instanceof Action && $component->getName() === 'downloadPoppler',
        withActions: true,
        withHidden: true,
    );
    $skip = $schema?->getComponent(
        fn (mixed $component): bool => $component instanceof Action && $component->getName() === 'skipPoppler',
        withActions: true,
        withHidden: true,
    );

    expect($download)->not->toBeNull()
        ->and($download?->isHidden())->toBeFalse()
        ->and($download instanceof Action ? $download->getUrl() : null)->toBe(PopplerDetector::WINDOWS_DOWNLOAD_URL)
        ->and($skip)->not->toBeNull()
        ->and($skip?->isHidden())->toBeFalse();
});

test('ollama setup auto-detect notifies when poppler is missing', function (): void {
    Livewire::test(OllamaPage::class)
        ->call('detectPopplerBinaries')
        ->assertNotified('Failed to find Poppler');
});

test('ollama setup auto-detect notifies when poppler is found', function (): void {
    $stagedPaths = stagePopplerBinariesOnPath(['pdfinfo', 'pdftocairo', 'pdftotext']);

    Livewire::test(OllamaPage::class)
        ->call('detectPopplerBinaries')
        ->assertSet('pdfInfoBinary', $stagedPaths[0])
        ->assertSet('pdfToCairoBinary', $stagedPaths[1])
        ->assertSet('pdfToTextBinary', $stagedPaths[2])
        ->assertNotified('Poppler detected');
});

test('ollama setup auto-detect notifies when poppler is partial', function (): void {
    $stagedPaths = stagePopplerBinariesOnPath(['pdfinfo']);

    Livewire::test(OllamaPage::class)
        ->call('detectPopplerBinaries')
        ->assertSet('pdfInfoBinary', $stagedPaths[0])
        ->assertNotified('Poppler partially detected');
});

test('ollama setup modal hides the empty poppler guide when pdf binaries are set', function (): void {
    $component = Livewire::test(OllamaPage::class)
        ->set('pdfInfoBinary', 'C:\\poppler\\pdfinfo.exe')
        ->set('pdfToCairoBinary', 'C:\\poppler\\pdftocairo.exe')
        ->set('pdfToTextBinary', 'C:\\poppler\\pdftotext.exe')
        ->set('detectionState', 'running')
        ->mountAction('configureSetup')
        ->assertActionMounted('configureSetup');

    $schema = $component->instance()->getSchema('mountedActionSchema0');
    $emptyGuide = $schema?->getComponent('popplerEmptyGuide', withHidden: true);
    $download = $schema?->getComponent(
        fn (mixed $component): bool => $component instanceof Action && $component->getName() === 'downloadPoppler',
        withActions: true,
        withHidden: true,
    );
    $skip = $schema?->getComponent(
        fn (mixed $component): bool => $component instanceof Action && $component->getName() === 'skipPoppler',
        withActions: true,
        withHidden: true,
    );

    expect($emptyGuide)->not->toBeNull()
        ->and($emptyGuide?->isHidden())->toBeTrue()
        ->and($download)->not->toBeNull()
        ->and($download?->isHidden())->toBeTrue()
        ->and($skip)->not->toBeNull()
        ->and($skip?->isHidden())->toBeTrue();
});

test('ollama setup modal hides skip poppler when any pdf binary is set', function (): void {
    $component = Livewire::test(OllamaPage::class)
        ->set('pdfInfoBinary', 'C:\\poppler\\pdfinfo.exe')
        ->set('pdfToCairoBinary', '')
        ->set('pdfToTextBinary', '')
        ->set('detectionState', 'running')
        ->mountAction('configureSetup')
        ->assertActionMounted('configureSetup');

    $skip = $component->instance()->getSchema('mountedActionSchema0')?->getComponent(
        fn (mixed $component): bool => $component instanceof Action && $component->getName() === 'skipPoppler',
        withActions: true,
        withHidden: true,
    );

    expect($skip)->not->toBeNull()
        ->and($skip?->isHidden())->toBeTrue();
});

test('ollama pull command copy action uses the clipboard helper', function (): void {
    $component = Livewire::test(OllamaPage::class)
        ->set('availableModels', [])
        ->set('connectionStatus', 'degraded')
        ->set('detectionState', 'running')
        ->mountAction('configureSetup');

    $field = $component->instance()->getSchema('mountedActionSchema0')?->getComponent('pull_command');
    $copyAction = $field instanceof TextInput
        ? ($field->getSuffixActions()['copyPullCommand'] ?? null)
        : null;
    $handler = $copyAction?->getAlpineClickHandler();
    $command = OllamaSettings::recommendedPullCommand();

    expect($handler)->not->toBeNull()
        ->and($handler)
        ->toContain('window.tidoCopyToClipboard')
        ->toContain(Js::from($command)->toHtml())
        ->toContain('Copied')
        ->not->toContain('navigator.clipboard');
});

test('ollama page run test extraction shows success with mocked ollama', function (): void {
    Http::fake([
        'http://ollama.test/api/tags' => Http::response(fakeOllamaTagsResponse()),
        'http://ollama.test/api/generate' => Http::response([
            'response' => json_encode([
                'merchant_name' => 'Sample Mart',
                'total_amount' => 12.50,
            ]),
        ]),
    ]);

    Livewire::test(OllamaPage::class)
        ->call('runTestExtraction')
        ->assertSet('testExtractionResult.success', true)
        ->assertSet('testExtractionResult.merchant', 'Sample Mart')
        ->assertNotified('Test extraction succeeded');
});

test('family member cannot access ollama page', function (): void {
    $familyMember = FamilyMember::factory()->loginEnabled()->create();
    $familyMemberUser = User::query()
        ->where('family_member_id', $familyMember->getKey())
        ->firstOrFail();

    $this->actingAs($familyMemberUser);

    expect(OllamaPage::canAccess())->toBeFalse()
        ->and(OllamaPage::shouldRegisterNavigation())->toBeFalse();

    $this->get(OllamaPage::getUrl())
        ->assertRedirect();
});

test('ollama page is in integrations navigation group', function (): void {
    expect(OllamaPage::getNavigationGroup())->toBe('Integrations')
        ->and(OllamaPage::getNavigationSort())->toBe(10)
        ->and(OllamaPage::getNavigationIcon())->toBe('icon-ollama');
});

test('header action group test connection calls recheckDetection and notifies', function (): void {
    Http::fake([
        'http://ollama.test/api/tags' => Http::response(fakeOllamaTagsResponse()),
    ]);

    Livewire::test(OllamaPage::class)
        ->callAction('testConnection')
        ->assertNotified('Connection successful');
});

test('header action group run test extraction is disabled when connection is not operational', function (): void {
    Livewire::test(OllamaPage::class)
        ->set('connectionStatus', 'degraded')
        ->assertActionDisabled('runTestExtraction');
});

test('header action group run test extraction is enabled when operational', function (): void {
    Livewire::test(OllamaPage::class)
        ->set('connectionStatus', 'operational')
        ->assertActionEnabled('runTestExtraction');
});

test('header action group try start ollama is disabled when already operational', function (): void {
    Livewire::test(OllamaPage::class)
        ->set('connectionStatus', 'operational')
        ->assertActionDisabled('tryStartOllama');
});

test('header action group try start ollama is enabled when not operational', function (): void {
    Livewire::test(OllamaPage::class)
        ->set('connectionStatus', 'degraded')
        ->assertActionEnabled('tryStartOllama');
});

test('header action group recheck poppler is disabled when all binaries are set', function (): void {
    Livewire::test(OllamaPage::class)
        ->set('pdfInfoBinary', 'C:\\poppler\\pdfinfo.exe')
        ->set('pdfToCairoBinary', 'C:\\poppler\\pdftocairo.exe')
        ->set('pdfToTextBinary', 'C:\\poppler\\pdftotext.exe')
        ->assertActionDisabled('recheckPoppler');
});

test('header action group recheck poppler is enabled when binaries are missing', function (): void {
    Livewire::test(OllamaPage::class)
        ->set('pdfInfoBinary', '')
        ->set('pdfToCairoBinary', '')
        ->set('pdfToTextBinary', '')
        ->assertActionEnabled('recheckPoppler');
});
