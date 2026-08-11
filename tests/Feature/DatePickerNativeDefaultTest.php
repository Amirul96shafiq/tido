<?php

declare(strict_types=1);

use App\Enums\UserDateFormat;
use App\Filament\Resources\Backups\Pages\ListBackups;
use App\Helpers\UserDateDisplay;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

test('filter dropdown css lets an open date picker panel escape overflow clipping', function (): void {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain(
            '.fi-dropdown-panel.fi-scrollable[style*="display: block"]:has(',
        )
        ->toContain('.fi-fo-date-time-picker-panel[style*="display: block"]')
        ->toContain('.fi-ta-filters-dropdown .fi-fo-date-time-picker-panel')
        ->toContain('z-index: 40;');
});
