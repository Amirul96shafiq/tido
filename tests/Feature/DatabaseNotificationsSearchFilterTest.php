<?php

declare(strict_types=1);

use App\Enums\NotificationResource;
use App\Filament\Livewire\DatabaseNotifications;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function seedDatabaseNotifications(User $user): void
{
    Notification::make()
        ->title('Profile Settings Updated')
        ->body('Your name was changed.')
        ->success()
        ->sendToDatabase($user);

    Notification::make()
        ->title('Receipt requires manual review')
        ->body('A receipt from Tesco could not be parsed automatically.')
        ->warning()
        ->sendToDatabase($user);

    Notification::make()
        ->title('WhatsApp Connected')
        ->body('Your WhatsApp instance is connected.')
        ->success()
        ->sendToDatabase($user);

    Notification::make()
        ->title('Budget Alert: Food')
        ->body('Spending exceeded the threshold.')
        ->warning()
        ->sendToDatabase($user);

    $user->notifications()
        ->where('data->title', 'WhatsApp Connected')
        ->update(['read_at' => now()]);

    $user->notifications()
        ->where('data->title', 'Budget Alert: Food')
        ->update([
            'read_at' => now(),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);
}

test('database notifications component is registered on the panel', function () {
    expect(filament()->getDatabaseNotificationsLivewireComponent())
        ->toBe(DatabaseNotifications::class);
});

test('database notifications modal content does not create a nested scrollport', function () {
    seedDatabaseNotifications($this->user);

    Livewire::test(DatabaseNotifications::class)
        ->assertDontSeeHtml('overflow-x: hidden');

    $css = (string) file_get_contents(resource_path('css/app.css'));
    $databaseNotificationsBlock = Str::between(
        $css,
        '.fi-no-database > .fi-modal-close-overlay {',
        '.fi-no-empty-panel {',
    );

    expect($databaseNotificationsBlock)->not->toContain('overflow-x: hidden');
});

test('can search database notifications by title', function () {
    seedDatabaseNotifications($this->user);

    Livewire::test(DatabaseNotifications::class)
        ->set('search', 'Receipt')
        ->assertSee('Receipt requires manual review')
        ->assertDontSee('Profile Settings Updated')
        ->assertDontSee('WhatsApp Connected');
});

test('can search database notifications by body', function () {
    seedDatabaseNotifications($this->user);

    Livewire::test(DatabaseNotifications::class)
        ->set('search', 'Tesco')
        ->assertSee('Receipt requires manual review')
        ->assertDontSee('Profile Settings Updated');
});

test('can toggle filters panel', function () {
    seedDatabaseNotifications($this->user);

    Livewire::test(DatabaseNotifications::class)
        ->assertSet('filtersOpen', false)
        ->call('toggleFilters')
        ->assertSet('filtersOpen', true)
        ->assertSee('Resource')
        ->assertSee('From')
        ->assertSee('Until')
        ->assertSee('Status');
});

test('can filter by resource', function () {
    seedDatabaseNotifications($this->user);

    Livewire::test(DatabaseNotifications::class)
        ->set('filters.resource', NotificationResource::Invoices->value)
        ->assertSee('Receipt requires manual review')
        ->assertDontSee('Profile Settings Updated')
        ->assertDontSee('WhatsApp Connected')
        ->assertDontSee('Budget Alert: Food');
});

test('can filter by unread status', function () {
    seedDatabaseNotifications($this->user);

    Livewire::test(DatabaseNotifications::class)
        ->set('filters.status', 'unread')
        ->assertSee('Profile Settings Updated')
        ->assertSee('Receipt requires manual review')
        ->assertDontSee('WhatsApp Connected')
        ->assertDontSee('Budget Alert: Food');
});

test('can filter by read status', function () {
    seedDatabaseNotifications($this->user);

    Livewire::test(DatabaseNotifications::class)
        ->set('filters.status', 'read')
        ->assertSee('WhatsApp Connected')
        ->assertSee('Budget Alert: Food')
        ->assertDontSee('Profile Settings Updated')
        ->assertDontSee('Receipt requires manual review');
});

test('can filter by from and until dates', function () {
    seedDatabaseNotifications($this->user);

    Livewire::test(DatabaseNotifications::class)
        ->set('filters.from', now()->subDays(6)->toDateString())
        ->set('filters.until', now()->subDays(4)->toDateString())
        ->assertSee('Budget Alert: Food')
        ->assertDontSee('Profile Settings Updated')
        ->assertDontSee('Receipt requires manual review')
        ->assertDontSee('WhatsApp Connected');
});

test('search and filters work together', function () {
    seedDatabaseNotifications($this->user);

    Livewire::test(DatabaseNotifications::class)
        ->set('search', 'Alert')
        ->set('filters.resource', NotificationResource::Budgets->value)
        ->set('filters.status', 'read')
        ->assertSee('Budget Alert: Food')
        ->assertDontSee('Profile Settings Updated')
        ->assertDontSee('Receipt requires manual review');
});

test('shows empty filter message when nothing matches', function () {
    seedDatabaseNotifications($this->user);

    Livewire::test(DatabaseNotifications::class)
        ->set('search', 'no-such-notification')
        ->assertSee('No matches found')
        ->assertSee('Clear search & filters')
        ->assertDontSee('Profile Settings Updated');
});

test('clear search and filters restores notifications', function () {
    seedDatabaseNotifications($this->user);

    Livewire::test(DatabaseNotifications::class)
        ->set('search', 'no-such-notification')
        ->set('filters.status', 'unread')
        ->assertSee('No matches found')
        ->call('clearSearchAndFilters')
        ->assertSet('search', '')
        ->assertSet('filters.status', null)
        ->assertSee('Profile Settings Updated');
});

test('unread badge count ignores search and filters', function () {
    seedDatabaseNotifications($this->user);

    $component = Livewire::test(DatabaseNotifications::class)
        ->set('search', 'Receipt')
        ->set('filters.status', 'unread');

    expect($component->instance()->getUnreadNotificationsCount())->toBe(2);
});

test('reset filters clears applied filters', function () {
    seedDatabaseNotifications($this->user);

    Livewire::test(DatabaseNotifications::class)
        ->set('filters.resource', NotificationResource::Invoices->value)
        ->set('filters.status', 'unread')
        ->assertDontSee('Profile Settings Updated')
        ->call('resetFilters')
        ->assertSee('Profile Settings Updated')
        ->assertSee('Receipt requires manual review');
});
