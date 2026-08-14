<?php

declare(strict_types=1);

use App\Filament\Livewire\DatabaseNotifications;
use App\Models\User;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('sending a database notification dispatches DatabaseNotificationsSent for that user', function (): void {
    Event::fake([DatabaseNotificationsSent::class]);

    Notification::make()
        ->title('Receipt requires manual review')
        ->sendToDatabase($this->user);

    Event::assertDispatched(DatabaseNotificationsSent::class, function (DatabaseNotificationsSent $event): bool {
        $user = (new ReflectionProperty(DatabaseNotificationsSent::class, 'user'))->getValue($event);

        return $event->broadcastAs() === 'database-notifications.sent'
            && $user instanceof User
            && $user->is($this->user);
    });

    expect($this->user->notifications()->count())->toBe(1);
});

test('database notification echo event is dispatched even when isEventDispatched is false', function (): void {
    Event::fake([DatabaseNotificationsSent::class]);

    Notification::make()
        ->title('Backup created')
        ->sendToDatabase($this->user, isEventDispatched: false);

    Event::assertDispatched(DatabaseNotificationsSent::class);
});

test('empty inbox listens for echo database notifications without polling', function (): void {
    $component = Livewire::test(DatabaseNotifications::class)
        ->assertSuccessful()
        ->assertDontSeeHtml('wire:poll.60s')
        ->assertSeeHtml('.database-notifications.sent')
        ->assertSeeHtml('App.Models.User.'.$this->user->id);

    expect($component->instance()->getPollingInterval())->toBeNull()
        ->and($component->instance()->getBroadcastChannel())
        ->toBe('App.Models.User.'.$this->user->id);
});

test('inbox with notifications listens for echo without polling', function (): void {
    Notification::make()
        ->title('Budget Alert: Food')
        ->sendToDatabase($this->user);

    Livewire::test(DatabaseNotifications::class)
        ->assertSuccessful()
        ->assertDontSeeHtml('wire:poll.60s')
        ->assertSeeHtml('.database-notifications.sent')
        ->assertSeeHtml('Budget Alert: Food');
});
