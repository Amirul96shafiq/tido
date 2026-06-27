<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Invoice;
use App\Observers\InvoiceObserver;
use Illuminate\Support\ServiceProvider;

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

        try {
            \Illuminate\Support\Facades\Storage::extend('google', function ($app, $config) {
                if (empty($config['clientId']) || empty($config['clientSecret']) || empty($config['refreshToken'])) {
                    $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter(storage_path('app/private'));
                    $driver = new \League\Flysystem\Filesystem($adapter);
                    return new \Illuminate\Filesystem\FilesystemAdapter($driver, $adapter);
                }

                $client = new \Google\Client();
                $client->setClientId($config['clientId']);
                $client->setClientSecret($config['clientSecret']);
                $client->refreshToken($config['refreshToken']);

                $service = new \Google\Service\Drive($client);
                $adapter = new \Masbug\Flysystem\GoogleDriveAdapter($service, $config['folderId'] ?? '/');
                $driver = new \League\Flysystem\Filesystem($adapter);

                return new \Illuminate\Filesystem\FilesystemAdapter($driver, $adapter);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to load Google Drive driver: ' . $e->getMessage());
        }
    }
}
