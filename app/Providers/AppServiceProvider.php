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
use App\Models\ExpenseItem;
use App\Models\FamilyMember;
use App\Models\User;
use App\Observers\ExpenseItemObserver;
use App\Observers\ExpenseObserver;
use App\Observers\FamilyMemberObserver;
use App\Services\Calendar\BirthdayCalendarProvider;
use App\Services\Calendar\CalendarEventAggregator;
use App\Services\Calendar\RecurringDueCalendarProvider;
use App\Services\Currency\CurrencyApiExchangeRateProvider;
use App\Services\Currency\ExchangeRateProvider;
use App\Support\FieldCharacterLimits;
use App\Support\ProductionEnvironmentBaseline;
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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->app->singleton(CalendarEventAggregator::class, function (): CalendarEventAggregator {
            $aggregator = new CalendarEventAggregator;
            $aggregator->register(app(RecurringDueCalendarProvider::class));
            $aggregator->register(app(BirthdayCalendarProvider::class));

            return $aggregator;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ProductionEnvironmentBaseline::assert();

        $this->callAfterResolving(Factory::class, function (Factory $factory): void {
            $factory->add('tido', [
                'path' => resource_path('svg'),
                'prefix' => 'icon',
            ]);
        });

        Expense::observe(ExpenseObserver::class);
        ExpenseItem::observe(ExpenseItemObserver::class);
        FamilyMember::observe(FamilyMemberObserver::class);

        Event::listen(BackupWasSuccessful::class, RegisterScheduledBackupCatalog::class);

        $this->configureGuestRestoreRateLimiter();
        $this->configureWhatsAppWebhookRateLimiter();
        $this->configureEvolutionSendRateLimiter();
        $this->configureOllamaGenerateRateLimiter();
        $this->configureGoogleOAuthRateLimiter();

        Livewire::setUpdateRoute(function ($handle, $path) {
            return Route::post('/livewire/update', $handle)
                ->middleware(['web', LogLivewireUpdates::class]);
        });

        $this->configureFilamentDateFormats();
        $this->configureFilamentMoneyFormatting();
    }

    protected function configureGuestRestoreRateLimiter(): void
    {
        RateLimiter::for('guest-restore', function (Request $request) {
            $perIp = max(1, (int) config('backup.backup.restore.per_ip_attempts_per_minute', 5));
            $global = max(1, (int) config('backup.backup.restore.global_attempts_per_minute', 10));

            $response = function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many restore attempts. Try again later.',
                ], 429, $headers);
            };

            return [
                Limit::perMinute($perIp)
                    ->by('guest-restore:ip:'.$request->ip())
                    ->response($response),
                Limit::perMinute($global)
                    ->by('guest-restore:global')
                    ->response($response),
            ];
        });
    }

    protected function configureWhatsAppWebhookRateLimiter(): void
    {
        RateLimiter::for('whatsapp-webhook', function (Request $request) {
            $perIp = max(1, (int) config('services.evolution.webhook_per_ip_attempts_per_minute', 60));
            $global = max(1, (int) config('services.evolution.webhook_global_attempts_per_minute', 60));

            $response = function (Request $request, array $headers) {
                return response()->json([
                    'error' => 'Too many requests. Try again later.',
                ], 429, $headers);
            };

            return [
                Limit::perMinute($perIp)
                    ->by('whatsapp-webhook:ip:'.$request->ip())
                    ->response($response),
                Limit::perMinute($global)
                    ->by('whatsapp-webhook:global')
                    ->response($response),
            ];
        });
    }

    protected function configureEvolutionSendRateLimiter(): void
    {
        RateLimiter::for('evolution-send', function (): Limit {
            $max = max(1, (int) config('services.evolution.outbound_send_attempts_per_minute', 30));

            return Limit::perMinute($max)->by('evolution-send');
        });
    }

    protected function configureOllamaGenerateRateLimiter(): void
    {
        RateLimiter::for('ollama-generate', function (): Limit {
            $max = max(1, (int) config('services.ollama.generate_attempts_per_minute', 6));

            return Limit::perMinute($max)->by('ollama-generate');
        });
    }

    protected function configureGoogleOAuthRateLimiter(): void
    {
        RateLimiter::for('google-oauth', function (Request $request): Limit {
            return Limit::perMinute(10)->by('google-oauth:'.$request->ip());
        });
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
                ->native(false)
                ->seconds(false)
                ->defaultDateDisplayFormat(fn (): string => UserDateDisplay::dateFormat())
                ->defaultDateTimeDisplayFormat(fn (): string => UserDateDisplay::dateTimeFormat())
                ->defaultDateTimeWithSecondsDisplayFormat(fn (): string => UserDateDisplay::dateTimeFormat().':s')
                ->placeholder(fn (DateTimePicker $component): string => UserDateDisplay::pickerPlaceholder(
                    $component->hasDate(),
                    $component->hasTime(),
                ));
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

        TextInput::macro('characterLimit', function (int $max, string|\Closure|null $helperText = null): TextInput {
            return FieldCharacterLimits::applyTextInput($this, $max, $helperText);
        });
    }
}
