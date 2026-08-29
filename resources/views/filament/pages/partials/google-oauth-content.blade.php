@php
    $statusColor = match ($this->connectionStatus) {
        'operational' => 'emerald',
        'degraded' => 'warning',
        'down' => 'danger',
        'unconfigured' => 'gray',
        default => 'gray',
    };
    $statusIcon = match ($this->connectionStatus) {
        'operational' => 'heroicon-o-check-badge',
        'degraded' => 'heroicon-o-exclamation-triangle',
        'down' => 'heroicon-o-x-circle',
        default => 'heroicon-o-question-mark-circle',
    };
    $statusLabel = match ($this->connectionStatus) {
        'operational' => 'Operational',
        'degraded' => 'Degraded',
        'down' => 'Down',
        'unconfigured' => 'Not configured',
        default => 'Unknown',
    };
    $signInButtonLabel = $this->enabled && filled($this->clientId) && $this->hasSavedSecret ? 'Visible' : 'Hidden';
    $linkedPrimary = $this->linkedPrimaryEmail ?? 'Not linked';
    $lastSignIn = $this->lastSuccessfulSignIn
        ? \Illuminate\Support\Carbon::parse($this->lastSuccessfulSignIn)->timezone(config('app.timezone'))->diffForHumans()
        : 'Never';
@endphp

<div class="flex flex-col gap-6">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 xl:items-stretch">
        <x-filament::section id="google-oauth-status" class="h-full">
            <x-slot name="heading">Status</x-slot>

            <div class="flex h-full flex-col gap-3 py-4">
                <div class="flex w-full flex-col items-center justify-center gap-4 rounded-xl bg-white px-0 dark:bg-slate-800">
                    <div class="flex w-full flex-col items-center text-center lg:max-w-md">
                        <div @class([
                            'relative mb-8 flex h-20 w-20 items-center justify-center rounded-full',
                            'bg-success-500/10' => $this->connectionStatus === 'operational',
                            'bg-warning-500/10' => $this->connectionStatus === 'degraded',
                            'bg-danger-500/10' => $this->connectionStatus === 'down',
                            'bg-gray-500/10 dark:bg-slate-500/10' => in_array($this->connectionStatus, ['unknown', 'unconfigured'], true),
                        ])>
                            @if ($this->connectionStatus === 'operational')
                                <span
                                    class="tido-status-pulse pointer-events-none absolute inset-0 rounded-full border-2 border-success-500/30"
                                ></span>
                            @endif

                            <x-filament::icon
                                :icon="$statusIcon"
                                @class([
                                    'relative h-10 w-10',
                                    'text-success-500' => $this->connectionStatus === 'operational',
                                    'text-warning-500' => $this->connectionStatus === 'degraded',
                                    'text-danger-500' => $this->connectionStatus === 'down',
                                    'text-gray-400 dark:text-gray-500' => in_array($this->connectionStatus, ['unknown', 'unconfigured'], true),
                                ])
                            />
                        </div>

                        <h3 class="text-center text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                            {{ $statusLabel }}
                        </h3>

                        <p class="mt-4 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            {{ $this->statusMessage }}
                        </p>
                    </div>
                </div>

                <div class="flex w-full flex-col items-center justify-center gap-4 rounded-xl bg-white px-0 dark:bg-slate-800">
                    <div class="flex w-full flex-col items-center text-center lg:max-w-md">
                        <div class="mt-6 flex w-full flex-col gap-3 text-left text-sm">
                            <dl class="rounded-xl border border-gray-200 px-4 py-3 dark:border-slate-700">
                                <div class="flex flex-row items-baseline justify-between gap-3">
                                    <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Sign-In Button</dt>
                                    <dd class="min-w-0 truncate text-right font-mono text-gray-950 dark:text-white">
                                        {{ $signInButtonLabel }}
                                    </dd>
                                </div>
                            </dl>

                            <dl class="rounded-xl border border-gray-200 px-4 py-3 dark:border-slate-700">
                                <div class="flex flex-row items-baseline justify-between gap-3">
                                    <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Linked Primary</dt>
                                    <dd class="min-w-0 truncate text-right font-mono text-gray-950 dark:text-white">
                                        {{ $linkedPrimary }}
                                    </dd>
                                </div>
                            </dl>

                            <dl class="rounded-xl border border-gray-200 px-4 py-3 dark:border-slate-700">
                                <div class="flex flex-row items-baseline justify-between gap-3">
                                    <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Last Successful Sign-In</dt>
                                    <dd class="min-w-0 truncate text-right font-mono text-gray-950 dark:text-white">
                                        {{ $lastSignIn }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="mt-5">
                            <x-filament::modal
                                id="google-oauth-config-details"
                                width="md"
                                slide-over
                                sticky-header
                                teleport="body"
                                :close-button="true"
                            >
                                <x-slot name="trigger">
                                    <x-filament::button color="gray" size="sm" type="button">
                                        View details
                                    </x-filament::button>
                                </x-slot>

                                <x-slot name="header">
                                    <div>
                                        <h2 class="fi-modal-heading">Configuration Details</h2>
                                    </div>
                                </x-slot>

                                <div class="divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                                    <x-tido.detail-row label="Source">
                                        {{ $this->settingsSourceLabel() }}
                                    </x-tido.detail-row>
                                    <x-tido.detail-row label="Client ID" :long="false">
                                        <span class="font-mono break-all">{{ filled($this->clientId) ? $this->clientId : '—' }}</span>
                                    </x-tido.detail-row>
                                    <x-tido.detail-row label="Client Secret" :long="false">
                                        {{ $this->maskedClientSecret() }}
                                    </x-tido.detail-row>
                                    <x-tido.detail-row label="Redirect URI" :long="false">
                                        <span class="font-mono break-all">{{ $this->redirectUri() }}</span>
                                    </x-tido.detail-row>
                                    @if ($this->lastTestMessage)
                                        <x-tido.detail-row label="Last Test" long>
                                            {{ $this->lastTestMessage }}
                                        </x-tido.detail-row>
                                    @endif
                                    @if ($this->latencyMs > 0)
                                        <x-tido.detail-row label="Last Test Latency" :long="false">
                                            {{ $this->latencyMs }} ms
                                        </x-tido.detail-row>
                                    @endif
                                </div>
                            </x-filament::modal>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section id="google-oauth-config" class="h-full">
            <x-slot name="heading">Configuration</x-slot>

            <dl class="grid gap-4 text-sm">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Client ID</dt>
                    <dd class="mt-1 font-mono break-all text-gray-950 dark:text-white">
                        {{ filled($this->clientId) ? $this->clientId : '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Client Secret</dt>
                    <dd class="mt-1 font-mono text-gray-950 dark:text-white">
                        {{ $this->maskedClientSecret() }}
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Redirect URI</dt>
                    <dd class="mt-1 flex flex-col gap-2">
                        <span class="font-mono break-all text-gray-950 dark:text-white">{{ $this->redirectUri() }}</span>
                        <x-filament::button
                            color="gray"
                            size="sm"
                            type="button"
                            x-on:click="{{ \App\Support\ClipboardCopy::alpineClickHandler($this->redirectUri(), 'Redirect URI copied.') }}"
                        >
                            Copy URI
                        </x-filament::button>
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Sign-In Enabled</dt>
                    <dd class="mt-1">
                        <x-filament::badge :color="$this->enabled ? 'success' : 'gray'">
                            {{ $this->enabled ? 'Enabled' : 'Disabled' }}
                        </x-filament::badge>
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Settings Source</dt>
                    <dd class="mt-1 text-gray-950 dark:text-white">{{ $this->settingsSourceLabel() }}</dd>
                </div>
            </dl>

            <div class="mt-6 space-y-2 text-sm text-gray-500 dark:text-gray-400">
                <p>
                    1. Open Google Cloud Console and configure the OAuth consent screen.<br />
                    2. Create a Web application OAuth client.<br />
                    3. Add the redirect URI shown above to Authorized redirect URIs.<br />
                    4. Paste the Client ID and Client Secret in <strong>Start Configure</strong>, then enable sign-in.
                </p>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section id="google-oauth-readiness">
        <x-slot name="heading">Readiness</x-slot>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($this->readinessChecks as $check)
                <div class="rounded-xl border border-gray-200 px-4 py-4 dark:border-slate-700">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $check['label'] }}</p>
                    <div class="mt-3">
                        <x-filament::badge :color="$check['status'] === 'ready' ? 'success' : 'warning'">
                            {{ $check['detail'] }}
                        </x-filament::badge>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section id="google-oauth-activity">
        <x-slot name="heading">Sign-In History</x-slot>

        {{ $this->table }}
    </x-filament::section>
</div>
