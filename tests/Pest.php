<?php

use App\Filament\Support\DashboardMonthAnalytics;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->beforeEach(function (): void {
    DashboardMonthAnalytics::flushInstances();
    config([
        'services.currencyapi.api_key' => '',
        'services.currencyapi.base_url' => 'https://currencyapi.test',
    ]);
    Http::fake([
        'api.currencyapi.com/*' => Http::response(['message' => 'blocked in tests'], 401),
    ]);
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * @return array{Authorization: string}
 */
function evolutionWebhookHeaders(?string $secret = null): array
{
    return [
        'Authorization' => 'Bearer '.($secret ?? (string) config('services.evolution.webhook_secret')),
    ];
}

function livewireDeferredListPage(string $pageClass): Testable
{
    return Livewire::test($pageClass)->loadTable();
}

function livewireDeferredTablePage(string $pageClass): Testable
{
    return Livewire::test($pageClass)->loadTable();
}

function deferredFormSchemaKey(string $sectionKey): string
{
    return "form.{$sectionKey}";
}

function deferredFormComponentKey(string $sectionKey, string $field): string
{
    return "{$sectionKey}.{$field}";
}

function deferredExpenseItemSchemaKey(int|string $itemKey): string
{
    return "expenseItems.record-{$itemKey}";
}

function loadDeferredExpenseItemSchema(Testable $test, int|string $itemKey): Testable
{
    return loadDeferredFormSchemas($test, deferredExpenseItemSchemaKey($itemKey));
}

function loadDeferredFormSchemas(Testable $test, string ...$sectionKeys): Testable
{
    foreach ($sectionKeys as $sectionKey) {
        $test->call('loadDeferredSchema', deferredFormSchemaKey($sectionKey));
    }

    $instance = $test->instance();

    if (method_exists($instance, 'getDefaultTestingSchemaName')) {
        $schemaName = $instance->getDefaultTestingSchemaName();
        $instance->{$schemaName}->flushCachedHierarchy();
    }

    return $test;
}
