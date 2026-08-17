<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\EvolutionApiPage;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Resources\FamilyMembers\Pages\CreateFamilyMember;
use App\Filament\Resources\FamilyMembers\Pages\EditFamilyMember;
use App\Filament\Resources\FamilyMembers\Pages\ListFamilyMembers;
use App\Filament\Resources\FamilyMembers\Schemas\FamilyMemberForm;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\PhoneNumber;
use Filament\Actions\Testing\TestAction;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->withWhatsAppPhone('60123456789')->create();
    $this->actingAs($this->admin);
});

test('family members resource is under settings navigation', function () {
    expect(FamilyMemberResource::getNavigationGroup())->toBe('Settings')
        ->and(FamilyMemberResource::getNavigationLabel())->toBe('Family Members')
        ->and(FamilyMemberResource::getNavigationSort())->toBe(3)
        ->and(FamilyMemberResource::getUrl('index'))->toEndWith('/admin/family-members');
});

test('authenticated user can list family members', function () {
    FamilyMember::factory()->create(['name' => 'Spouse']);

    $this->get(FamilyMemberResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('Spouse');
});

test('user can create a family member on the allowlist', function () {
    Livewire::test(CreateFamilyMember::class)
        ->fillForm([
            'name' => 'Sibling',
            'display_name' => 'Sib',
            'phone' => '+60116330785',
            'relationship' => 'sibling',
            'date_of_birth' => '1990-05-15',
            'allowlist_enabled' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $member = FamilyMember::query()->where('name', 'Sibling')->first();

    expect($member)->not->toBeNull()
        ->and($member->phone)->toBe('60116330785')
        ->and($member->display_name)->toBe('Sib')
        ->and($member->relationship?->value)->toBe('sibling')
        ->and($member->date_of_birth?->format('Y-m-d'))->toBe('1990-05-15')
        ->and($member->allowlist_enabled)->toBeTrue()
        ->and(PhoneNumber::isAllowedWhatsAppSender('60116330785'))->toBeTrue();
});

test('user can create a family member with a custom other relationship', function () {
    Livewire::test(CreateFamilyMember::class)
        ->fillForm([
            'name' => 'Along',
            'phone' => '+60116330790',
            'relationship' => 'other',
            'relationship_other' => 'Godfather',
            'allowlist_enabled' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $member = FamilyMember::query()->where('name', 'Along')->first();

    expect($member)->not->toBeNull()
        ->and($member->relationship?->value)->toBe('other')
        ->and($member->relationship_other)->toBe('Godfather')
        ->and($member->relationshipLabel())->toBe('Godfather');
});

test('custom relationship is required when other is selected', function () {
    Livewire::test(CreateFamilyMember::class)
        ->fillForm([
            'name' => 'Along',
            'phone' => '+60116330792',
            'relationship' => 'other',
            'relationship_other' => null,
            'allowlist_enabled' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['relationship_other' => 'required']);
});

test('custom relationship sits beside relationship on one row when other is selected', function () {
    Livewire::test(CreateFamilyMember::class)
        ->assertFormFieldIsHidden('relationship_other')
        ->fillForm(['relationship' => 'other'])
        ->assertFormFieldIsVisible('relationship_other')
        ->fillForm(['relationship' => 'sibling'])
        ->assertFormFieldIsHidden('relationship_other');
});

test('user can upload a family member profile photo', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('spouse-avatar.jpg');

    Livewire::test(CreateFamilyMember::class)
        ->fillForm([
            'name' => 'Spouse',
            'phone' => '+60116330786',
            'allowlist_enabled' => true,
            'avatar_url' => [$file],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $member = FamilyMember::query()->where('name', 'Spouse')->first();

    expect($member)->not->toBeNull()
        ->and($member->avatar_url)->not->toBeNull()
        ->and($member->getFilamentAvatarUrl())->not->toBeNull();

    Storage::disk('public')->assertExists($member->avatar_url);
});

test('edit form preserves date of birth across asia kuala lumpur timezone serialization', function () {
    config(['app.timezone' => 'Asia/Kuala_Lumpur']);

    $member = FamilyMember::factory()->create([
        'name' => 'Spouse',
        'phone' => '60116330799',
        'date_of_birth' => '1988-11-11',
    ]);

    Livewire::test(EditFamilyMember::class, ['record' => $member->getRouteKey()])
        ->assertFormSet([
            'date_of_birth' => '1988-11-11',
        ]);

    $member->refresh();

    expect($member->date_of_birth?->format('Y-m-d'))->toBe('1988-11-11')
        ->and($member->attributesToArray()['date_of_birth'])->toBe('1988-11-11');
});

test('user can replace a family member profile photo on edit', function () {
    Storage::fake('public');

    $member = FamilyMember::factory()->create([
        'name' => 'Spouse',
        'phone' => '60116330787',
        'avatar_url' => null,
    ]);

    $file = UploadedFile::fake()->image('updated-avatar.jpg');

    Livewire::test(EditFamilyMember::class, ['record' => $member->getRouteKey()])
        ->fillForm([
            'avatar_url' => [$file],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $member->refresh();

    expect($member->avatar_url)->not->toBeNull();

    Storage::disk('public')->assertExists($member->avatar_url);
});

test('editing a family member dispatches an account switcher refresh event', function () {
    $member = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Spouse',
        'phone' => '60116330788',
    ]);

    Livewire::test(EditFamilyMember::class, ['record' => $member->getRouteKey()])
        ->fillForm(['display_name' => 'Updated Spouse'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertDispatched('family-member-updated', function (string $event, array $params) use ($member): bool {
            return $event === 'family-member-updated'
                && $params['familyMemberId'] === $member->id;
        });
});

test('disabled family member is excluded from allowlist', function () {
    $member = FamilyMember::factory()->create([
        'phone' => '60111111111',
        'allowlist_enabled' => true,
    ]);

    Livewire::test(EditFamilyMember::class, ['record' => $member->getRouteKey()])
        ->fillForm([
            'allowlist_enabled' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(PhoneNumber::isAllowedWhatsAppSender('60111111111'))->toBeFalse();
});

test('family members table has view slide-over action', function () {
    $member = FamilyMember::factory()->create();

    Livewire::test(ListFamilyMembers::class)
        ->assertSuccessful()
        ->assertActionExists(TestAction::make('view')->table($member));
});

test('family members table filters by contact allowlist and panel login status', function () {
    $allowlisted = FamilyMember::factory()->allowlisted()->create(['name' => 'Allowlisted']);
    $notAllowlisted = FamilyMember::factory()->notAllowlisted()->create(['name' => 'Not allowlisted']);
    $loginEnabled = FamilyMember::factory()->loginEnabled()->create(['name' => 'Login enabled']);
    $loginDisabled = FamilyMember::factory()->create(['name' => 'Login disabled']);
    $trashed = FamilyMember::factory()->create(['name' => 'Trashed']);
    $trashed->delete();

    $component = Livewire::test(ListFamilyMembers::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$allowlisted, $notAllowlisted, $loginEnabled, $loginDisabled])
        ->assertCanNotSeeTableRecords([$trashed])
        ->assertTableFilterExists('trashed', fn ($filter): bool => $filter instanceof TrashedFilter)
        ->assertTableFilterExists('allowlist_enabled', fn ($filter): bool => $filter instanceof TernaryFilter)
        ->assertTableFilterExists('login_enabled', fn ($filter): bool => $filter instanceof TernaryFilter)
        ->filterTable('allowlist_enabled', true)
        ->assertCanSeeTableRecords([$allowlisted, $loginEnabled, $loginDisabled])
        ->assertCanNotSeeTableRecords([$notAllowlisted]);

    $component
        ->filterTable('login_enabled', true)
        ->assertCanSeeTableRecords([$loginEnabled])
        ->assertCanNotSeeTableRecords([$allowlisted, $notAllowlisted, $loginDisabled]);

    Livewire::test(ListFamilyMembers::class)
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$allowlisted, $notAllowlisted, $loginEnabled, $loginDisabled, $trashed])
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$trashed])
        ->assertCanNotSeeTableRecords([$allowlisted, $notAllowlisted, $loginEnabled, $loginDisabled]);
});

test('restoring a soft deleted login-enabled family member restores panel login access', function () {
    $member = FamilyMember::factory()->loginEnabled()->create();
    $member->delete();

    expect(FamilyMember::query()->find($member->getKey()))->toBeNull()
        ->and($member->loginUser()->exists())->toBeFalse();

    $member = FamilyMember::withTrashed()->findOrFail($member->getKey());
    $member->restore();

    expect($member->fresh())->not->toBeNull()
        ->and($member->loginUser()->exists())->toBeTrue();
});

test('trashed family member edit page exposes the restore action', function () {
    $member = FamilyMember::factory()->create();
    $member->delete();

    Livewire::test(EditFamilyMember::class, ['record' => $member->getRouteKey()])
        ->assertSuccessful()
        ->assertActionExists('restore')
        ->assertActionExists('forceDelete');
});

test('family member form uses details plus profile photo sidebar layout', function () {
    $schema = FamilyMemberForm::configure(Schema::make()->columns(2));
    $components = $schema->getComponents();

    expect($schema->getColumns('lg'))->toBe(10)
        ->and($components)->toHaveCount(2)
        ->and($components[0])->toBeInstanceOf(Grid::class)
        ->and($components[0]->getColumnSpan('lg'))->toBe(7)
        ->and($components[1])->toBeInstanceOf(Grid::class)
        ->and($components[1]->getColumnSpan('lg'))->toBe(3)
        ->and(FamilyMemberForm::sectionNavItems())->toBe([
            ['label' => 'Profile Photo', 'id' => 'profile-photo'],
            ['label' => 'Family Member Details', 'id' => 'family-member-details'],
        ]);
});

test('profile whatsapp number is required', function () {
    Livewire::test(EditProfile::class)
        ->set('data.phone', '')
        ->call('save')
        ->assertHasErrors(['data.phone']);
});

test('evolution connect is blocked without contact allowlist', function () {
    User::query()->update(['phone' => null]);
    FamilyMember::query()->delete();

    Http::fake([
        '*/instance/connectionState/*' => Http::response([
            'instance' => ['state' => 'close'],
        ]),
        '*/instance/fetchInstances*' => Http::response([]),
    ]);

    Livewire::test(EvolutionApiPage::class)
        ->assertSuccessful()
        ->assertSee('Contact allowlist required')
        ->assertActionDisabled('connect')
        ->call('generateQr')
        ->assertNotified('Contact allowlist required');
});

test('primary can duplicate a family member with a new WhatsApp number', function () {
    $source = FamilyMember::factory()->loginEnabled()->create([
        'name' => 'Nur Aisyah Ahmad',
        'display_name' => 'Aisyah',
        'phone' => '60111111111',
        'whatsapp_lid' => '11111111111@lid',
        'avatar_url' => 'avatars/aisyah.jpg',
        'allowlist_enabled' => true,
        'relationship' => 'sibling',
        'date_of_birth' => '1991-05-15',
    ]);
    $sourceLoginUser = $source->loginUser()->first();

    $page = Livewire::test(ListFamilyMembers::class)
        ->callAction(TestAction::make('duplicate')->table($source), data: [
            'phone' => '+60122222222',
            'allowlist_enabled' => false,
            'login_enabled' => false,
        ])
        ->assertNotified('Family member duplicated');

    $replica = FamilyMember::query()
        ->where('phone', '60122222222')
        ->first();

    expect($replica)->not->toBeNull();

    $page->assertRedirect(FamilyMemberResource::getUrl('edit', ['record' => $replica]));

    expect($replica->name)->toBe('Nur Aisyah Ahmad (Copy)')
        ->and($replica->display_name)->toBe('Aisyah')
        ->and($replica->relationship?->value)->toBe('sibling')
        ->and($replica->date_of_birth?->toDateString())->toBe('1991-05-15')
        ->and($replica->avatar_url)->toBeNull()
        ->and($replica->whatsapp_lid)->toBeNull()
        ->and($replica->allowlist_enabled)->toBeFalse()
        ->and($replica->login_enabled)->toBeFalse()
        ->and($replica->loginUser()->exists())->toBeFalse()
        ->and($source->fresh()->loginUser->is($sourceLoginUser))->toBeTrue();
});

test('family member duplicate can explicitly enable a new panel login', function () {
    $source = FamilyMember::factory()->create([
        'phone' => '60113333333',
    ]);

    Livewire::test(ListFamilyMembers::class)
        ->callAction(TestAction::make('duplicate')->table($source), data: [
            'phone' => '60114444444',
            'allowlist_enabled' => true,
            'login_enabled' => true,
        ]);

    $replica = FamilyMember::query()
        ->where('phone', '60114444444')
        ->first();

    expect($replica)->not->toBeNull()
        ->and($replica->login_enabled)->toBeTrue()
        ->and($replica->loginUser)->not->toBeNull()
        ->and($replica->loginUser->phone)->toBe('60114444444');
});

test('family member duplicate rejects existing and soft-deleted WhatsApp numbers', function () {
    $source = FamilyMember::factory()->create([
        'phone' => '60115555555',
    ]);
    $existing = FamilyMember::factory()->create([
        'phone' => '60116666666',
    ]);
    $existing->delete();

    Livewire::test(ListFamilyMembers::class)
        ->callAction(TestAction::make('duplicate')->table($source), data: [
            'phone' => '+60116666666',
            'allowlist_enabled' => false,
            'login_enabled' => false,
        ])
        ->assertHasActionErrors(['phone']);

    expect(FamilyMember::withTrashed()->count())->toBe(2);
});

test('family member duplicate action is available on the edit header', function () {
    $member = FamilyMember::factory()->create();

    Livewire::test(EditFamilyMember::class, ['record' => $member->getRouteKey()])
        ->assertActionVisible('duplicate');
});
