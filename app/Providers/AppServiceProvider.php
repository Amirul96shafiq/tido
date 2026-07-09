<?php

declare(strict_types=1);

namespace App\Providers;

use App\Helpers\UserDateDisplay;
use App\Models\Invoice;
use App\Models\User;
use App\Observers\InvoiceObserver;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Table;
use Google\Client;
use Google\Service\Drive;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Masbug\Flysystem\GoogleDriveAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Invoice::observe(InvoiceObserver::class);

        $this->configureFilamentDateFormats();

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
                ->defaultDateTimeDisplayFormat(fn (): string => UserDateDisplay::dateTimeFormat());
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
}
