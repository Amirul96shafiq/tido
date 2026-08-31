<?php

declare(strict_types=1);

use App\Enums\UserDateFormat;
use App\Filament\Forms\Components\DateOfBirthPicker;
use App\Filament\Resources\Backups\Pages\ListBackups;
use App\Helpers\UserDateDisplay;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create([
        'date_format' => UserDateFormat::DmySlash->value,
    ]));
});

test('date time pickers default to the javascript picker', function (): void {
    expect(DatePicker::make('from')->isNative())->toBeFalse()
        ->and(DateTimePicker::make('published_at')->isNative())->toBeFalse()
        ->and(TimePicker::make('alarm_at')->isNative())->toBeFalse();
});

test('date time pickers default to format-aware placeholders', function (): void {
    expect(DatePicker::make('from')->getPlaceholder())->toBe('dd/mm/yyyy')
        ->and(DateTimePicker::make('published_at')->getPlaceholder())->toBe('dd/mm/yyyy HH:mm')
        ->and(TimePicker::make('alarm_at')->getPlaceholder())->toBe('HH:mm');
});

test('date time pickers hide seconds and use the preferred datetime format', function (): void {
    expect(DateTimePicker::make('published_at')->hasSeconds())->toBeFalse()
        ->and(DateTimePicker::make('published_at')->getDisplayFormat())->toBe(UserDateDisplay::dateTimeFormat())
        ->and(DateTimePicker::make('published_at')->getDisplayFormat())->toBe('d/m/Y H:i');
});

test('date time picker display format follows the preferred date format', function (string $format, string $expected): void {
    $this->actingAs(User::factory()->create([
        'date_format' => $format,
    ]));

    expect(DateTimePicker::make('published_at')->getDisplayFormat())->toBe($expected);
})->with([
    'dmy slash' => [UserDateFormat::DmySlash->value, 'd/m/Y H:i'],
    'dmy long' => [UserDateFormat::DmyLong->value, 'd M Y H:i'],
    'iso' => [UserDateFormat::Iso->value, 'Y-m-d H:i'],
]);

test('date of birth picker is a non-native date field capped at today', function (): void {
    $field = DateOfBirthPicker::make();

    expect($field->getLabel())->toBe('Date of Birth')
        ->and($field->isNative())->toBeFalse()
        ->and($field->getPlaceholder())->toBe('dd/mm/yyyy')
        ->and($field->getMaxDate())->not->toBeNull()
        ->and(Carbon::parse((string) $field->getMaxDate())->isSameDay(now()))->toBeTrue();
});

test('date placeholders follow the preferred date format', function (string $format, string $expected): void {
    $this->actingAs(User::factory()->create([
        'date_format' => $format,
    ]));

    expect(UserDateDisplay::datePlaceholder())->toBe($expected)
        ->and(DatePicker::make('from')->getPlaceholder())->toBe($expected);
})->with([
    'dmy slash' => [UserDateFormat::DmySlash->value, 'dd/mm/yyyy'],
    'dmy long' => [UserDateFormat::DmyLong->value, 'dd M yyyy'],
    'iso' => [UserDateFormat::Iso->value, 'yyyy-mm-dd'],
]);

test('backups date filters render the javascript date picker', function (): void {
    $html = Livewire::test(ListBackups::class)
        ->assertSuccessful()
        ->html();

    expect($html)
        ->toContain('dateTimePickerFormComponent')
        ->toContain('fi-fo-date-time-picker-display-text-input')
        ->toContain('placeholder="dd/mm/yyyy"')
        ->not->toContain('type="date"');
});

test('filter dropdown css keeps overflow hidden and pins date picker panels fixed', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $js = (string) file_get_contents(resource_path('js/date-picker-month-select.js'));

    expect($css)
        ->toContain('.fi-fixed-positioning-context .fi-fo-date-time-picker-panel')
        ->toContain('z-index: 40;')
        ->not->toContain('.fi-ta-filters-body:has(');

    expect($js)
        ->toContain('pinDatePickerPanelFixed')
        ->toContain('setProperty("position", "fixed", "important")')
        ->toContain('getFixedCoordsForTrigger')
        ->toContain('.fi-gsm-toolbar');
});
