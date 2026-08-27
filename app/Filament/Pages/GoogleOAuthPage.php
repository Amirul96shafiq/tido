<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\GoogleOAuthLoginEvent;
use App\Filament\Concerns\HasSectionNav;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RequiresPrimaryHouseholdAccess;
use App\Filament\Pages\Schemas\GoogleOAuthSetupForm;
use App\Filament\Support\IntegrationNavigation;
use App\Models\GoogleOAuthLoginLog;
use App\Models\User;
use App\Services\GoogleOAuth\GoogleOAuthCredentialTester;
use App\Services\GoogleOAuth\GoogleOAuthSettings;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class GoogleOAuthPage extends Page implements HasTable
{
    use HasSectionNav;
    use InteractsWithTable;
    use PrependsHomeBreadcrumb;
    use RequiresPrimaryHouseholdAccess;

    protected static ?string $slug = 'google-oauth';

    protected static string|\BackedEnum|null $navigationIcon = 'icon-google-oauth';

    protected static ?string $navigationLabel = 'Google OAuth';

    protected static ?string $navigationParentItem = IntegrationNavigation::GOOGLE;

    protected static string|\UnitEnum|null $navigationGroup = IntegrationNavigation::GROUP;

    protected static ?string $title = 'Google OAuth';

    protected static ?int $navigationSort = 10;

    public string $connectionStatus = 'unknown';

    public string $statusMessage = '';

    public int $latencyMs = 0;

    public string $clientId = '';

    public bool $enabled = false;

    public bool $usingSavedSettings = false;

    public bool $setupComplete = false;

    public bool $hasSavedSecret = false;

    public ?string $linkedPrimaryEmail = null;

    public ?string $lastSuccessfulSignIn = null;

    public ?string $lastTestMessage = null;

    /**
     * @var list<array{label: string, status: string, detail: string}>
     */
    public array $readinessChecks = [];

    public static function getNavigationBadge(): ?string
    {
        $settings = app(GoogleOAuthSettings::class);

        return $settings->isSignInAvailable() ? 'Active' : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return app(GoogleOAuthSettings::class)->isSignInAvailable() ? 'success' : null;
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            'fi-google-oauth-page',
        ];
    }

    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return [
            ['label' => 'Status', 'id' => 'google-oauth-status'],
            ['label' => 'Configuration', 'id' => 'google-oauth-config'],
            ['label' => 'Readiness', 'id' => 'google-oauth-readiness'],
            ['label' => 'Sign-In History', 'id' => 'google-oauth-activity'],
        ];
    }

    public function sectionNavAriaLabel(): string
    {
        return 'Google OAuth sections';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->wrapInSectionNavScope([
                    SchemaView::make('filament.pages.partials.google-oauth-content'),
                ]),
            ]);
    }

    public function mount(GoogleOAuthSettings $settings): void
    {
        $this->loadFromSettings($settings);
        $this->refreshStatus(false);
        $this->loadReadinessChecks();
    }

    public function settingsSourceLabel(): string
    {
        if ($this->setupComplete) {
            return 'Setup complete';
        }

        if ($this->usingSavedSettings) {
            return 'Using saved settings';
        }

        return 'Using environment defaults';
    }

    public function redirectUri(): string
    {
        return app(GoogleOAuthSettings::class)->redirectUrl();
    }

    public function maskedClientSecret(): string
    {
        if (! $this->hasSavedSecret) {
            return '—';
        }

        return '••••••••••••';
    }

    public function refreshStatus(bool $allowSideEffects = false): void
    {
        $settings = app(GoogleOAuthSettings::class);

        if (! $settings->hasCredentials()) {
            $this->connectionStatus = 'unconfigured';
            $this->statusMessage = 'Client ID and Client Secret are not configured.';
            $this->latencyMs = 0;
        } elseif (! $settings->enabled()) {
            $this->connectionStatus = 'degraded';
            $this->statusMessage = 'Credentials saved. Sign-in is disabled on the login page.';
            $this->latencyMs = 0;
        } elseif ($this->lastTestMessage !== null && str_contains(strtolower($this->lastTestMessage), 'rejected')) {
            $this->connectionStatus = 'down';
            $this->statusMessage = $this->lastTestMessage;
        } else {
            $this->connectionStatus = 'operational';
            $this->statusMessage = 'Google OAuth is enabled for Primary sign-in.';
            $this->latencyMs = 0;
        }

        $this->loadLinkedPrimary();
        $this->loadLastSuccessfulSignIn();
        $this->loadReadinessChecks();

        if ($allowSideEffects) {
            // No side effects required for Google OAuth status polling.
        }
    }

    public function testConnection(): void
    {
        $this->testCredentials();
    }

    public function testCredentials(): void
    {
        $result = app(GoogleOAuthCredentialTester::class)->test();

        $this->latencyMs = $result['latencyMs'];
        $this->lastTestMessage = $result['message'];

        if ($result['ok']) {
            Notification::make()
                ->title('Connection verified')
                ->body($result['message'])
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Cannot reach Google OAuth')
                ->body($result['message'])
                ->danger()
                ->send();
        }

        $this->refreshStatus(false);
    }

    public function testCredentialsFromForm(string $clientId, string $clientSecret): void
    {
        $settings = app(GoogleOAuthSettings::class);
        $secret = filled($clientSecret) ? $clientSecret : $settings->clientSecret();

        $result = app(GoogleOAuthCredentialTester::class)->test($clientId, $secret);

        $this->latencyMs = $result['latencyMs'];
        $this->lastTestMessage = $result['message'];

        if ($result['ok']) {
            Notification::make()
                ->title('Connection verified')
                ->body($result['message'])
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Cannot reach Google OAuth')
                ->body($result['message'])
                ->danger()
                ->send();
        }
    }

    public function configureSetupAction(): Action
    {
        return Action::make('configureSetup')
            ->label(fn (): string => $this->setupComplete ? 'Edit Google OAuth' : 'Start Configure')
            ->color('primary')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->modalHeading('Edit Google OAuth settings')
            ->modalDescription('Configure Google OAuth credentials and enable Primary sign-in.')
            ->modalSubmitActionLabel('Save')
            ->modalWidth(Width::ThreeExtraLarge)
            ->fillForm(fn (): array => $this->setupFormState())
            ->schema(GoogleOAuthSetupForm::components())
            ->action(function (array $data, Action $action): void {
                if (! $this->saveSettingsFromState($data)) {
                    $action->halt();
                }
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh status')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    $this->refreshStatus(false);

                    Notification::make()
                        ->title('Status refreshed')
                        ->success()
                        ->send();
                }),
            $this->configureSetupAction(),
            ActionGroup::make([
                Action::make('testConnection')
                    ->label('Test connection')
                    ->icon('heroicon-o-signal')
                    ->disabled(fn (): bool => ! app(GoogleOAuthSettings::class)->hasCredentials())
                    ->action(function (): void {
                        $this->testConnection();
                    }),
                Action::make('unlinkGoogleAccount')
                    ->label('Unlink Google account')
                    ->icon(Heroicon::OutlinedLinkSlash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Unlink Google account?')
                    ->modalDescription('The Primary account will need to sign in with Google again to re-link.')
                    ->disabled(fn (): bool => $this->linkedPrimaryEmail === null)
                    ->action(function (): void {
                        $this->unlinkGoogleAccount();
                    }),
                Action::make('resetCredentials')
                    ->label('Reset credentials')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Google OAuth credentials?')
                    ->modalDescription('Saved credentials, sign-in toggle, and the linked Google account will be cleared.')
                    ->action(function (): void {
                        $this->resetCredentials();
                    }),
            ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(GoogleOAuthLoginLog::query())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('event')
                    ->badge()
                    ->formatStateUsing(fn (GoogleOAuthLoginEvent $state): string => $state->label())
                    ->color(fn (GoogleOAuthLoginEvent $state): string => $state->badgeColor())
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'success' ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Account')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('message')
                    ->placeholder('—')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Created at')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->timezone(config('app.timezone')),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Event')
                    ->options(GoogleOAuthLoginEvent::options()),
            ])
            ->emptyStateHeading('No sign-in events yet')
            ->emptyStateDescription('Successful and failed Google sign-in attempts appear here.');
    }

    /**
     * @return array<string, mixed>
     */
    private function setupFormState(): array
    {
        return [
            'client_id' => $this->clientId,
            'client_secret' => '',
            'has_saved_secret' => $this->hasSavedSecret,
            'enabled' => $this->enabled,
        ];
    }

    private function saveSettingsFromState(array $data): bool
    {
        $settings = app(GoogleOAuthSettings::class);
        $clientId = trim((string) ($data['client_id'] ?? ''));
        $clientSecret = trim((string) ($data['client_secret'] ?? ''));
        $enabled = (bool) ($data['enabled'] ?? false);

        if ($clientId === '') {
            Notification::make()
                ->title('Client ID is required')
                ->danger()
                ->send();

            return false;
        }

        if ($clientSecret === '' && ! $this->hasSavedSecret) {
            Notification::make()
                ->title('Client Secret is required')
                ->danger()
                ->send();

            return false;
        }

        $attributes = [
            'client_id' => $clientId,
            'enabled' => $enabled,
            'setup_completed_at' => now(),
        ];

        if ($clientSecret !== '') {
            $attributes['client_secret'] = $clientSecret;
        }

        $settings->save($attributes);
        $this->loadFromSettings($settings);
        $this->refreshStatus(false);

        Notification::make()
            ->title('Google OAuth settings saved')
            ->success()
            ->send();

        return true;
    }

    private function loadFromSettings(GoogleOAuthSettings $settings): void
    {
        $this->clientId = (string) ($settings->clientId() ?? '');
        $this->enabled = $settings->enabled();
        $this->usingSavedSettings = $settings->usesSavedSettings();
        $this->setupComplete = $settings->isSetupComplete();
        $this->hasSavedSecret = filled($settings->clientSecret());
    }

    private function loadLinkedPrimary(): void
    {
        $user = User::query()
            ->whereNotNull('google_id')
            ->where(function ($query): void {
                $query->where('household_role', 'primary')
                    ->orWhereNull('household_role');
            })
            ->first();

        $this->linkedPrimaryEmail = $user?->email;
    }

    private function loadLastSuccessfulSignIn(): void
    {
        $log = GoogleOAuthLoginLog::query()
            ->where('event', GoogleOAuthLoginEvent::SignIn)
            ->where('status', 'success')
            ->orderByDesc('created_at')
            ->first();

        $this->lastSuccessfulSignIn = $log?->created_at instanceof Carbon
            ? $log->created_at->toIso8601String()
            : null;
    }

    private function loadReadinessChecks(): void
    {
        $settings = app(GoogleOAuthSettings::class);
        $linked = $this->linkedPrimaryEmail !== null;

        $this->readinessChecks = [
            [
                'label' => 'Client ID saved',
                'status' => filled($this->clientId) ? 'ready' : 'attention',
                'detail' => filled($this->clientId) ? 'Ready' : 'Needs attention',
            ],
            [
                'label' => 'Client Secret saved',
                'status' => $this->hasSavedSecret ? 'ready' : 'attention',
                'detail' => $this->hasSavedSecret ? 'Ready' : 'Needs attention',
            ],
            [
                'label' => 'Sign-in enabled on login page',
                'status' => $this->enabled && $settings->hasCredentials() ? 'ready' : 'attention',
                'detail' => $this->enabled && $settings->hasCredentials() ? 'Ready' : 'Needs attention',
            ],
            [
                'label' => 'Primary Google account linked',
                'status' => $linked ? 'ready' : 'attention',
                'detail' => $linked ? 'Linked' : 'Ready to link on next sign-in',
            ],
        ];
    }

    private function unlinkGoogleAccount(): void
    {
        User::query()
            ->whereNotNull('google_id')
            ->update([
                'google_id' => null,
                'google_linked_at' => null,
            ]);

        $this->loadLinkedPrimary();
        $this->loadReadinessChecks();

        Notification::make()
            ->title('Google account unlinked')
            ->success()
            ->send();
    }

    private function resetCredentials(): void
    {
        app(GoogleOAuthSettings::class)->reset();
        User::query()
            ->whereNotNull('google_id')
            ->update([
                'google_id' => null,
                'google_linked_at' => null,
            ]);

        $this->loadFromSettings(app(GoogleOAuthSettings::class));
        $this->lastTestMessage = null;
        $this->refreshStatus(false);

        Notification::make()
            ->title('Google OAuth credentials reset')
            ->success()
            ->send();
    }
}
