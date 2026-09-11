<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\GoogleOAuthLoginEvent;
use App\Filament\Concerns\EnsuresPrimaryHouseholdMutation;
use App\Filament\Concerns\HasSectionNav;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RequiresPrimaryHouseholdAccess;
use App\Filament\Pages\Schemas\GoogleOAuthSetupForm;
use App\Filament\Support\IntegrationNavigation;
use App\Models\GoogleOAuthLoginLog;
use App\Models\GoogleOAuthSetting;
use App\Models\User;
use App\Services\GoogleOAuth\GoogleOAuthCredentialTester;
use App\Services\GoogleOAuth\GoogleOAuthSettings;
use App\Support\CurrentHousehold;
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
    use EnsuresPrimaryHouseholdMutation;
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
        $platformReady = GoogleOAuthSettings::platform()->isSignInAvailable();
        $linked = filled(auth()->user()?->google_id);

        return ($platformReady && $linked) ? 'Active' : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getNavigationBadge() !== null ? 'success' : null;
    }

    public function canManagePlatformCredentials(): bool
    {
        $householdId = CurrentHousehold::id() ?? auth()->user()?->household_id;

        return $householdId === GoogleOAuthSetting::PLATFORM_HOUSEHOLD_ID;
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

    public function mount(): void
    {
        if (request()->boolean('google_oauth_linked') || session()->pull('google_oauth_linked')) {
            Notification::make()
                ->title('Google account linked')
                ->success()
                ->send();
        }

        if (request()->boolean('google_oauth_link_error') || session()->pull('google_oauth_link_error')) {
            Notification::make()
                ->title('Could not link Google account')
                ->body('Sign in with the Google account you want to use, or try again.')
                ->danger()
                ->send();
        }

        $this->loadFromSettings($this->settings());
        $this->refreshStatus(false);
        $this->loadReadinessChecks();
    }

    public function settingsSourceLabel(): string
    {
        if (! $this->canManagePlatformCredentials()) {
            return 'Platform-managed (shared Client ID)';
        }

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
        return $this->settings()->redirectUrl();
    }

    public function maskedClientSecret(): string
    {
        if (! $this->canManagePlatformCredentials()) {
            return $this->hasSavedSecret || $this->settings()->hasCredentials() ? '••••••••••••' : '—';
        }

        if (! $this->hasSavedSecret) {
            return '—';
        }

        return '••••••••••••';
    }

    public function refreshStatus(bool $allowSideEffects = false): void
    {
        $settings = $this->settings();
        $this->loadLinkedPrimary();
        $this->loadLastSuccessfulSignIn();

        $linked = $this->linkedPrimaryEmail !== null;

        if (! $settings->hasCredentials()) {
            $this->connectionStatus = 'unconfigured';
            $this->statusMessage = $this->canManagePlatformCredentials()
                ? 'Configure the shared Client ID and Client Secret to enable Continue with Google.'
                : 'Google sign-in is not configured on this install yet.';
            $this->latencyMs = 0;
        } elseif (! $linked) {
            $this->connectionStatus = 'degraded';
            $this->statusMessage = 'Platform Google OAuth is ready. Link this Primary’s Gmail to use Continue with Google.';
            $this->latencyMs = 0;
        } elseif ($this->lastTestMessage !== null && str_contains(strtolower($this->lastTestMessage), 'rejected')) {
            $this->connectionStatus = 'down';
            $this->statusMessage = $this->lastTestMessage;
        } else {
            $this->connectionStatus = 'operational';
            $this->statusMessage = 'This Primary can sign in with the linked Google account.';
            $this->latencyMs = 0;
        }

        $this->loadReadinessChecks();
    }

    public function testConnection(): void
    {
        $this->testCredentials();
    }

    public function testCredentials(): void
    {
        $this->ensurePrimaryHouseholdMutation();

        if (! $this->canManagePlatformCredentials()) {
            Notification::make()
                ->title('Only the platform household can test credentials')
                ->warning()
                ->send();

            return;
        }

        $result = app(GoogleOAuthCredentialTester::class)->test(settings: $this->settings());

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
        $this->ensurePrimaryHouseholdMutation();

        if (! $this->canManagePlatformCredentials()) {
            return;
        }

        $settings = $this->settings();
        $secret = filled($clientSecret) ? $clientSecret : $settings->clientSecret();

        $result = app(GoogleOAuthCredentialTester::class)->test($clientId, $secret, $settings);

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
            ->visible(fn (): bool => $this->canManagePlatformCredentials())
            ->modalHeading('Edit Google OAuth settings')
            ->modalDescription('Configure the shared Google OAuth client used by every household for Primary sign-in.')
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
        $platformReady = fn (): bool => $this->settings()->isSignInAvailable();

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
            $this->primaryOnlyAction(Action::make('linkGoogleAccount')
                ->label('Link Google account')
                ->icon(Heroicon::OutlinedLink)
                ->color('primary')
                ->visible(fn (): bool => $this->linkedPrimaryEmail === null)
                ->disabled(fn (): bool => ! $platformReady())
                ->action(function (): void {
                    // Full browser navigation — wire:navigate cannot follow Socialite → Google.
                    $this->redirect($this->settings()->linkAuthorizeUrl(), navigate: false);
                })),
            $this->primaryOnlyAction($this->configureSetupAction()),
            ActionGroup::make([
                $this->primaryOnlyAction(Action::make('testConnection')
                    ->label('Test connection')
                    ->icon('heroicon-o-signal')
                    ->visible(fn (): bool => $this->canManagePlatformCredentials())
                    ->disabled(fn (): bool => ! $this->settings()->hasCredentials())
                    ->action(function (): void {
                        $this->testConnection();
                    })),
                $this->primaryOnlyAction(Action::make('unlinkGoogleAccount')
                    ->label('Unlink Google account')
                    ->icon(Heroicon::OutlinedLinkSlash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Unlink Google account?')
                    ->modalDescription('This Primary will need to link Google again before Continue with Google will work.')
                    ->disabled(fn (): bool => $this->linkedPrimaryEmail === null)
                    ->action(function (): void {
                        $this->unlinkGoogleAccount();
                    })),
                $this->primaryOnlyAction(Action::make('resetCredentials')
                    ->label('Reset credentials')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (): bool => $this->canManagePlatformCredentials())
                    ->requiresConfirmation()
                    ->modalHeading('Reset Google OAuth credentials?')
                    ->modalDescription('Clears the shared Client ID/Secret for the whole install. Linked Google accounts are kept.')
                    ->action(function (): void {
                        $this->resetCredentials();
                    })),
            ])
                ->label('')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button(),
        ];
    }

    public function table(Table $table): Table
    {
        $householdId = CurrentHousehold::id() ?? auth()->user()?->household_id;

        return $table
            ->query(
                GoogleOAuthLoginLog::query()
                    ->when(
                        $householdId !== null,
                        fn ($query) => $query->where('household_id', $householdId),
                        fn ($query) => $query->whereRaw('0 = 1'),
                    )
            )
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
                    ->searchable()
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
        ];
    }

    private function saveSettingsFromState(array $data): bool
    {
        $this->ensurePrimaryHouseholdMutation();

        if (! $this->canManagePlatformCredentials()) {
            Notification::make()
                ->title('Only the platform household can edit credentials')
                ->warning()
                ->send();

            return false;
        }

        $settings = $this->settings();
        $clientId = trim((string) ($data['client_id'] ?? ''));
        $clientSecret = trim((string) ($data['client_secret'] ?? ''));

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
            'enabled' => true,
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

    private function settings(): GoogleOAuthSettings
    {
        return GoogleOAuthSettings::platform();
    }

    private function loadFromSettings(GoogleOAuthSettings $settings): void
    {
        $this->clientId = $this->canManagePlatformCredentials()
            ? (string) ($settings->clientId() ?? '')
            : ($settings->hasCredentials() ? 'Configured by platform' : '');
        $this->enabled = $settings->isSignInAvailable();
        $this->usingSavedSettings = $settings->usesSavedSettings();
        $this->setupComplete = $settings->isSetupComplete();
        $this->hasSavedSecret = filled($settings->clientSecret());
    }

    private function loadLinkedPrimary(): void
    {
        $user = auth()->user();

        if ($user instanceof User && filled($user->google_id)) {
            $this->linkedPrimaryEmail = $user->email;

            return;
        }

        $this->linkedPrimaryEmail = null;
    }

    private function loadLastSuccessfulSignIn(): void
    {
        $householdId = CurrentHousehold::id() ?? auth()->user()?->household_id;

        $log = GoogleOAuthLoginLog::query()
            ->where('event', GoogleOAuthLoginEvent::SignIn)
            ->where('status', 'success')
            ->when(
                $householdId !== null,
                fn ($query) => $query->where('household_id', $householdId),
            )
            ->orderByDesc('created_at')
            ->first();

        $this->lastSuccessfulSignIn = $log?->created_at instanceof Carbon
            ? $log->created_at->toIso8601String()
            : null;
    }

    private function loadReadinessChecks(): void
    {
        $settings = $this->settings();
        $linked = $this->linkedPrimaryEmail !== null;

        $this->readinessChecks = [
            [
                'label' => 'Shared Client ID configured',
                'status' => $settings->hasCredentials() ? 'ready' : 'attention',
                'detail' => $settings->hasCredentials() ? 'Ready' : 'Needs platform setup',
            ],
            [
                'label' => 'Primary Google account linked',
                'status' => $linked ? 'ready' : 'attention',
                'detail' => $linked ? 'Linked' : 'Link from this page',
            ],
            [
                'label' => 'Continue with Google on login',
                'status' => $settings->isSignInAvailable() ? 'ready' : 'attention',
                'detail' => $settings->isSignInAvailable() ? 'Visible when credentials exist' : 'Needs credentials',
            ],
        ];
    }

    private function unlinkGoogleAccount(): void
    {
        $this->ensurePrimaryHouseholdMutation();

        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $user->forceFill([
            'google_id' => null,
            'google_linked_at' => null,
        ])->save();

        $this->loadLinkedPrimary();
        $this->loadReadinessChecks();
        $this->refreshStatus(false);

        Notification::make()
            ->title('Google account unlinked')
            ->success()
            ->send();
    }

    private function resetCredentials(): void
    {
        $this->ensurePrimaryHouseholdMutation();

        if (! $this->canManagePlatformCredentials()) {
            Notification::make()
                ->title('Only the platform household can reset credentials')
                ->warning()
                ->send();

            return;
        }

        $settings = $this->settings();
        $settings->reset();

        $this->loadFromSettings($settings);
        $this->lastTestMessage = null;
        $this->refreshStatus(false);

        Notification::make()
            ->title('Google OAuth credentials reset')
            ->success()
            ->send();
    }
}
