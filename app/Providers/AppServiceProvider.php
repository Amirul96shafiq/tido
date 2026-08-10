<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Notifications\Notification as AppNotification;
use App\Helpers\MoneyDisplay;
use App\Helpers\UserDateDisplay;
use App\Http\Middleware\LogLivewireUpdates;
use App\Http\Responses\LogoutResponse;
use App\Listeners\RegisterScheduledBackupCatalog;
use App\Models\Expense;
use App\Models\FamilyMember;
use App\Models\User;
use App\Observers\ExpenseObserver;
use App\Observers\FamilyMemberObserver;
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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
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

        Expense::observe(ExpenseObserver::class);
        FamilyMember::observe(FamilyMemberObserver::class);

        Event::listen(BackupWasSuccessful::class, RegisterScheduledBackupCatalog::class);

        Livewire::setUpdateRoute(function ($handle, $path) {
            return Route::post('/livewire/update', $handle)
                ->middleware(['web', LogLivewireUpdates::class]);
        });

        $this->configureFilamentDateFormats();
        $this->configureFilamentMoneyFormatting();
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
                ->deferColumnManager(false)
                ->filtersFormMaxHeight('min(40vh, 20rem)')
                ->columnManagerMaxHeight('min(40vh, 20rem)')
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
