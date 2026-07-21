<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section class="order-2 lg:order-1">
            <x-slot name="heading">
                Connection
            </x-slot>
            <x-slot name="description">
                Pair Evolution with your WhatsApp (Linked Devices).
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
                    1. Click <strong>Connect</strong> and choose <strong>Scan QR code</strong> or <strong>Pair with code</strong> before the timer hits 0.<br>
                    2. <strong>QR:</strong> scan from another screen — WhatsApp → <strong>Linked Devices</strong> → <strong>Link a Device</strong>.<br>
                    3. <strong>Pair with code:</strong> enter the <em>same</em> WhatsApp number as the phone that will type the code, copy the code, then WhatsApp → <strong>Linked Devices</strong> → <strong>Link with phone number instead</strong>. Use a fresh code before the timer hits 0.<br>
                    4. When status is <strong>open</strong>, the webhook registers automatically. Optionally send a <strong>Send Test Ping</strong>.
                </p>
                <p>
                    If linking fails: use <strong>Cancel connecting</strong> to stop a QR/pairing attempt, or <strong>Sign out Current Session</strong> when already linked, then connect again. Do not scan the QR printed in the Evolution terminal — only the image on this page.
                </p>
            </div>
        </x-filament::section>

        <x-filament::section class="order-1 lg:order-2">
            <x-slot name="heading">
                Link device
            </x-slot>
            <x-slot name="description">
                @if ($this->isConnectionOpen())
                    Already connected — no link code needed.
                @elseif (filled($pairingCode))
                    Enter the code in WhatsApp before expiry. A new code is fetched automatically when the timer ends.
                @elseif (filled($qrBase64))
                    Scan before expiry. A new QR is fetched automatically when the timer ends.
                @else
                    Disconnected — use Connect to scan a QR or pair with a code.
                @endif
            </x-slot>

            <div
                @if ($this->getPollingInterval())
                    wire:poll.{{ $this->getPollingInterval() }}.keep-alive="refreshStatus"
                @endif
                class="flex min-h-72 flex-col items-center justify-center gap-4 rounded-xl bg-white p-6 dark:bg-slate-800"
            >
                @if (filled($pairingCode) && ! $this->isConnectionOpen())
                    <div
                        wire:key="wa-pair-timer-{{ $pairingCodeGeneratedAt }}"
                        x-data="{
                            ttl: {{ \App\Filament\Pages\WhatsAppConnectionPage::CONNECT_TTL_SECONDS }},
                            generatedAt: {{ $pairingCodeGeneratedAt }},
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
                        class="flex w-full max-w-sm flex-col items-center gap-4"
                    >
                        <div class="text-center">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Expires in
                            </p>
                            <p
                                class="font-mono text-4xl font-semibold tabular-nums"
                                :class="expired ? 'text-danger-600 dark:text-danger-400' : (remaining <= 10 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-950 dark:text-white')"
                                x-text="expired ? '0s' : remaining + 's'"
                            ></p>
                            <p
                                class="mt-1 text-xs"
                                :class="expired ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500 dark:text-gray-400'"
                                x-text="expired ? 'Expired — refreshing…' : 'Enter in WhatsApp Linked Devices'"
                            ></p>
                        </div>

                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-slate-700">
                            <div
                                class="h-full rounded-full transition-[width] duration-200 ease-linear"
                                :class="expired ? 'bg-danger-500' : (remaining <= 10 ? 'bg-warning-500' : 'bg-primary-500')"
                                :style="`width: ${percent}%`"
                            ></div>
                        </div>

                        @if (filled($pairingNumber))
                            <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                                Linking account
                                <span class="font-mono text-gray-950 dark:text-white">{{ $pairingNumber }}</span>
                            </p>
                        @endif

                        <p
                            class="font-mono text-3xl font-bold tracking-[0.2em] text-gray-950 dark:text-white"
                            :class="expired && 'opacity-40'"
                            wire:key="wa-pair-code-{{ md5($pairingCode) }}"
                        >
                            {{ $this->formattedPairingCode() }}
                        </p>

                        <x-filament::button
                            type="button"
                            color="gray"
                            icon="heroicon-o-clipboard-document"
                            wire:click="copyPairingCode"
                            :disabled="blank($pairingCode)"
                        >
                            Copy code
                        </x-filament::button>

                        <p class="text-center text-xs leading-5 text-gray-500 dark:text-gray-400">
                            WhatsApp → Linked Devices → Link a Device → <strong>Link with phone number instead</strong>
                        </p>
                    </div>
                @elseif (filled($qrBase64) && ! $this->isConnectionOpen())
                    <div
                        wire:key="wa-qr-timer-{{ $qrGeneratedAt }}"
                        x-data="{
                            ttl: {{ \App\Filament\Pages\WhatsAppConnectionPage::CONNECT_TTL_SECONDS }},
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
                                :class="expired ? 'text-danger-600 dark:text-danger-400' : (remaining <= 10 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-950 dark:text-white')"
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
                                :class="expired ? 'bg-danger-500' : (remaining <= 10 ? 'bg-warning-500' : 'bg-primary-500')"
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
                                        'connectedVia' => $connectedVia?->label(),
                                        'connectedIntegration' => $connectedIntegration,
                                        'connectedInstanceId' => $connectedInstanceId,
                                        'connectedMessageCount' => $connectedMessageCount,
                                        'connectedContactCount' => $connectedContactCount,
                                        'connectedChatCount' => $connectedChatCount,
                                        'connectedUpdatedAt' => $connectedUpdatedAt,
                                        'deviceLabel' => $this->effectiveDeviceLabel(),
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
                                icon="heroicon-o-link"
                                class="relative h-10 w-10 text-gray-400 dark:text-gray-500"
                            />
                        </div>

                        <h3 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                            Not connected
                        </h3>

                        <p class="mt-4 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            Click <strong>Connect</strong> to scan a QR code or pair with a phone number before the timer expires.
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

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
