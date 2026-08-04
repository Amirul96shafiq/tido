<?php

declare(strict_types=1);

use App\Filament\Livewire\AccountSwitcher;
use App\Models\FamilyMember;
use App\Models\User;
use Filament\AvatarProviders\UiAvatarsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('primary user sees account switcher with login-enabled family members', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create([
        'name' => 'Primary Account',
        'display_name' => null,
    ]);
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Sample Spouse',
        'display_name' => null,
    ]);

    $this->actingAs($primary);

    Livewire::test(AccountSwitcher::class)
        ->assertSee('Sample Spouse')
        ->assertSee('Swap Account')
        ->assertSee('fi-account-switcher')
        ->assertSee('fi-account-switcher-account-chevron')
        ->assertSee("mountAction('confirmSwitchTo'", false)
        ->assertSee('tido-single-line-text-clip')
        ->assertSee('x-ref="singleLineText"', false)
        ->assertDontSee('tido-text-marquee', false)
        ->assertDontSee('Primary Account')
        ->assertDontSee('fi-account-switcher-account-active');
});

test('family members without profile photos use the default avatar provider in the account switcher', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create();
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Sample Spouse',
        'avatar_url' => null,
    ]);
    $defaultAvatarUrl = app(UiAvatarsProvider::class)->get($member);

    $this->actingAs($primary);

    Livewire::test(AccountSwitcher::class)
        ->assertSeeHtml('src="'.e($defaultAvatarUrl).'"')
        ->assertDontSee('fi-account-switcher-account-avatar-placeholder', false);
});

test('primary user sees switchable family members newest first', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create();
    $olderMember = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Older Member',
        'display_name' => null,
        'created_at' => now()->subDay(),
    ]);
    $newerMember = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Newer Member',
        'display_name' => null,
        'created_at' => now(),
    ]);

    $this->actingAs($primary);

    Livewire::test(AccountSwitcher::class)
        ->assertSeeInOrder([
            $newerMember->name,
            $olderMember->name,
        ]);
});

test('primary user previews two family members and can reveal the full list', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create();
    $members = collect([
        FamilyMember::factory()->loginEnabled()->create(['name' => 'First Member']),
        FamilyMember::factory()->loginEnabled()->create(['name' => 'Second Member']),
        FamilyMember::factory()->loginEnabled()->create(['name' => 'Third Member']),
    ]);

    $this->actingAs($primary);

    $html = Livewire::test(AccountSwitcher::class)->html();

    expect($html)
        ->toContain('View All Family Members')
        ->toContain('aria-controls="account-switcher-all-members"')
        ->toContain('x-show="allMembersOpen"')
        ->toContain('fi-account-switcher-account-preview-faded')
        ->and(substr_count($html, 'wire:key="account-switcher-preview-member-'))->toBe(2)
        ->and(substr_count($html, 'wire:key="account-switcher-expanded-member-'))->toBe(3);

    expect($members->pluck('name')->all())->each->toBeString();
});

test('primary user does not see switcher when no login-enabled family members exist', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create();

    // Create a family member without login enabled
    FamilyMember::factory()->create(['login_enabled' => false]);

    $this->actingAs($primary);

    Livewire::test(AccountSwitcher::class)
        ->assertDontSee('fi-account-switcher-section');
});

test('family member does not see the account switcher', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Sample Spouse',
    ]);
    $familyUser = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($familyUser);

    Livewire::test(AccountSwitcher::class)
        ->assertDontSee('fi-account-switcher-section');
});

test('primary can switch to a login-enabled family member', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create();
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Sample Spouse',
        'display_name' => 'Spouse',
    ]);
    $familyUser = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($primary);

    Livewire::test(AccountSwitcher::class)
        ->call('switchTo', $member->id)
        ->assertRedirect();

    // Auth should now be the family member user
    expect(auth()->id())->toBe($familyUser->id);
    expect(session()->get(AccountSwitcher::SESSION_KEY))->toBe($primary->id);
});

test('switching to a family member requires the native confirmation modal', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create();
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Sample Spouse',
    ]);
    $familyUser = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($primary);

    $component = Livewire::test(AccountSwitcher::class)
        ->mountAction('confirmSwitchTo', ['familyMemberId' => $member->id])
        ->assertActionMounted('confirmSwitchTo')
        ->assertMountedActionModalSee('Switch account?')
        ->assertMountedActionModalSee('You will be signed in as the selected family member.');

    expect(auth()->id())->toBe($primary->id);

    $component
        ->callMountedAction()
        ->assertRedirect();

    expect(auth()->id())->toBe($familyUser->id);
});

test('switched family member remains authenticated after redirect', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create();
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Sample Spouse',
    ]);
    $familyUser = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($primary);
    session()->put('password_hash_web', $primary->getAuthPassword());

    Livewire::test(AccountSwitcher::class)
        ->call('switchTo', $member->id)
        ->assertRedirect();

    $this->get('/admin')->assertSuccessful();

    expect(auth()->id())->toBe($familyUser->id);
});

test('impersonating user can switch back to primary', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create();
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Sample Spouse',
    ]);
    $familyUser = User::query()->where('family_member_id', $member->id)->firstOrFail();

    // Simulate impersonation state
    $this->actingAs($familyUser);
    session()->put(AccountSwitcher::SESSION_KEY, $primary->id);
    session()->put('password_hash_web', $familyUser->getAuthPassword());

    Livewire::test(AccountSwitcher::class)
        ->call('switchBack')
        ->assertRedirect();

    expect(auth()->id())->toBe($primary->id);
    expect(session()->has(AccountSwitcher::SESSION_KEY))->toBeFalse();

    $this->get('/admin')->assertSuccessful();
});

test('switching back to the primary account requires the native confirmation modal', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create();
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Sample Spouse',
    ]);
    $familyUser = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($familyUser);
    session()->put(AccountSwitcher::SESSION_KEY, $primary->id);

    $component = Livewire::test(AccountSwitcher::class)
        ->mountAction('confirmSwitchBack')
        ->assertActionMounted('confirmSwitchBack')
        ->assertMountedActionModalSee('Switch back to the primary account?')
        ->assertMountedActionModalSee('You will leave the current family member account and return to the primary account.');

    expect(auth()->id())->toBe($familyUser->id);

    $component
        ->callMountedAction()
        ->assertRedirect();

    expect(auth()->id())->toBe($primary->id);
});

test('cannot switch to a family member without login enabled', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create();
    $member = FamilyMember::factory()->create([
        'name' => 'No Login Member',
        'login_enabled' => false,
    ]);

    $this->actingAs($primary);

    Livewire::test(AccountSwitcher::class)
        ->call('switchTo', $member->id)
        ->assertNotDispatched('redirect');

    // Auth should still be primary
    expect(auth()->id())->toBe($primary->id);
    expect(session()->has(AccountSwitcher::SESSION_KEY))->toBeFalse();
});

test('cannot switch to a family member without a linked user', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create();

    // Create a login-enabled member, then delete its linked user
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Orphaned Member',
    ]);
    User::query()->where('family_member_id', $member->id)->delete();

    $this->actingAs($primary);

    Livewire::test(AccountSwitcher::class)
        ->call('switchTo', $member->id)
        ->assertNotDispatched('redirect');

    expect(auth()->id())->toBe($primary->id);
});

test('family member cannot call switchTo', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Sample Spouse',
    ]);
    $familyUser = User::query()->where('family_member_id', $member->id)->firstOrFail();

    // Create another login-enabled member
    $otherMember = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Other Member',
    ]);

    $this->actingAs($familyUser);

    Livewire::test(AccountSwitcher::class)
        ->call('switchTo', $otherMember->id)
        ->assertNotDispatched('redirect');

    // Auth should still be the family member
    expect(auth()->id())->toBe($familyUser->id);
});

test('session key prevents nested impersonation overwrite', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create();

    $member1 = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Spouse One',
    ]);
    $member2 = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Spouse Two',
    ]);
    $familyUser1 = User::query()->where('family_member_id', $member1->id)->firstOrFail();

    // Primary switches to member1
    $this->actingAs($primary);

    Livewire::test(AccountSwitcher::class)
        ->call('switchTo', $member1->id)
        ->assertRedirect();

    expect(session()->get(AccountSwitcher::SESSION_KEY))->toBe($primary->id);

    // Now, while impersonating member1, switch to member2
    $this->actingAs($familyUser1);
    // Session key should still point to the original primary
    expect(session()->get(AccountSwitcher::SESSION_KEY))->toBe($primary->id);

    Livewire::test(AccountSwitcher::class)
        ->call('switchTo', $member2->id)
        ->assertRedirect();

    // Session key should still be the original primary, not member1
    expect(session()->get(AccountSwitcher::SESSION_KEY))->toBe($primary->id);
});

test('impersonating user sees the account list', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create([
        'name' => 'Primary Account',
        'display_name' => null,
    ]);
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Sample Spouse',
        'display_name' => 'Spouse',
    ]);
    $familyUser = User::query()->where('family_member_id', $member->id)->firstOrFail();

    $this->actingAs($familyUser);
    session()->put(AccountSwitcher::SESSION_KEY, $primary->id);

    Livewire::test(AccountSwitcher::class)
        ->assertSee($primary->fresh()->name)
        ->assertSee('Swap Account')
        ->assertDontSee('Spouse')
        ->assertSee('fi-account-switcher-section')
        ->assertSee('fi-account-switcher-account-chevron')
        ->assertSee("mountAction('confirmSwitchBack')", false)
        ->assertDontSee('fi-account-switcher-account-active');
});

test('impersonating user previews two actionable family members', function () {
    $primary = User::factory()->withWhatsAppPhone('60123456789')->create();
    $currentMember = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Current Member',
        'display_name' => null,
    ]);
    $firstOtherMember = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'First Other Member',
        'display_name' => null,
    ]);
    $secondOtherMember = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Second Other Member',
        'display_name' => null,
    ]);
    $currentUser = User::query()->where('family_member_id', $currentMember->id)->firstOrFail();

    $this->actingAs($currentUser);
    session()->put(AccountSwitcher::SESSION_KEY, $primary->id);

    $html = Livewire::test(AccountSwitcher::class)->html();

    expect($html)
        ->toContain($firstOtherMember->name)
        ->toContain($secondOtherMember->name)
        ->not->toContain($currentMember->name)
        ->and(substr_count($html, 'wire:key="account-switcher-preview-member-'))->toBe(2);
});
