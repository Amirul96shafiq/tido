<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user menu orders theme before profile changelogs and logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $items = Filament::getUserMenuItems();
    $keys = array_keys($items);

    expect($keys)->toBe(['profile', 'changelogs', 'logout']);

    expect($items['profile']->getIcon())->toBe('heroicon-o-user-circle');
    expect($items['profile']->getSort())->toBeGreaterThanOrEqual(0);
    expect($items['changelogs']->getLabel())->toBe('Changelogs');
    expect($items['changelogs']->getSort())->toBeGreaterThan($items['profile']->getSort());
    expect($items['logout']->getSort())->toBeGreaterThan($items['changelogs']->getSort());
});
