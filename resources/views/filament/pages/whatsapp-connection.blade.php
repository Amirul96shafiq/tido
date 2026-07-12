<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section class="order-2 lg:order-1">
            <x-slot name="heading">
                Connection
            </x-slot>
            <x-slot name="description">
                Pair Evolution with your personal WhatsApp (Linked Devices).
            </x-slot>

            <dl class="grid gap-4 text-sm">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Instance</dt>
                    <dd class="mt-1 font-mono text-gray-950 dark:text-white">
                        {{ config('services.evolution.instance_name', 'tido') }}
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">API URL</dt>
                    <dd class="mt-1 break-all font-mono text-gray-950 dark:text-white">
                        {{ config('services.evolution.api_url') }}
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="mt-1">
                        <x-filament::badge
                            :color="match (strtolower($connectionStatus)) {
                                'open', 'connected' => 'success',
                                'connecting', 'close', 'closed' => 'warning',
                                'unconfigured', 'unreachable', 'error' => 'danger',
                                default => 'gray',
                            }"
                        >
                            {{ $connectionStatus }}
                        </x-filament::badge>
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Webhook URL</dt>
                    <dd class="mt-1 break-all font-mono text-gray-950 dark:text-white">
                        {{ $webhookUrl }}
                    </dd>
                </div>
                @if (filled($statusMessage))
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">Message</dt>
                        <dd class="mt-1 text-gray-950 dark:text-white">
                            {{ $statusMessage }}
                        </dd>
                    </div>
                @endif
            </dl>

            <div class="mt-6 space-y-2 text-sm text-gray-500 dark:text-gray-400">
                <p>
                    1. Click <strong>Generate / Refresh QR</strong> and scan <strong>before the timer hits 0</strong>.<br>
                    2. Phone: WhatsApp → <strong>Linked Devices</strong> → <strong>Link a Device</strong> (not the normal camera).<br>
                    3. When status is <strong>open</strong>, the webhook registers automatically. Optionally send a <strong>Send Test Ping</strong>.
                </p>
                <p>
                    If linking fails: use <strong>Logout Current Session</strong>, then generate a new QR. Do not scan the QR printed in the Evolution terminal — only the image on this page.
                </p>
            </div>
        </x-filament::section>

        <x-filament::section class="order-1 lg:order-2">
            <x-slot name="heading">
                QR code
            </x-slot>
            <x-slot name="description">
                @if ($this->isConnectionOpen())
                    Already connected — no QR needed.
                @elseif (filled($qrBase64))
                    Scan before expiry. A new QR is fetched automatically when the timer ends.
                @else
                    Disconnected — generate a QR to link your phone.
                @endif
            </x-slot>

            <div
                @if ($this->getPollingInterval())
                    wire:poll.{{ $this->getPollingInterval() }}="refreshStatus"
                @endif
                class="flex min-h-72 flex-col items-center justify-center gap-4 rounded-xl bg-white p-6 dark:bg-slate-800"
            >
                @if (filled($qrBase64) && ! $this->isConnectionOpen())
                    <div
                        wire:key="wa-qr-timer-{{ $qrGeneratedAt }}"
                        x-data="{
                            ttl: {{ \App\Filament\Pages\WhatsAppConnectionPage::QR_TTL_SECONDS }},
                            generatedAt: {{ $qrGeneratedAt }},
                            now: Math.floor(Date.now() / 1000),
                            get remaining() {
                                return Math.max(0, this.ttl - (this.now - this.generatedAt));
                            },
                            get percent() {
                                return Math.round((this.remaining / this.ttl) * 100);
                            },
                            get expired() {
                                return this.remaining <= 0;
                            },
                        }"
                        x-init="setInterval(() => { now = Math.floor(Date.now() / 1000) }, 250)"
                        class="flex w-full max-w-xs flex-col items-center gap-3"
                    >
                        <div class="text-center">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Expires in
                            </p>
                            <p
                                class="font-mono text-4xl font-semibold tabular-nums"
                                :class="expired ? 'text-danger-600 dark:text-danger-400' : (remaining <= 5 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-950 dark:text-white')"
                                x-text="expired ? '0s' : remaining + 's'"
                            ></p>
                            <p
                                class="mt-1 text-xs"
                                :class="expired ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500 dark:text-gray-400'"
                                x-text="expired ? 'Expired — refreshing…' : 'Scan now with Linked Devices'"
                            ></p>
                        </div>

                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-slate-700">
                            <div
                                class="h-full rounded-full transition-[width] duration-200 ease-linear"
                                :class="expired ? 'bg-danger-500' : (remaining <= 5 ? 'bg-warning-500' : 'bg-primary-500')"
                                :style="`width: ${percent}%`"
                            ></div>
                        </div>

                        <img
                            src="{{ $qrBase64 }}"
                            alt="WhatsApp QR code"
                            class="h-64 w-64 rounded-lg border border-gray-200 object-contain dark:border-slate-700"
                            :class="expired && 'opacity-40'"
                            wire:key="wa-qr-{{ md5($qrBase64) }}"
                        />
                    </div>
                @elseif ($this->isConnectionOpen())
                    <div class="flex w-full max-w-md flex-col items-center px-4 py-6 text-center">
                        <div class="relative mb-8 flex h-20 w-20 items-center justify-center rounded-full bg-success-500/10">
                            <span
                                class="pointer-events-none absolute inset-0 rounded-full border-2 border-success-500/30"
                                style="animation: wa-connected-pulse 2s infinite;"
                            ></span>
                            <x-filament::icon
                                icon="heroicon-o-check-badge"
                                class="relative h-10 w-10 text-success-500"
                            />
                        </div>

                        <h3 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                            Connected
                        </h3>

                        <p class="mt-4 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            Your WhatsApp instance is linked and ready. The webhook is registered automatically — send a test ping anytime to confirm outbound messages.
                        </p>

                        <dl class="mt-6 w-full divide-y divide-gray-200 rounded-xl border border-gray-200 text-left text-sm dark:divide-slate-700 dark:border-slate-700">
                            <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                                <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Connected number</dt>
                                <dd class="font-mono text-gray-950 dark:text-white">
                                    {{ $connectedNumber ?? 'Unknown — refresh status' }}
                                </dd>
                            </div>

                            @if (filled($connectedProfileName))
                                <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                                    <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Profile</dt>
                                    <dd class="text-gray-950 dark:text-white">
                                        {{ $connectedProfileName }}
                                    </dd>
                                </div>
                            @endif

                            <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                                <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Contact allowlist</dt>
                                <dd class="font-mono text-gray-950 dark:text-white">
                                    @forelse ($this->allowedSenderNumbers() as $allowedNumber)
                                        <span @class(['block' => ! $loop->first])>{{ $allowedNumber }}</span>
                                    @empty
                                        <span class="font-sans text-warning-600 dark:text-warning-400">Not set — set PERSONAL_WHATSAPP_NUMBER</span>
                                    @endforelse
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-5">
                            <div class="fi-wa-connection-details">
                                <x-filament::modal
                                    id="whatsapp-connection-details"
                                    width="md"
                                    slide-over
                                    sticky-header
                                    teleport="body"
                                    :close-button="true"
                                    class="fi-wa-connection-details"
                                >
                                    <x-slot name="trigger">
                                        <x-filament::button color="gray" size="sm" type="button">
                                            View details
                                        </x-filament::button>
                                    </x-slot>

                                    <x-slot name="header">
                                        <div>
                                            <h2 class="fi-modal-heading">
                                                Connection details
                                            </h2>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                Full Evolution instance information for this linked WhatsApp session.
                                            </p>
                                        </div>
                                    </x-slot>

                                    @include('filament.pages.partials.whatsapp-connection-details', [
                                        'connectedNumber' => $connectedNumber,
                                        'connectedProfileName' => $connectedProfileName,
                                        'connectedIntegration' => $connectedIntegration,
                                        'connectedInstanceId' => $connectedInstanceId,
                                        'connectedMessageCount' => $connectedMessageCount,
                                        'connectedContactCount' => $connectedContactCount,
                                        'connectedChatCount' => $connectedChatCount,
                                        'connectedUpdatedAt' => $connectedUpdatedAt,
                                        'deviceLabel' => $this->configuredDeviceLabel(),
                                        'allowedSenderNumbers' => $this->allowedSenderNumbers(),
                                    ])
                                </x-filament::modal>
                            </div>
                        </div>
                    </div>

                    <style>
                        @keyframes wa-connected-pulse {
                            0% {
                                transform: scale(1);
                                opacity: 1;
                            }
                            100% {
                                transform: scale(1.4);
                                opacity: 0;
                            }
                        }
                    </style>
                @else
                    <div class="flex w-full max-w-sm flex-col items-center px-4 py-6 text-center">
                        <div class="relative mb-8 flex h-20 w-20 items-center justify-center rounded-full bg-gray-500/10 dark:bg-slate-500/10">
                            <x-filament::icon
                                icon="heroicon-o-qr-code"
                                class="relative h-10 w-10 text-gray-400 dark:text-gray-500"
                            />
                        </div>

                        <h3 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                            Not connected
                        </h3>

                        <p class="mt-4 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            No QR yet. Click <strong>Generate / Refresh QR</strong>, then scan it in WhatsApp → Linked Devices before the timer expires.
                        </p>
                    </div>
                @endif
            </div>
        </x-filament::section>
    </div>

    <x-filament::section class="mt-6">
        <x-slot name="heading">
            Connection history
        </x-slot>
        <x-slot name="description">
            Previous Evolution session events for this tido instance.
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
