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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
            'email' => 'sibling@example.com',
            'relationship' => 'sibling',
            'date_of_birth' => '15/05/1990',
            'allowlist_enabled' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $member = FamilyMember::query()->where('name', 'Sibling')->first();

    expect($member)->not->toBeNull()
        ->and($member->phone)->toBe('60116330785')
        ->and($member->display_name)->toBe('Sib')
        ->and($member->email)->toBe('sibling@example.com')
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

test('email is optional when creating a family member', function () {
    Livewire::test(CreateFamilyMember::class)
        ->fillForm([
            'name' => 'Cousin',
            'phone' => '+60116330791',
            'email' => null,
            'allowlist_enabled' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(FamilyMember::query()->where('name', 'Cousin')->first()?->email)->toBeNull();
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

test('list page can toggle allowlist column', function () {
    $member = FamilyMember::factory()->create([
        'phone' => '60111111111',
        'allowlist_enabled' => true,
    ]);

    Livewire::test(ListFamilyMembers::class)
        ->assertSuccessful()
        ->call('updateTableColumnState', 'allowlist_enabled', (string) $member->getKey(), false);

    expect($member->fresh()->allowlist_enabled)->toBeFalse()
        ->and(PhoneNumber::isAllowedWhatsAppSender('60111111111'))->toBeFalse();
});

test('family members table has view slide-over action', function () {
    $member = FamilyMember::factory()->create();

    Livewire::test(ListFamilyMembers::class)
        ->assertSuccessful()
        ->assertActionExists(TestAction::make('view')->table($member));
});

test('family member form uses details plus profile photo sidebar layout', function () {
    $schema = FamilyMemberForm::configure(Schema::make()->columns(2));
    $components = $schema->getComponents();

    expect($schema->getColumns('lg'))->toBe(10)
        ->and($components)->toHaveCount(2)
        ->and($components[0])->toBeInstanceOf(Grid::class)
        ->and($components[0]->getColumnSpan('lg'))->toBe(7)
        ->and($components[1])->toBeInstanceOf(Section::class)
        ->and($components[1]->getColumnSpan('lg'))->toBe(3)
        ->and($components[1]->getHeading())->toBe('Profile Photo');
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
