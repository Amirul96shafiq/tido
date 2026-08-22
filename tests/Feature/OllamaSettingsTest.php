<?php

declare(strict_types=1);

use App\Enums\OllamaDetectionState;
use App\Models\OllamaSetting;
use App\Services\Health\Probes\OllamaProbe;
use App\Services\Ollama\OllamaDetector;
use App\Services\Ollama\OllamaSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.ollama.host' => 'http://ollama.test',
        'services.ollama.model' => 'qwen2.5vl:7b',
    ]);
});

test('ollama settings resolver prefers database values over env', function (): void {
    OllamaSetting::singleton()->update([
        'host' => 'http://saved.test',
        'model' => 'saved-model:7b',
        'timeout' => 90,
    ]);

    $settings = app(OllamaSettings::class);

    expect($settings->host())->toBe('http://saved.test')
        ->and($settings->model())->toBe('saved-model:7b')
        ->and($settings->timeout())->toBe(90)
        ->and($settings->usesSavedSettings())->toBeTrue();
});

test('ollama settings resolver falls back to env when database is empty', function (): void {
    $settings = app(OllamaSettings::class);

    expect($settings->host())->toBe('http://ollama.test')
        ->and($settings->model())->toBe('qwen2.5vl:7b')
        ->and($settings->usesSavedSettings())->toBeFalse();
});

test('ollama detector reports running when tags endpoint succeeds', function (): void {
    Http::fake([
        'http://ollama.test/api/tags' => Http::response(['models' => []]),
    ]);

    Process::fake([
        '*' => Process::result(exitCode: 1),
    ]);

    $probe = app(OllamaDetector::class)->probe('http://ollama.test');

    expect($probe['state'])->toBe(OllamaDetectionState::Running)
        ->and($probe['modelCount'])->toBe(0);
});

test('ollama detector reports not installed when cli and http both fail on localhost', function (): void {
    Http::fake([
        'http://127.0.0.1:11434/api/tags' => Http::response(null, 500),
    ]);

    $detector = Mockery::mock(OllamaDetector::class, [app(OllamaSettings::class)])->makePartial();
    $detector->shouldReceive('resolveCliPath')->andReturn(null);
    app()->instance(OllamaDetector::class, $detector);

    $probe = app(OllamaDetector::class)->probe('http://127.0.0.1:11434');

    expect($probe['state'])->toBe(OllamaDetectionState::NotInstalled);
});

test('ollama detector reports installed stopped when cli exists but http fails on localhost', function (): void {
    Http::fake([
        'http://127.0.0.1:11434/api/tags' => Http::response(null, 500),
    ]);

    $detector = Mockery::mock(OllamaDetector::class, [app(OllamaSettings::class)])->makePartial();
    $detector->shouldReceive('resolveCliPath')->andReturn('C:\\Ollama\\ollama.exe');
    app()->instance(OllamaDetector::class, $detector);

    $probe = app(OllamaDetector::class)->probe('http://127.0.0.1:11434');

    expect($probe['state'])->toBe(OllamaDetectionState::InstalledStopped)
        ->and($probe['cliPath'])->toBe('C:\\Ollama\\ollama.exe');
});

test('recommended pull command targets the vision model', function (): void {
    expect(OllamaSettings::recommendedPullCommand())->toBe('ollama pull qwen2.5vl:7b');
});

test('vision model tiers returns three entries in recommended order', function (): void {
    $tiers = OllamaSettings::visionModelTiers();

    expect($tiers)->toHaveCount(3)
        ->and($tiers[0]['name'])->toBe('qwen2.5vl:7b')
        ->and($tiers[0]['tier'])->toBe('recommended')
        ->and($tiers[1]['name'])->toBe('minicpm-v')
        ->and($tiers[1]['tier'])->toBe('lighter')
        ->and($tiers[2]['name'])->toBe('moondream')
        ->and($tiers[2]['tier'])->toBe('minimal');
});

test('pull command helper formats ollama pull for lighter models', function (): void {
    expect(OllamaSettings::pullCommandFor('minicpm-v'))->toBe('ollama pull minicpm-v')
        ->and(OllamaSettings::pullCommandFor('moondream'))->toBe('ollama pull moondream');
});

test('select option label includes tier hints for known models', function (): void {
    expect(OllamaSettings::selectOptionLabel('minicpm-v'))->toBe('minicpm-v (Lighter · ~4 GB VRAM)')
        ->and(OllamaSettings::selectOptionLabel('custom-model'))->toBe('custom-model');
});

test('select options for models sort tier models before other installed models', function (): void {
    $options = OllamaSettings::selectOptionsForModels([
        ['name' => 'llama3:8b'],
        ['name' => 'moondream'],
        ['name' => 'qwen2.5vl:7b'],
        ['name' => 'minicpm-v'],
    ]);

    expect(array_keys($options))->toBe([
        'qwen2.5vl:7b',
        'minicpm-v',
        'moondream',
        'llama3:8b',
    ]);
});

test('ollama probe uses saved host from settings', function (): void {
    OllamaSetting::singleton()->update(['host' => 'http://saved.test']);

    Http::fake([
        'http://saved.test/api/tags' => Http::response(['models' => [['name' => 'qwen2.5vl:7b']]]),
    ]);

    $result = app(OllamaProbe::class)->probe();

    expect($result->status->value)->toBe('operational');
});
