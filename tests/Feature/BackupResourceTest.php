<?php

declare(strict_types=1);

use App\Enums\BackupType;
use App\Filament\Resources\Backups\BackupResource;
use App\Filament\Resources\Backups\Pages\ListBackups;
use App\Models\Backup;
use App\Models\User;
use App\Services\BackupService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.evolution.personal_number' => null,
    ]);

    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
    Storage::fake('local');
});

test('authenticated user can load backups list', function () {
    $this->get(BackupResource::getUrl('index'))
        ->assertSuccessful();
});

test('create backup header action registers a manual backup', function () {
    $backup = Backup::factory()->make([
        'type' => BackupType::Manual,
        'filename' => 'tido-app-local-2026-07-14-100413-manual.zip',
        'created_by' => $this->admin->getKey(),
    ]);

    $this->mock(BackupService::class, function ($mock) use ($backup): void {
        $mock->shouldReceive('create')
            ->once()
            ->with(BackupType::Manual, $this->admin)
            ->andReturn($backup);
    });

    Livewire::test(ListBackups::class)
        ->callAction('createBackup')
        ->assertNotified();

    $this->admin->refresh();

    expect($this->admin->notifications()->count())->toBe(1);
    expect($this->admin->notifications()->first()->data['title'])->toBe('Backup created');
});

test('delete backup stores database notification', function () {
    $backup = Backup::factory()->create([
        'disk' => 'local',
        'path' => 'laravel-backup/test-backup.zip',
        'filename' => 'tido-app-local-2026-07-14-100413-manual.zip',
    ]);

    Storage::disk('local')->put($backup->path, 'zip-contents');

    Livewire::test(ListBackups::class)
        ->callAction(TestAction::make('delete')->table($backup))
        ->assertNotified();

    $this->admin->refresh();

    expect($this->admin->notifications()->count())->toBe(1);
    expect($this->admin->notifications()->first()->data['title'])->toBe('Backup deleted');
});

test('delete backup action removes catalog entry via service', function () {
    $backup = Backup::factory()->create([
        'disk' => 'local',
        'path' => 'laravel-backup/test-backup.zip',
    ]);

    Storage::disk('local')->put($backup->path, 'zip-contents');

    $this->mock(BackupService::class, function ($mock) use ($backup): void {
        $mock->shouldReceive('delete')
            ->once()
            ->withArgs(fn (Backup $record): bool => $record->is($backup));
    });

    Livewire::test(ListBackups::class)
        ->callAction(TestAction::make('delete')->table($backup))
        ->assertNotified();
});

test('restore backup action logs user out to login', function () {
    $backup = Backup::factory()->create([
        'filename' => 'tido-app-local-2026-07-14-100413-manual.zip',
    ]);

    $this->mock(BackupService::class, function ($mock) use ($backup): void {
        $mock->shouldReceive('restore')
            ->once()
            ->withArgs(fn (Backup $record): bool => $record->is($backup));
    });

    Livewire::test(ListBackups::class)
        ->callAction(TestAction::make('restore')->table($backup))
        ->assertRedirect('/admin/login');

    $this->admin->refresh();

    expect($this->admin->notifications()->count())->toBe(1);
    expect($this->admin->notifications()->first()->data['title'])->toBe('Backup restored');

    $this->assertGuest();
});

test('backups table has download action', function () {
    $backup = Backup::factory()->create([
        'disk' => 'local',
        'path' => 'laravel-backup/tido-app-local-2026-07-14-101010-manual.zip',
        'filename' => 'tido-app-local-2026-07-14-101010-manual.zip',
    ]);

    Storage::disk('local')->put($backup->path, 'zip-contents');

    Livewire::test(ListBackups::class)
        ->assertActionExists(TestAction::make('download')->table($backup));
});

test('backups table can filter by created date', function () {
    $todayBackup = Backup::factory()->create([
        'created_at' => Carbon::parse('2026-07-14 10:00:00'),
    ]);

    $olderBackup = Backup::factory()->create([
        'created_at' => Carbon::parse('2026-07-01 10:00:00'),
    ]);

    Livewire::test(ListBackups::class)
        ->filterTable('created_at', [
            'from' => '2026-07-14',
            'until' => '2026-07-14',
        ])
        ->assertCanSeeTableRecords([$todayBackup])
        ->assertCanNotSeeTableRecords([$olderBackup]);
});

test('backup filename includes app slug environment type and user timezone timestamp', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-14 10:10:10', 'UTC'));

    config([
        'app.name' => 'tido',
        'app.env' => 'local',
    ]);

    $user = User::factory()->create([
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $filename = app(BackupService::class)->buildBackupFilename(BackupType::Manual, $user);

    expect($filename)->toBe('tido-app-local-2026-07-14-181010-manual.zip');

    Carbon::setTestNow();
});

test('backup filename uses staging and production environment slugs', function () {
    $service = app(BackupService::class);

    config(['app.name' => 'tido', 'app.env' => 'staging']);
    expect($service->buildBackupFilename(BackupType::Auto, null))->toStartWith('tido-app-stg-');

    config(['app.name' => 'tido', 'app.env' => 'production']);
    expect($service->buildBackupFilename(BackupType::Auto, null))->toStartWith('tido-app-prod-');
});

test('backup service delete removes file and database row', function () {
    $backup = Backup::factory()->create([
        'disk' => 'local',
        'path' => 'laravel-backup/manual-delete.zip',
    ]);

    Storage::disk('local')->put($backup->path, 'backup-zip');

    app(BackupService::class)->delete($backup);

    expect(Backup::query()->whereKey($backup->getKey())->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists($backup->path))->toBeFalse();
});

test('backup service creates native sqlite backup without sqlite3 cli', function () {
    $databasePath = database_path('testing-native-backup.sqlite');

    if (File::exists($databasePath)) {
        File::delete($databasePath);
    }

    File::copy(database_path('database.sqlite'), $databasePath);

    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $databasePath,
    ]);

    $backup = app(BackupService::class)->create(BackupType::Manual, $this->admin);

    expect($backup->fileExists())->toBeTrue()
        ->and(Backup::query()->whereKey($backup->getKey())->exists())->toBeTrue()
        ->and($backup->filename)->toMatch('/^tido-app-local-\d{4}-\d{2}-\d{2}-\d{6}-manual\.zip$/');

    File::delete($databasePath);
})->skip(fn (): bool => ! file_exists(database_path('database.sqlite')), 'Requires file-backed sqlite database.');
