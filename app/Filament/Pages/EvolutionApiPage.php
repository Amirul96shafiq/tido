<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\EvolutionApiConnectionEvent;
use App\Enums\EvolutionApiConnectMethod;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Jobs\SendEvolutionApiConnectedAlertJob;
use App\Models\EvolutionApiConnectionLog;
use App\Models\User;
use App\Services\EvolutionApiConnectionLogService;
use App\Services\EvolutionInstanceService;
use App\Services\WhatsAppNotificationService;
use App\Support\PhoneNumber;
use App\Support\WhatsAppMessage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Js;

class EvolutionApiPage extends Page implements HasTable
{
    use InteractsWithTable;
    use PrependsHomeBreadcrumb;

    protected string $view = 'filament.pages.evolution-api';

    protected static ?string $slug = 'evolution-api';

    protected static string|\BackedEnum|null $navigationIcon = 'icon-evolution-api';

    protected static ?string $navigationLabel = 'Evolution API';

    protected static string|\UnitEnum|null $navigationGroup = 'Integrations';

    protected static ?string $title = 'Evolution API';

    protected static ?int $navigationSort = 20;

    public string $connectionStatus = 'unknown';

    public ?string $qrBase64 = null;

    public string $statusMessage = '';

    public string $webhookUrl = '';

    public int $qrGeneratedAt = 0;

    public bool $welcomePingSent = false;

    public bool $webhookRegistered = false;

    public ?string $connectedNumber = null;

    public ?string $connectedProfileName = null;

    public ?string $connectedInstanceId = null;

    public ?string $connectedIntegration = null;

    public ?int $connectedMessageCount = null;

    public ?int $connectedContactCount = null;

    public ?int $connectedChatCount = null;

    public ?string $connectedUpdatedAt = null;

    public ?string $pairingCode = null;

    public int $pairingCodeGeneratedAt = 0;

    public ?string $pairingNumber = null;

    public ?string $lastConnectedNumber = null;

    public ?EvolutionApiConnectMethod $pendingConnectMethod = null;

    public ?EvolutionApiConnectMethod $connectedVia = null;

    /** Matches Evolution Baileys `qrTimeout` (45_000 ms) — codes rotate server-side on this interval. */
    public const CONNECT_TTL_SECONDS = 45;

    public function mount(EvolutionInstanceService $evolution): void
    {
        $this->webhookUrl = $evolution->defaultWebhookUrl();
        // Skip side effects when opening the page while already connected/disconnected.
        $this->refreshStatus(allowConnectSideEffects: false);
    }

    public function refreshStatus(bool $allowConnectSideEffects = true): void
    {
        $evolution = app(EvolutionInstanceService::class);
        $wasOpen = $this->isConnectionOpen();

        if (! $evolution->isConfigured()) {
            $this->connectionStatus = 'unconfigured';
            $this->statusMessage = 'Set EVOLUTION_API_URL and EVOLUTION_API_KEY in .env, then start Evolution.';

            return;
        }

        $state = $evolution->connectionState();
        $this->connectionStatus = $state['status'];
        $this->statusMessage = $state['message'];

        if ($this->isConnectionOpen()) {
            $this->clearConnectDisplay();
            $this->statusMessage = 'Evolution API is connected.';
            $this->loadConnectedInstanceDetails($evolution);
            $this->syncWebhookRegistrationStatus($evolution);

            if ($this->connectedNumber !== null) {
                $this->lastConnectedNumber = $this->connectedNumber;
            }

            if ($allowConnectSideEffects && ! $wasOpen) {
                $this->handleSuccessfulConnect();
            }

            return;
        }

        $previousNumber = $this->connectedNumber;
        $previousProfile = $this->connectedProfileName;
        $previousInstanceId = $this->connectedInstanceId;

        $this->clearConnectedInstanceDetails();

        if ($previousNumber !== null) {
            $this->lastConnectedNumber = $previousNumber;
        }

        if ($allowConnectSideEffects && $wasOpen && $this->isConnectionClosed()) {
            $this->welcomePingSent = false;
            $this->webhookRegistered = false;
            $this->pendingConnectMethod = null;

            $this->logConnectionEvent(EvolutionApiConnectionEvent::Disconnected, [
                'status' => $this->connectionStatus,
                'connected_number' => $previousNumber,
                'profile_name' => $previousProfile,
                'meta' => [
                    'source' => 'page',
                    'instance_id' => $previousInstanceId,
                ],
            ]);
            $this->sendDisconnectedDatabaseNotification();
        }

        // While a pairing code is on screen, only connectionState (above) may run.
        // Calling /connect during entry can race Evolution's companion_hello and
        // invalidate the code WhatsApp is validating.
        if (strtolower($this->connectionStatus) === 'connecting' && $this->qrBase64 !== null && $this->pairingCode === null) {
            $this->syncQrFromEvolution($allowConnectSideEffects);
        }

        // Once the on-screen code expires it is already dead, so it is safe to pull
        // the code Evolution rotated server-side — no logout, same session/creds.
        if (
            strtolower($this->connectionStatus) === 'connecting'
            && $this->pairingCode !== null
            && $this->pairingSecondsRemaining() <= 0
        ) {
            $this->syncPairingCodeFromEvolution($allowConnectSideEffects);
        }
    }

    public function generateQr(): void
    {
        if (! $this->hasContactAllowlist()) {
            $this->notifyMissingContactAllowlist();

            return;
        }

        $this->pendingConnectMethod = EvolutionApiConnectMethod::QrCode;
        $this->clearPairingDisplay();

        $evolution = app(EvolutionInstanceService::class);
        $wasOpen = $this->isConnectionOpen();
        $result = $evolution->createOrConnect();

        $this->applyQrResult($result);

        if ($result['ok'] && $this->qrBase64 !== null) {
            Notification::make()
                ->title('Fresh QR ready — scan now')
                ->body('WhatsApp → Linked Devices → Link a Device. Scan before the timer hits 0 — this page auto-refreshes.')
                ->success()
                ->send();

            return;
        }

        if ($result['ok'] && $this->isConnectionOpen()) {
            if (! $wasOpen) {
                $this->handleSuccessfulConnect();
            }

            Notification::make()
                ->title('Already connected')
                ->body('No QR needed. Webhook is registered automatically; you can send a test ping.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Could not get QR')
            ->body($result['message'])
            ->danger()
            ->send();
    }

    public function generatePairingCode(string $number): void
    {
        if (! $this->hasContactAllowlist()) {
            $this->notifyMissingContactAllowlist();

            return;
        }

        $normalized = PhoneNumber::normalize($number);

        if ($normalized === null) {
            Notification::make()
                ->title('Invalid phone number')
                ->body('Enter a valid WhatsApp number (e.g. 60123456789).')
                ->danger()
                ->send();

            return;
        }

        // Resubmitting while a code is still valid logs Evolution out and rotates the
        // code mid-entry, which guarantees "couldn't link" on the phone.
        if (
            $this->pairingCode !== null
            && $this->pairingNumber === $normalized
            && $this->pairingSecondsRemaining() > 0
        ) {
            Notification::make()
                ->title('Pairing code still active')
                ->body('Enter the code on screen. A new one is fetched automatically after it expires.')
                ->warning()
                ->send();

            return;
        }

        $this->clearQrDisplay();

        $this->pendingConnectMethod = EvolutionApiConnectMethod::PairingCode;

        $evolution = app(EvolutionInstanceService::class);
        $wasOpen = $this->isConnectionOpen();

        // Clean slate before pairing — stale Baileys creds make WhatsApp reject the code.
        // Do this only before starting, never mid-flight.
        $evolution->logoutInstance();
        usleep(1_500_000);

        $result = $evolution->createOrConnectWithPairingCode($normalized);

        $this->applyPairingResult($result, $normalized);

        if ($result['ok'] && $this->pairingCode !== null) {
            Notification::make()
                ->title('Pairing code ready')
                ->body('WhatsApp → Linked Devices → Link with phone number. Enter the code before the timer hits 0.')
                ->success()
                ->send();

            return;
        }

        if ($result['ok'] && $this->isConnectionOpen()) {
            if (! $wasOpen) {
                $this->handleSuccessfulConnect();
            }

            Notification::make()
                ->title('Already connected')
                ->body('No pairing code needed. Webhook is registered automatically; you can send a test ping.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Could not get pairing code')
            ->body($result['message'])
            ->danger()
            ->send();
    }

    public function copyPairingCode(): void
    {
        if ($this->pairingCode === null) {
            return;
        }

        $this->js('window.navigator.clipboard.writeText('.Js::from($this->pairingCodeForClipboard()).')');

        Notification::make()
            ->title('Pairing code copied')
            ->body('Paste it in WhatsApp → Linked Devices → Link with phone number.')
            ->success()
            ->send();
    }

    public function logoutSession(): void
    {
        $evolution = app(EvolutionInstanceService::class);
        $wasOpen = $this->isConnectionOpen();
        $connectedNumber = $this->connectedNumber;
        $profileName = $this->connectedProfileName;
        $connectedInstanceId = $this->connectedInstanceId;
        $result = $evolution->logoutInstance();

        if ($connectedNumber !== null) {
            $this->lastConnectedNumber = $connectedNumber;
        }

        $this->clearConnectDisplay();
        $this->welcomePingSent = false;
        $this->webhookRegistered = false;
        $this->pendingConnectMethod = null;
        $this->clearConnectedInstanceDetails();
        $this->refreshStatus(allowConnectSideEffects: false);
        $this->statusMessage = $result['message'];

        if ($result['ok']) {
            $this->logConnectionEvent(EvolutionApiConnectionEvent::Logout, [
                'status' => $this->connectionStatus,
                'connected_number' => $connectedNumber,
                'profile_name' => $profileName,
                'meta' => [
                    'source' => 'logout',
                    'instance_id' => $connectedInstanceId,
                ],
            ]);
        }

        if ($result['ok'] && $wasOpen && $this->isConnectionClosed()) {
            $this->sendDisconnectedDatabaseNotification();
        }

        Notification::make()
            ->title($result['ok'] ? 'Session logged out' : 'Logout failed')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    public function cancelConnecting(): void
    {
        if ($this->isConnectionOpen() || ! $this->isConnectingAttempt()) {
            Notification::make()
                ->title('Nothing to cancel')
                ->body('There is no active connecting attempt.')
                ->warning()
                ->send();

            return;
        }

        $result = app(EvolutionInstanceService::class)->logoutInstance();

        $this->clearConnectDisplay();
        $this->pendingConnectMethod = null;
        $this->refreshStatus(allowConnectSideEffects: false);

        if ($result['ok']) {
            $this->connectionStatus = 'close';
            $this->statusMessage = 'Connecting cancelled. Use Connect to try again.';
        } else {
            $this->statusMessage = $result['message'];
        }

        Notification::make()
            ->title($result['ok'] ? 'Connecting cancelled' : 'Cancel failed')
            ->body($this->statusMessage)
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();
    }

    public function registerWebhook(): void
    {
        $result = $this->registerWebhookQuietly(notify: true);

        if ($result['ok']) {
            $this->webhookRegistered = true;
        }
    }

    public function sendPing(): void
    {
        $number = PhoneNumber::primaryWhatsAppNumber();

        if ($number === null) {
            Notification::make()
                ->title('Missing profile WhatsApp number')
                ->body('Set your WhatsApp number in Profile first.')
                ->danger()
                ->send();

            return;
        }

        $whatsApp = app(WhatsAppNotificationService::class);
        $exists = $whatsApp->isWhatsAppNumber($number);

        if ($exists === false) {
            Notification::make()
                ->title('Invalid WhatsApp number')
                ->body($number.' is not registered on WhatsApp. Update your Profile WhatsApp Number, then try again.')
                ->danger()
                ->send();

            return;
        }

        $result = $whatsApp->sendMessageResult(
            $number,
            WhatsAppMessage::compose(
                '✅',
                'Test ping',
                "Outbound WhatsApp delivery is working correctly.\n\nSend a document anytime to start tracking expenses.",
            ),
        );

        if ($result->ok) {
            Notification::make()
                ->title('Ping sent')
                ->body('Check WhatsApp on '.$number.'.')
                ->success()
                ->send();

            return;
        }

        $body = match ($result->reason) {
            'not_on_whatsapp' => $number.' is not registered on WhatsApp. Update your Profile WhatsApp Number, then try again.',
            'connection_error' => 'Could not reach Evolution API. Is the service running?',
            default => $this->isConnectionOpen()
                ? 'Could not deliver to '.$number.'. Confirm the Profile WhatsApp Number is correct, then try again.'
                : 'Evolution sendText failed. Is the instance connected?',
        };

        Notification::make()
            ->title($result->reason === 'not_on_whatsapp' ? 'Invalid WhatsApp number' : 'Ping failed')
            ->body($body)
            ->danger()
            ->send();
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshStatus')
                ->label('Refresh status')
                ->color('gray')
                ->action('refreshStatus'),
            Action::make('cancelConnecting')
                ->label('Cancel connecting')
                ->icon('heroicon-o-x-mark')
                ->color('warning')
                ->extraAttributes(['wire:key' => 'wa-action-cancel-connecting'])
                ->visible(fn (): bool => $this->isConnectingAttempt())
                ->requiresConfirmation()
                ->modalHeading('Cancel connecting?')
                ->modalDescription('Stops the current QR or pairing attempt and closes the Evolution connecting session.')
                ->modalSubmitActionLabel('Cancel connecting')
                ->action(function (): void {
                    $this->cancelConnecting();
                }),
            $this->connectHeaderAction(),
            ActionGroup::make([
                Action::make('registerWebhook')
                    ->label('Register Webhook')
                    ->icon('heroicon-o-globe-alt')
                    ->extraAttributes(['wire:key' => 'wa-action-register-webhook'])
                    ->disabled(fn (): bool => ! $this->isConnectionOpen() || $this->webhookRegistered)
                    ->action(function (): void {
                        $this->registerWebhook();
                    }),
                Action::make('sendPing')
                    ->label('Send Test Ping')
                    ->icon('heroicon-o-paper-airplane')
                    ->extraAttributes(['wire:key' => 'wa-action-send-ping'])
                    ->disabled(fn (): bool => ! $this->isConnectionOpen())
                    ->action(function (): void {
                        $this->sendPing();
                    }),
                Action::make('logoutSession')
                    ->label('Sign out Current Session')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('danger')
                    ->extraAttributes(['wire:key' => 'wa-action-logout-session'])
                    ->disabled(fn (): bool => ! $this->isConnectionOpen())
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $this->logoutSession();
                    }),
            ])
                ->label('')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button(),
        ];
    }

    private function connectHeaderAction(): Action|ActionGroup
    {
        if ($this->isConnectionOpen()) {
            // ActionGroup dropdown ignores `disabled` on its trigger button — render a
            // plain disabled action instead so the menu cannot open while connected.
            return Action::make('connect')
                ->label('Connected')
                ->icon('heroicon-o-link')
                ->button()
                ->disabled()
                ->extraAttributes(['wire:key' => 'wa-action-connect-disabled']);
        }

        if (! $this->hasContactAllowlist()) {
            return Action::make('connect')
                ->label('Connect')
                ->icon('heroicon-o-link')
                ->button()
                ->disabled()
                ->tooltip('Set your WhatsApp number in Profile before connecting')
                ->extraAttributes(['wire:key' => 'wa-action-connect-blocked']);
        }

        return ActionGroup::make([
            Action::make('generateQr')
                ->label('Scan QR code')
                ->icon('heroicon-o-qr-code')
                ->extraAttributes(['wire:key' => 'wa-action-generate-qr'])
                ->action(function (): void {
                    $this->generateQr();
                }),
            Action::make('pairWithCode')
                ->label('Pair with code')
                ->icon('heroicon-o-device-phone-mobile')
                ->extraAttributes(['wire:key' => 'wa-action-pair-with-code'])
                ->modalWidth(Width::Small)
                ->extraModalOverlayAttributes(['class' => 'fi-modal-overlay-blur'], merge: true)
                ->form([
                    TextInput::make('number')
                        ->label('WhatsApp number to link')
                        ->helperText('WhatsApp account to link as the Evolution device (not your allowlist contact).')
                        ->default(fn (): ?string => $this->lastConnectedNumber)
                        ->required()
                        ->rules([
                            fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                if (PhoneNumber::normalize(is_string($value) ? $value : null) === null) {
                                    $fail('Enter a valid Malaysian WhatsApp number (e.g. 60123456789).');
                                }
                            },
                        ]),
                ])
                ->modalSubmitActionLabel('Generate code')
                ->action(function (array $data): void {
                    $this->generatePairingCode((string) ($data['number'] ?? ''));
                }),
        ])
            ->label('Connect')
            ->icon('heroicon-o-link')
            ->button()
            ->dropdownPlacement('bottom-end')
            ->extraAttributes(['wire:key' => 'wa-action-connect-group']);
    }

    public function isConnectionOpen(): bool
    {
        return in_array(strtolower($this->connectionStatus), ['open', 'connected'], true);
    }

    public function isConnectionClosed(): bool
    {
        return in_array(strtolower($this->connectionStatus), ['close', 'closed', 'disconnected'], true);
    }

    public function isConnectingAttempt(): bool
    {
        if ($this->isConnectionOpen()) {
            return false;
        }

        return strtolower($this->connectionStatus) === 'connecting'
            || $this->qrBase64 !== null
            || $this->pairingCode !== null;
    }

    public function getPollingInterval(): ?string
    {
        if ($this->isConnectionOpen()) {
            return null;
        }

        // Keep polling through post-scan 515 recovery even if the QR/code panel cleared.
        if (
            $this->qrBase64 !== null
            || $this->pairingCode !== null
            || strtolower($this->connectionStatus) === 'connecting'
        ) {
            return '5s';
        }

        return null;
    }

    public function qrAgeSeconds(): int
    {
        if ($this->qrGeneratedAt <= 0) {
            return 0;
        }

        return max(0, time() - $this->qrGeneratedAt);
    }

    public function qrSecondsRemaining(): int
    {
        if ($this->qrGeneratedAt <= 0 || $this->qrBase64 === null) {
            return 0;
        }

        return max(0, self::CONNECT_TTL_SECONDS - $this->qrAgeSeconds());
    }

    public function qrProgressPercent(): int
    {
        if ($this->qrGeneratedAt <= 0 || $this->qrBase64 === null) {
            return 0;
        }

        return (int) round(($this->qrSecondsRemaining() / self::CONNECT_TTL_SECONDS) * 100);
    }

    public function pairingAgeSeconds(): int
    {
        if ($this->pairingCodeGeneratedAt <= 0) {
            return 0;
        }

        return max(0, time() - $this->pairingCodeGeneratedAt);
    }

    public function pairingSecondsRemaining(): int
    {
        if ($this->pairingCodeGeneratedAt <= 0 || $this->pairingCode === null) {
            return 0;
        }

        return max(0, self::CONNECT_TTL_SECONDS - $this->pairingAgeSeconds());
    }

    public function pairingProgressPercent(): int
    {
        if ($this->pairingCodeGeneratedAt <= 0 || $this->pairingCode === null) {
            return 0;
        }

        return (int) round(($this->pairingSecondsRemaining() / self::CONNECT_TTL_SECONDS) * 100);
    }

    public function formattedPairingCode(): string
    {
        if ($this->pairingCode === null) {
            return '';
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $this->pairingCode) ?? $this->pairingCode);

        if (strlen($normalized) === 8) {
            return substr($normalized, 0, 4).'-'.substr($normalized, 4, 4);
        }

        return $normalized;
    }

    public function pairingCodeForClipboard(): string
    {
        if ($this->pairingCode === null) {
            return '';
        }

        return strtoupper(preg_replace('/\s+/', '', $this->pairingCode) ?? $this->pairingCode);
    }

    private function handleSuccessfulConnect(): void
    {
        if ($this->pendingConnectMethod !== null) {
            $this->connectedVia = $this->pendingConnectMethod;
        }

        $this->logConnectionEvent(EvolutionApiConnectionEvent::Connected, [
            'status' => $this->connectionStatus,
            'connected_number' => $this->connectedNumber,
            'profile_name' => $this->connectedProfileName,
            'meta' => [
                'source' => 'page',
                'instance_id' => $this->connectedInstanceId,
                'connect_method' => $this->pendingConnectMethod?->value,
            ],
        ]);
        $this->registerWebhookOnConnect();
        $this->sendWelcomePingOnConnect();
        $this->sendConnectedDatabaseNotification();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(EvolutionApiConnectionLog::query())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('event')
                    ->badge()
                    ->formatStateUsing(fn (EvolutionApiConnectionEvent $state): string => $state->label())
                    ->color(fn (EvolutionApiConnectionEvent $state): string => $state->badgeColor())
                    ->sortable()
                    ->searchable(),

                TextColumn::make('connected_number')
                    ->label('Number')
                    ->placeholder('—')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('profile_name')
                    ->label('Profile')
                    ->placeholder('—')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('connectMethod')
                    ->label('Connected via')
                    ->getStateUsing(fn (EvolutionApiConnectionLog $record): ?string => $record->connectMethod()?->label())
                    ->placeholder('—')
                    ->toggleable(),

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
                    ->options(EvolutionApiConnectionEvent::options())
                    ->searchable(),
            ])
            ->emptyStateHeading('No connection events yet')
            ->emptyStateDescription('Connect, disconnect, or log out to start the history.')
            ->emptyStateIcon('heroicon-o-signal')
            ->paginated([10, 25, 50]);
    }

    /**
     * @param  array{
     *     status?: string|null,
     *     connected_number?: string|null,
     *     profile_name?: string|null,
     *     message?: string|null,
     *     meta?: array<string, mixed>|null
     * }  $context
     */
    private function logConnectionEvent(EvolutionApiConnectionEvent $event, array $context = []): void
    {
        app(EvolutionApiConnectionLogService::class)->log($event, $context);
    }

    private function registerWebhookOnConnect(): void
    {
        if ($this->webhookRegistered) {
            return;
        }

        $result = $this->registerWebhookQuietly(notify: false);

        if ($result['ok']) {
            $this->webhookRegistered = true;
            $this->statusMessage = $result['message'];

            Notification::make()
                ->title('Webhook registered')
                ->body($result['message'])
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Connected — webhook registration failed')
            ->body($result['message'].' Use Register Webhook from the menu to retry.')
            ->warning()
            ->send();
    }

    private function syncWebhookRegistrationStatus(EvolutionInstanceService $evolution): void
    {
        $result = $evolution->findWebhook();

        if (! $result['ok'] || ! $result['registered']) {
            return;
        }

        $this->webhookRegistered = true;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function registerWebhookQuietly(bool $notify): array
    {
        $evolution = app(EvolutionInstanceService::class);
        $result = $evolution->registerWebhook($this->webhookUrl !== '' ? $this->webhookUrl : null);

        $this->statusMessage = $result['message'];

        if ($notify) {
            Notification::make()
                ->title($result['ok'] ? 'Webhook registered' : 'Webhook failed')
                ->body($result['message'])
                ->{$result['ok'] ? 'success' : 'danger'}()
                ->send();
        }

        return $result;
    }

    private function syncQrFromEvolution(bool $allowConnectSideEffects = true): void
    {
        $wasOpen = $this->isConnectionOpen();
        $result = app(EvolutionInstanceService::class)->connectInstance();

        if (! $result['ok']) {
            return;
        }

        $qrChanged = $result['qrBase64'] !== null && $result['qrBase64'] !== $this->qrBase64;
        $ttlExpired = $this->qrSecondsRemaining() <= 0;
        $becameOpen = in_array(strtolower($result['status']), ['open', 'connected'], true);

        if ($becameOpen || $qrChanged || ($ttlExpired && $result['qrBase64'] !== null)) {
            $this->applyQrResult($result, quiet: true);
        }

        if ($allowConnectSideEffects && ! $wasOpen && $this->isConnectionOpen()) {
            $this->handleSuccessfulConnect();
        }
    }

    private function syncPairingCodeFromEvolution(bool $allowConnectSideEffects = true): void
    {
        if ($this->pairingNumber === null || $this->pairingNumber === '') {
            return;
        }

        $wasOpen = $this->isConnectionOpen();
        $result = app(EvolutionInstanceService::class)->connectInstance($this->pairingNumber);

        if (! $result['ok']) {
            if ($this->pairingSecondsRemaining() <= 0) {
                $this->refreshPairingCodeQuietly($allowConnectSideEffects);
            }

            return;
        }

        $codeChanged = $result['pairingCode'] !== null && $result['pairingCode'] !== $this->pairingCode;
        $ttlExpired = $this->pairingSecondsRemaining() <= 0;
        $becameOpen = in_array(strtolower($result['status']), ['open', 'connected'], true);

        if ($becameOpen || $codeChanged || ($ttlExpired && $result['pairingCode'] !== null)) {
            $this->applyPairingResult($result, $this->pairingNumber, quiet: true);

            if ($allowConnectSideEffects && ! $wasOpen && $this->isConnectionOpen()) {
                $this->handleSuccessfulConnect();
            }

            return;
        }

        if ($ttlExpired) {
            $this->refreshPairingCodeQuietly($allowConnectSideEffects);
        }
    }

    private function refreshPairingCodeQuietly(bool $allowConnectSideEffects = true): void
    {
        if ($this->pairingNumber === null || $this->pairingNumber === '') {
            return;
        }

        $wasOpen = $this->isConnectionOpen();
        $result = app(EvolutionInstanceService::class)->createOrConnectWithPairingCode($this->pairingNumber);

        if (! $result['ok']) {
            return;
        }

        $this->applyPairingResult($result, $this->pairingNumber, quiet: true);

        if ($allowConnectSideEffects && ! $wasOpen && $this->isConnectionOpen()) {
            $this->handleSuccessfulConnect();
        }
    }

    /**
     * @param  array{ok: bool, status: string, qrBase64: string|null, pairingCode: string|null, message: string, raw: array<string, mixed>|null}  $result
     */
    private function applyPairingResult(array $result, string $number, bool $quiet = false): void
    {
        $this->connectionStatus = $result['status'];
        $this->statusMessage = $result['message'];
        $this->pairingNumber = $number;

        if ($result['pairingCode'] !== null) {
            $this->pairingCode = $result['pairingCode'];
            $this->pairingCodeGeneratedAt = time();

            if (! $quiet) {
                $this->statusMessage = 'Pairing code ready — enter it in WhatsApp before the timer expires.';
            }

            return;
        }

        if ($this->isConnectionOpen()) {
            $this->clearPairingDisplay();
            $this->statusMessage = 'Evolution API is connected.';
            $this->loadConnectedInstanceDetails();
        }
    }

    /**
     * @param  array{ok: bool, status: string, qrBase64: string|null, pairingCode: string|null, message: string, raw: array<string, mixed>|null}  $result
     */
    private function applyQrResult(array $result, bool $quiet = false): void
    {
        $this->connectionStatus = $result['status'];
        $this->statusMessage = $result['message'];

        if ($result['qrBase64'] !== null) {
            $this->qrBase64 = $result['qrBase64'];
            $this->qrGeneratedAt = time();

            if (! $quiet) {
                $this->statusMessage = 'Fresh QR ready — scan before the timer expires.';
            }

            return;
        }

        if ($this->isConnectionOpen()) {
            $this->clearQrDisplay();
            $this->statusMessage = 'Evolution API is connected.';
            $this->loadConnectedInstanceDetails();
        }
    }

    private function clearConnectDisplay(): void
    {
        $this->clearQrDisplay();
        $this->clearPairingDisplay();
    }

    private function clearQrDisplay(): void
    {
        $this->qrBase64 = null;
        $this->qrGeneratedAt = 0;
    }

    private function clearPairingDisplay(): void
    {
        $this->pairingCode = null;
        $this->pairingCodeGeneratedAt = 0;
        $this->pairingNumber = null;
    }

    /**
     * @return list<string>
     */
    public function allowedSenderNumbers(): array
    {
        return PhoneNumber::allowedWhatsAppSenders();
    }

    /**
     * @return array{
     *     primary: list<array{name: string, display_name: string|null, phone: string, avatar_url: string}>,
     *     family: list<array{id: int, name: string, display_name: string|null, relationship_label: string|null, phone: string, avatar_url: string}>
     * }
     */
    public function allowedSenderEntries(): array
    {
        return PhoneNumber::allowedWhatsAppSenderEntries();
    }

    public function hasContactAllowlist(): bool
    {
        return $this->allowedSenderNumbers() !== [];
    }

    public function profileEditUrl(): string
    {
        return EditProfile::getUrl();
    }

    public function familyMembersUrl(): string
    {
        return FamilyMemberResource::getUrl('index');
    }

    private function notifyMissingContactAllowlist(): void
    {
        Notification::make()
            ->title('Contact allowlist required')
            ->body('Set your WhatsApp number in Profile before connecting Evolution API.')
            ->danger()
            ->actions([
                Action::make('editProfile')
                    ->label('Open Profile')
                    ->url($this->profileEditUrl())
                    ->button(),
            ])
            ->send();
    }

    public function configuredDeviceLabel(): string
    {
        $label = config('services.evolution.device_label');

        return is_string($label) && trim($label) !== ''
            ? trim($label)
            : 'tido App (Evolution API)';
    }

    public function effectiveDeviceLabel(): string
    {
        if ($this->connectedVia !== null) {
            return $this->connectedVia->linkedDeviceLabel($this->configuredDeviceLabel());
        }

        return $this->configuredDeviceLabel();
    }

    private function loadConnectedInstanceDetails(?EvolutionInstanceService $evolution = null): void
    {
        $details = ($evolution ?? app(EvolutionInstanceService::class))->fetchInstanceDetails();

        if (! $details['ok']) {
            return;
        }

        $this->connectedNumber = $details['connectedNumber'];
        $this->connectedProfileName = $details['profileName'];
        $this->connectedInstanceId = $details['instanceId'];
        $this->connectedIntegration = $details['integration'];
        $this->connectedMessageCount = $details['messageCount'];
        $this->connectedContactCount = $details['contactCount'];
        $this->connectedChatCount = $details['chatCount'];
        $this->connectedUpdatedAt = $details['updatedAt'];

        $this->loadConnectedViaFromHistory();
    }

    private function loadConnectedViaFromHistory(): void
    {
        if ($this->connectedNumber === null) {
            return;
        }

        $log = EvolutionApiConnectionLog::query()
            ->where('event', EvolutionApiConnectionEvent::Connected)
            ->where('connected_number', $this->connectedNumber)
            ->latest('id')
            ->first();

        $method = $log?->connectMethod();

        if ($method !== null) {
            $this->connectedVia = $method;
        }
    }

    private function clearConnectedInstanceDetails(): void
    {
        $this->connectedNumber = null;
        $this->connectedProfileName = null;
        $this->connectedInstanceId = null;
        $this->connectedIntegration = null;
        $this->connectedMessageCount = null;
        $this->connectedContactCount = null;
        $this->connectedChatCount = null;
        $this->connectedUpdatedAt = null;
        $this->connectedVia = null;
    }

    private function sendConnectedDatabaseNotification(): void
    {
        $recipient = auth()->user();

        if (! $recipient instanceof User || ! $recipient->notify_evolution_api) {
            return;
        }

        Notification::make()
            ->title('Evolution API connected')
            ->body('Your Evolution API instance is linked and ready. You can send receipt photos anytime.')
            ->success()
            ->icon('heroicon-o-check-badge')
            ->actions([
                Action::make('openEvolutionApi')
                    ->label('Open Evolution API')
                    ->button()
                    ->url(static::getUrl(), shouldOpenInNewTab: true)
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipient);
    }

    private function sendDisconnectedDatabaseNotification(): void
    {
        $recipient = auth()->user();

        if (! $recipient instanceof User || ! $recipient->notify_evolution_api) {
            return;
        }

        Notification::make()
            ->title('Evolution API disconnected')
            ->body('Your Evolution API session is closed or disconnected. Use Connect to scan a QR or pair with a code.')
            ->warning()
            ->icon('icon-evolution-api')
            ->actions([
                Action::make('openEvolutionApi')
                    ->label('Open Evolution API')
                    ->button()
                    ->url(static::getUrl(), shouldOpenInNewTab: true)
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipient);
    }

    private function sendWelcomePingOnConnect(): void
    {
        if ($this->welcomePingSent) {
            return;
        }

        $number = PhoneNumber::primaryWhatsAppNumber();

        if ($number === null) {
            Notification::make()
                ->title('Connected — welcome ping skipped')
                ->body('Set your WhatsApp number in Profile to auto-message yourself on connect.')
                ->warning()
                ->send();

            return;
        }

        $this->welcomePingSent = true;

        $connectMethod = $this->pendingConnectMethod;
        $this->pendingConnectMethod = null;

        SendEvolutionApiConnectedAlertJob::dispatch($this->connectedNumber, $connectMethod)
            ->delay(now()->addSeconds(5));

        Notification::make()
            ->title('Connected — welcome queued')
            ->body('A confirmation will be sent to '.$number.' shortly.')
            ->success()
            ->send();
    }
}
