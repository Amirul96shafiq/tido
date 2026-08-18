@php
    $statusColor = match ($this->connectionStatus) {
        'operational' => 'emerald',
        'degraded' => 'warning',
        'down' => 'danger',
        default => 'gray',
    };
    $statusIcon = match ($this->connectionStatus) {
        'operational' => 'heroicon-o-check-circle',
        'degraded' => 'heroicon-o-exclamation-triangle',
        'down' => 'heroicon-o-x-circle',
        default => 'heroicon-o-question-mark-circle',
    };
    $statusLabel = match ($this->connectionStatus) {
        'operational' => 'Operational',
        'degraded' => 'Degraded',
        'down' => 'Down',
        default => 'Unknown',
    };
@endphp

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] lg:items-start">
    <x-filament::section id="ollama-status">
        <x-slot name="heading">Status</x-slot>

        <div class="flex flex-col gap-5">
            <div class="flex w-full flex-col items-center justify-center rounded-xl bg-white px-4 py-6 dark:bg-slate-800">
                <div @class([
                    'relative mb-6 flex h-20 w-20 items-center justify-center rounded-full',
                    'bg-emerald-500/10' => $this->connectionStatus === 'operational',
                    'bg-warning-500/10' => $this->connectionStatus === 'degraded',
                    'bg-danger-500/10' => $this->connectionStatus === 'down',
                    'bg-gray-500/10 dark:bg-slate-500/10' => $this->connectionStatus === 'unknown',
                ])>
                    @if ($this->connectionStatus === 'operational')
                        <span
                            class="pointer-events-none absolute inset-0 rounded-full border-2 border-emerald-500/30"
                            style="animation: ollama-status-pulse 2s infinite"
                        ></span>
                    @endif

                    <x-filament::icon
                        :icon="$statusIcon"
                        @class([
                            'relative h-10 w-10',
                            'text-emerald-500' => $this->connectionStatus === 'operational',
                            'text-warning-500' => $this->connectionStatus === 'degraded',
                            'text-danger-500' => $this->connectionStatus === 'down',
                            'text-gray-400 dark:text-gray-500' => $this->connectionStatus === 'unknown',
                        ])
                    />
                </div>

                <h3 class="text-center text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ $statusLabel }}
                </h3>

                <p class="mt-4 max-w-sm text-center text-sm leading-6 text-gray-500 dark:text-gray-400">
                    {{ $this->statusMessage }}
                </p>
            </div>

            <dl class="rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-slate-700">
                <div class="flex flex-row items-baseline justify-between gap-3">
                    <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Configured model</dt>
                    <dd class="min-w-0 truncate text-right font-mono text-gray-950 dark:text-white">
                        {{ $this->configuredModel ?: '—' }}
                    </dd>
                </div>
            </dl>

            <div class="mt-2">
                <x-filament::modal
                    id="ollama-config-details"
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
                            <h2 class="fi-modal-heading">Configuration details</h2>
                        </div>
                    </x-slot>

                    <div class="fi-ollama-config-details-list divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
                        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Host</dt>
                            <dd class="break-all font-mono text-gray-950 dark:text-white">{{ $this->host }}</dd>
                        </div>

                        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Latency</dt>
                            <dd class="font-mono text-gray-950 dark:text-white">
                                {{ $this->latencyMs > 0 ? $this->latencyMs.' ms' : '—' }}
                            </dd>
                        </div>

                        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Timeout</dt>
                            <dd class="text-gray-950 dark:text-white">{{ $this->timeout }} s</dd>
                        </div>

                        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Context window</dt>
                            <dd class="text-gray-950 dark:text-white">{{ number_format($this->numCtx) }} tokens</dd>
                        </div>

                        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Max image dimension</dt>
                            <dd class="text-gray-950 dark:text-white">{{ number_format($this->maxImageDimension) }} px</dd>
                        </div>
                    </div>
                </x-filament::modal>
            </div>
        </div>

        <style>
            @keyframes ollama-status-pulse {
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
    </x-filament::section>

    <x-filament::section id="ollama-models">
        <x-slot name="heading">Models</x-slot>

        @if (count($this->availableModels) === 0)
            <p class="py-4 text-sm text-gray-500 dark:text-gray-400">
                @if ($this->connectionStatus === 'down' || $this->connectionStatus === 'unknown')
                    Model list unavailable — Ollama is not reachable.
                @else
                    No models are installed. Run <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-xs dark:bg-slate-700">ollama pull &lt;model&gt;</code> to install one.
                @endif
            </p>
        @else
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($this->availableModels as $model)
                    <div
                        class="flex flex-col gap-1 py-4 first:pt-0 last:pb-0"
                        wire:key="ollama-model-{{ $loop->index }}"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span @class([
                                'truncate font-mono text-sm font-semibold',
                                'text-primary-600 dark:text-primary-400' => $model['isConfigured'],
                                'text-gray-950 dark:text-white' => ! $model['isConfigured'],
                            ])>{{ $model['name'] }}</span>

                            @if ($model['isConfigured'])
                                <x-filament::badge color="primary" size="sm">
                                    Active
                                </x-filament::badge>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            @if ($model['family'] !== '—')
                                <span>{{ $model['family'] }}</span>
                            @endif

                            @if ($model['parameterSize'] !== '—')
                                <span>{{ $model['parameterSize'] }}</span>
                            @endif

                            @if ($model['quantization'] !== '—')
                                <span>{{ $model['quantization'] }}</span>
                            @endif

                            @if ($model['contextLength'] > 0)
                                <span>{{ number_format($model['contextLength']) }} ctx</span>
                            @endif

                            @if ($model['sizeBytes'] > 0)
                                <span>{{ $this->formattedSize($model['sizeBytes']) }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</div>
