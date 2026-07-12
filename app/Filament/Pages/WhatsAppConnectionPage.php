<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\WhatsAppConnectionEvent;
use App\Jobs\SendWhatsAppConnectedAlertJob;
use App\Models\User;
use App\Models\WhatsAppConnectionLog;
use App\Services\EvolutionInstanceService;
use App\Services\WhatsAppConnectionLogService;
use App\Services\WhatsAppNotificationService;
use App\Support\PhoneNumber;
use App\Support\WhatsAppMessage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsAppConnectionPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.whatsapp-connection';

    protected static ?string $slug = 'whatsapp-connection';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationLabel = 'WhatsApp Connection';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'WhatsApp Connection';

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

    public const QR_TTL_SECONDS = 20;

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
            $this->qrBase64 = null;
            $this->qrGeneratedAt = 0;
            $this->statusMessage = 'WhatsApp is connected.';
            $this->loadConnectedInstanceDetails($evolution);

            if ($allowConnectSideEffects && ! $wasOpen) {
                $this->handleSuccessfulConnect();
            }

            return;
        }

        $previousNumber = $this->connectedNumber;
        $previousProfile = $this->connectedProfileName;
        $previousInstanceId = $this->connectedInstanceId;

        $this->clearConnectedInstanceDetails();

        if ($allowConnectSideEffects && $wasOpen && $this->isConnectionClosed()) {
            $this->welcomePingSent = false;
            $this->webhookRegistered = false;

            $this->logConnectionEvent(WhatsAppConnectionEvent::Disconnected, [
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

        // Evolution rotates QR codes often. Refresh when this page's QR TTL elapses
        // so WhatsApp does not reject an expired code from the UI.
        if ($this->qrBase64 !== null && $this->qrSecondsRemaining() <= 0) {
            $this->refreshQrQuietly();
        }
    }

    public function generateQr(): void
    {
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

    public function logoutSession(): void
    {
        $evolution = app(EvolutionInstanceService::class);
        $wasOpen = $this->isConnectionOpen();
        $connectedNumber = $this->connectedNumber;
        $profileName = $this->connectedProfileName;
        $connectedInstanceId = $this->connectedInstanceId;
        $result = $evolution->logoutInstance();

        $this->qrBase64 = null;
        $this->qrGeneratedAt = 0;
        $this->welcomePingSent = false;
        $this->webhookRegistered = false;
        $this->clearConnectedInstanceDetails();
        $this->refreshStatus(allowConnectSideEffects: false);
        $this->statusMessage = $result['message'];

        if ($result['ok']) {
            $this->logConnectionEvent(WhatsAppConnectionEvent::Logout, [
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

    public function registerWebhook(): void
    {
        $result = $this->registerWebhookQuietly(notify: true);

        if ($result['ok']) {
            $this->webhookRegistered = true;
        }
    }

    public function sendPing(): void
    {
        $number = config('services.evolution.personal_number');

        if (! is_string($number) || $number === '') {
            Notification::make()
                ->title('Missing PERSONAL_WHATSAPP_NUMBER')
                ->body('Set PERSONAL_WHATSAPP_NUMBER in .env first.')
                ->danger()
                ->send();

            return;
        }

        $sent = app(WhatsAppNotificationService::class)
            ->sendMessage(
                $number,
                WhatsAppMessage::compose(
                    '✅',
                    'Test ping',
                    "Outbound WhatsApp delivery is working correctly.\n\nSend a document anytime to start tracking expenses.",
                ),
            );

        Notification::make()
            ->title($sent ? 'Ping sent' : 'Ping failed')
            ->body($sent
                ? 'Check WhatsApp on '.$number.'.'
                : 'Evolution sendText failed. Is the instance connected?')
            ->{$sent ? 'success' : 'danger'}()
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
            ActionGroup::make([
                Action::make('generateQr')
                    ->label('Generate / Refresh QR')
                    ->icon('heroicon-o-qr-code')
                    ->extraAttributes(['wire:key' => 'wa-action-generate-qr'])
                    ->visible(fn (): bool => ! $this->isConnectionOpen())
                    ->action(function (): void {
                        $this->generateQr();
                    }),
                Action::make('registerWebhook')
                    ->label('Register Webhook')
                    ->icon('heroicon-o-globe-alt')
                    ->extraAttributes(['wire:key' => 'wa-action-register-webhook'])
                    ->action(function (): void {
                        $this->registerWebhook();
                    }),
                Action::make('sendPing')
                    ->label('Send Test Ping')
                    ->icon('heroicon-o-paper-airplane')
                    ->extraAttributes(['wire:key' => 'wa-action-send-ping'])
                    ->action(function (): void {
                        $this->sendPing();
                    }),
                Action::make('logoutSession')
                    ->label('Logout Current Session')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('danger')
                    ->extraAttributes(['wire:key' => 'wa-action-logout-session'])
                    ->visible(fn (): bool => $this->isConnectionOpen())
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

    public function isConnectionOpen(): bool
    {
        return in_array(strtolower($this->connectionStatus), ['open', 'connected'], true);
    }

    public function isConnectionClosed(): bool
    {
        return in_array(strtolower($this->connectionStatus), ['close', 'closed', 'disconnected'], true);
    }

    public function getPollingInterval(): ?string
    {
        if ($this->qrBase64 !== null && ! $this->isConnectionOpen()) {
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

        return max(0, self::QR_TTL_SECONDS - $this->qrAgeSeconds());
    }

    public function qrProgressPercent(): int
    {
        if ($this->qrGeneratedAt <= 0 || $this->qrBase64 === null) {
            return 0;
        }

        return (int) round(($this->qrSecondsRemaining() / self::QR_TTL_SECONDS) * 100);
    }

    private function handleSuccessfulConnect(): void
    {
        $this->logConnectionEvent(WhatsAppConnectionEvent::Connected, [
            'status' => $this->connectionStatus,
            'connected_number' => $this->connectedNumber,
            'profile_name' => $this->connectedProfileName,
            'meta' => [
                'source' => 'page',
                'instance_id' => $this->connectedInstanceId,
            ],
        ]);
        $this->registerWebhookOnConnect();
        $this->sendWelcomePingOnConnect();
        $this->sendConnectedDatabaseNotification();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(WhatsAppConnectionLog::query())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable()
                    ->timezone(config('app.timezone')),

                TextColumn::make('event')
                    ->badge()
                    ->formatStateUsing(fn (WhatsAppConnectionEvent $state): string => $state->label())
                    ->color(fn (WhatsAppConnectionEvent $state): string => $state->badgeColor())
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

                TextColumn::make('message')
                    ->placeholder('—')
                    ->wrap()
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Event')
                    ->options(WhatsAppConnectionEvent::options())
                    ->searchable(),
            ])
            ->emptyStateHeading('No connection events yet')
            ->emptyStateDescription('Connect, disconnect, or log out to start the history.')
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
    private function logConnectionEvent(WhatsAppConnectionEvent $event, array $context = []): void
    {
        app(WhatsAppConnectionLogService::class)->log($event, $context);
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

    private function refreshQrQuietly(): void
    {
        $wasOpen = $this->isConnectionOpen();
        $result = app(EvolutionInstanceService::class)->connectInstance();

        if (! $result['ok']) {
            return;
        }

        $this->applyQrResult($result, quiet: true);

        if (! $wasOpen && $this->isConnectionOpen()) {
            $this->handleSuccessfulConnect();
        }
    }

    /**
     * @param  array{ok: bool, status: string, qrBase64: string|null, message: string, raw: array<string, mixed>|null}  $result
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
            $this->qrBase64 = null;
            $this->qrGeneratedAt = 0;
            $this->statusMessage = 'WhatsApp is connected.';
            $this->loadConnectedInstanceDetails();
        }
    }

    /**
     * @return list<string>
     */
    public function allowedSenderNumbers(): array
    {
        return PhoneNumber::allowedWhatsAppSenders();
    }

    public function configuredDeviceLabel(): string
    {
        $label = config('services.evolution.device_label');

        return is_string($label) && trim($label) !== ''
            ? trim($label)
            : 'tido App (Evolution API)';
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
    }

    private function sendConnectedDatabaseNotification(): void
    {
        $recipient = auth()->user();

        if (! $recipient instanceof User || ! $recipient->notify_whatsapp_connection) {
            return;
        }

        Notification::make()
            ->title('WhatsApp connected')
            ->body('Your WhatsApp instance is linked and ready. You can send receipt photos anytime.')
            ->success()
            ->icon('heroicon-o-check-badge')
            ->actions([
                Action::make('openWhatsAppConnection')
                    ->label('Open WhatsApp Connection')
                    ->button()
                    ->url(static::getUrl(), shouldOpenInNewTab: true)
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipient);
    }

    private function sendDisconnectedDatabaseNotification(): void
    {
        $recipient = auth()->user();

        if (! $recipient instanceof User || ! $recipient->notify_whatsapp_connection) {
            return;
        }

        Notification::make()
            ->title('WhatsApp disconnected')
            ->body('Your WhatsApp session is closed or disconnected. Generate a new QR to reconnect.')
            ->warning()
            ->icon('heroicon-o-qr-code')
            ->actions([
                Action::make('openWhatsAppConnection')
                    ->label('Open WhatsApp Connection')
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

        $number = config('services.evolution.personal_number');

        if (! is_string($number) || $number === '') {
            Notification::make()
                ->title('Connected — welcome ping skipped')
                ->body('Set PERSONAL_WHATSAPP_NUMBER in .env to auto-message yourself on connect.')
                ->warning()
                ->send();

            return;
        }

        $this->welcomePingSent = true;

        SendWhatsAppConnectedAlertJob::dispatch($this->connectedNumber)
            ->delay(now()->addSeconds(5));

        Notification::make()
            ->title('Connected — welcome queued')
            ->body('A confirmation will be sent to '.$number.' shortly.')
            ->success()
            ->send();
    }
}
