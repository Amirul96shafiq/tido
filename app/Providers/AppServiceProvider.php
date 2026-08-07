<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Notifications\Notification as AppNotification;
use App\Helpers\MoneyDisplay;
use App\Helpers\UserDateDisplay;
use App\Http\Middleware\LogLivewireUpdates;
use App\Http\Responses\LogoutResponse;
use App\Listeners\RegisterScheduledBackupCatalog;
use App\Models\FamilyMember;
use App\Models\Invoice;
use App\Models\User;
use App\Observers\FamilyMemberObserver;
use App\Observers\InvoiceObserver;
use App\Services\Currency\CurrencyApiExchangeRateProvider;
use App\Services\Currency\ExchangeRateProvider;
use App\View\Components\ButtonComponent;
use BladeUI\Icons\Factory;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Icons\Heroicon;
use Filament\Support\View\Components\ButtonComponent as FilamentButtonComponent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Google\Client;
use Google\Service\Drive;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Livewire\Livewire;
use Masbug\Flysystem\GoogleDriveAdapter;
use Spatie\Backup\Events\BackupWasSuccessful;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FilamentButtonComponent::class, ButtonComponent::class);
        $this->app->bind(FilamentNotification::class, AppNotification::class);
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
        $this->app->bind(ExchangeRateProvider::class, CurrencyApiExchangeRateProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->callAfterResolving(Factory::class, function (Factory $factory): void {
            $factory->add('tido', [
                'path' => resource_path('svg'),
                'prefix' => 'icon',
            ]);
        });

        Invoice::observe(InvoiceObserver::class);
        FamilyMember::observe(FamilyMemberObserver::class);

        Event::listen(BackupWasSuccessful::class, RegisterScheduledBackupCatalog::class);

        Livewire::setUpdateRoute(function ($handle, $path) {
            return Route::post('/livewire/update', $handle)
                ->middleware(['web', LogLivewireUpdates::class]);
        });

        $this->configureFilamentDateFormats();
        $this->configureFilamentMoneyFormatting();

        try {
            Storage::extend('google', function ($app, $config) {
                if (empty($config['clientId']) || empty($config['clientSecret']) || empty($config['refreshToken'])) {
                    $adapter = new LocalFilesystemAdapter(storage_path('app/private'));
                    $driver = new Filesystem($adapter);

                    return new FilesystemAdapter($driver, $adapter);
                }

                $client = new Client;
                $client->setClientId($config['clientId']);
                $client->setClientSecret($config['clientSecret']);
                $client->refreshToken($config['refreshToken']);

                $service = new Drive($client);
                $adapter = new GoogleDriveAdapter($service, $config['folderId'] ?? '/');
                $driver = new Filesystem($adapter);

                return new FilesystemAdapter($driver, $adapter);
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to load Google Drive driver: '.$e->getMessage());
        }
    }

    protected function configureFilamentDateFormats(): void
    {
        FilamentTimezone::set(function (): string {
            $user = auth()->user();

            if ($user instanceof User) {
                return $user->preferredTimezone();
            }

            return (string) config('app.timezone');
        });

        Table::configureUsing(function (Table $table): void {
            $table
                ->defaultDateDisplayFormat(fn (): string => UserDateDisplay::dateFormat())
                ->defaultDateTimeDisplayFormat(fn (): string => UserDateDisplay::dateTimeFormat())
                ->deferFilters(false)
                ->modifyUngroupedRecordActionsUsing(fn (Action $action) => $action
                    ->iconButton()
                    ->tooltip(fn (Action $action): ?string => $action->getLabel()))
                ->filtersTriggerAction(fn (Action $action): Action => $action
                    ->tooltip(fn (Action $action): ?string => $action->getLabel()))
                ->columnManagerTriggerAction(fn (Action $action): Action => $action
                    ->tooltip(fn (Action $action): ?string => $action->getLabel()));
        });

        CreateAction::configureUsing(function (CreateAction $action): void {
            $action->icon(Heroicon::Plus);
        });

        Schema::configureUsing(function (Schema $schema): void {
            $schema
                ->defaultDateDisplayFormat(fn (): string => UserDateDisplay::dateFormat())
                ->defaultDateTimeDisplayFormat(fn (): string => UserDateDisplay::dateTimeFormat());
        });

        DateTimePicker::configureUsing(function (DateTimePicker $component): void {
            $component
                ->defaultDateDisplayFormat(fn (): string => UserDateDisplay::dateFormat())
                ->defaultDateTimeDisplayFormat(fn (): string => UserDateDisplay::dateTimeFormat());
        });
    }

    protected function configureFilamentMoneyFormatting(): void
    {
        TextColumn::macro('myr', function (): TextColumn {
            return MoneyDisplay::configureTextColumn($this);
        });

        TextInput::macro('myr', function (): TextInput {
            return MoneyDisplay::configureTextInput($this);
        });
    }
}
