@php
    $statusColor = match ($this->connectionStatus) {
        'operational' => 'emerald',
        'degraded' => 'warning',
        'down' => 'danger',
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
        default => 'Unknown',
    };
    $activeModel = $this->activeModel();
    $activeModelName = is_array($activeModel) ? $activeModel['name'] : '—';
@endphp

<div class="flex flex-col gap-6">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 xl:items-stretch">
        <x-filament::section id="ollama-status" class="h-full">
            <x-slot name="heading">Status</x-slot>

            <div class="flex h-full flex-col gap-3 py-4">
                <div class="flex w-full flex-col items-center justify-center gap-4 rounded-xl bg-white px-0 dark:bg-slate-800">
                    <div class="flex w-full flex-col items-center text-center lg:max-w-md">
                        <div @class([
                            'relative mb-8 flex h-20 w-20 items-center justify-center rounded-full',
                            'bg-success-500/10' => $this->connectionStatus === 'operational',
                            'bg-warning-500/10' => $this->connectionStatus === 'degraded',
                            'bg-danger-500/10' => $this->connectionStatus === 'down',
                            'bg-gray-500/10 dark:bg-slate-500/10' => $this->connectionStatus === 'unknown',
                        ])>
                            @if ($this->connectionStatus === 'operational')
                                <span
                                    class="pointer-events-none absolute inset-0 rounded-full border-2 border-success-500/30"
                                    style="animation: wa-connected-pulse 2s infinite"
                                ></span>
                            @endif

                            <x-filament::icon
                                :icon="$statusIcon"
                                @class([
                                    'relative h-10 w-10',
                                    'text-success-500' => $this->connectionStatus === 'operational',
                                    'text-warning-500' => $this->connectionStatus === 'degraded',
                                    'text-danger-500' => $this->connectionStatus === 'down',
                                    'text-gray-400 dark:text-gray-500' => $this->connectionStatus === 'unknown',
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
                                        <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Installed models</dt>
                                        <dd class="min-w-0 truncate text-right font-mono text-gray-950 dark:text-white">
                                            {{ number_format($this->installedModelCount()) }}
                                        </dd>
                                    </div>
                                </dl>

                                <dl class="rounded-xl border border-gray-200 px-4 py-3 dark:border-slate-700">
                                    <div class="flex flex-row items-baseline justify-between gap-3">
                                        <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Model</dt>
                                        <dd class="min-w-0 truncate text-right font-mono text-gray-950 dark:text-white">
                                            {{ $activeModelName }}
                                        </dd>
                                    </div>
                                </dl>

                                <dl class="rounded-xl border border-gray-200 px-4 py-3 dark:border-slate-700">
                                    <div class="flex flex-row items-baseline justify-between gap-3">
                                        <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Ready checks</dt>
                                        <dd class="min-w-0 truncate text-right font-mono text-gray-950 dark:text-white">
                                            {{ $this->readyPipelineCheckCount() }}/{{ count($this->pipelineChecks) }}
                                        </dd>
                                    </div>
                                </dl>

                                <dl class="rounded-xl border border-gray-200 px-4 py-3 dark:border-slate-700">
                                    <div class="flex flex-col gap-3">
                                        <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Configuration details</dt>
                                        <dd class="w-full min-w-0">
                                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                <div class="rounded-xl border border-gray-200 bg-transparent px-4 py-4 dark:border-white/10">
                                                    <p class="text-xs font-medium uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Latency</p>
                                                    <p class="mt-2 text-lg font-semibold tabular-nums text-gray-950 dark:text-white">
                                                        {{ $this->latencyMs > 0 ? $this->latencyMs.' ms' : '—' }}
                                                    </p>
                                                </div>

                                                <div class="rounded-xl border border-gray-200 bg-transparent px-4 py-4 dark:border-white/10">
                                                    <p class="text-xs font-medium uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Context window</p>
                                                    <p class="mt-2 text-lg font-semibold tabular-nums text-gray-950 dark:text-white">
                                                        {{ number_format($this->numCtx) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="mt-5">
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
                                            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Source</dt>
                                            <dd class="text-gray-950 dark:text-white">{{ $this->settingsSourceLabel() }}</dd>
                                        </div>

                                        <div class="flex flex-col gap-3 px-6 py-4">
                                            <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                                                <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Model</dt>
                                                <dd class="flex min-w-0 flex-wrap items-center justify-start gap-2 sm:justify-end">
                                                    <span class="break-all font-mono text-gray-950 dark:text-white">
                                                        {{ $activeModelName }}
                                                    </span>

                                                    @if (is_array($activeModel) && $activeModel['isConfigured'])
                                                        <x-filament::badge color="primary" size="sm">
                                                            Active
                                                        </x-filament::badge>
                                                    @endif
                                                </dd>
                                            </div>

                                            @if (is_array($activeModel))
                                                <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">
                                                    Suitable for structured extraction workflows that need consistent JSON output.
                                                </p>

                                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                                    @if ($activeModel['family'] !== '—')
                                                        <span>{{ $activeModel['family'] }}</span>
                                                    @endif

                                                    @if ($activeModel['parameterSize'] !== '—')
                                                        <span>{{ $activeModel['parameterSize'] }}</span>
                                                    @endif

                                                    @if ($activeModel['quantization'] !== '—')
                                                        <span>{{ $activeModel['quantization'] }}</span>
                                                    @endif

                                                    @if ($activeModel['contextLength'] > 0)
                                                        <span>{{ number_format($activeModel['contextLength']) }} ctx</span>
                                                    @endif

                                                    @if ($activeModel['sizeBytes'] > 0)
                                                        <span>{{ $this->formattedSize($activeModel['sizeBytes']) }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

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

                                        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                                            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">PDF info binary</dt>
                                            <dd class="break-all font-mono text-gray-950 dark:text-white">{{ $this->pdfInfoBinary ?: '—' }}</dd>
                                        </div>

                                        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                                            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">PDF renderer binary</dt>
                                            <dd class="break-all font-mono text-gray-950 dark:text-white">{{ $this->pdfToCairoBinary ?: '—' }}</dd>
                                        </div>

                                        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                                            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">PDF text binary</dt>
                                            <dd class="break-all font-mono text-gray-950 dark:text-white">{{ $this->pdfToTextBinary ?: '—' }}</dd>
                                        </div>
                                    </div>
                                </x-filament::modal>
                            </div>
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
        </x-filament::section>

        <x-filament::section id="ollama-pipeline" class="h-full">
            <x-slot name="heading">Pipeline Readiness</x-slot>

            <div class="flex h-full flex-col gap-4">
                <div class="flex flex-col gap-4">
                    @foreach ($this->pipelineChecks as $check)
                        <div
                            class="rounded-xl border border-gray-200 px-4 py-4 dark:border-white/10"
                            wire:key="ollama-pipeline-check-{{ \Illuminate\Support\Str::slug($check['label']) }}"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $check['label'] }}
                                    </h3>
                                    <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                                        {{ $check['detail'] }}
                                    </p>
                                </div>

                                <span @class([
                                    'inline-flex shrink-0 items-center rounded-md px-2 py-1 text-xs font-medium',
                                    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' => $check['status'] === 'ready',
                                    'bg-warning-500/10 text-warning-600 dark:text-warning-400' => $check['status'] !== 'ready',
                                ])>
                                    {{ $check['status'] === 'ready' ? 'Ready' : 'Needs attention' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="rounded-xl border border-gray-200 px-4 py-4 dark:border-white/10">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Supported tasks</h3>

                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        @foreach ($this->supportedTaskGroups() as $group)
                            <div
                                class="rounded-xl border border-gray-200 bg-transparent px-4 py-4 dark:border-white/10"
                                wire:key="ollama-supported-task-group-{{ \Illuminate\Support\Str::slug($group['heading']) }}"
                            >
                                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $group['heading'] }}
                                </h4>

                                <ul class="mt-3 space-y-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                                    @foreach ($group['items'] as $item)
                                        <li
                                            class="flex items-start gap-2"
                                            wire:key="ollama-supported-task-{{ \Illuminate\Support\Str::slug($group['heading'].'-'.$item) }}"
                                        >
                                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-primary-500"></span>
                                            <span>{{ $item }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section id="ollama-activity">
        <x-slot name="heading">Receipt & Parsing Activity</x-slot>

        <div class="flex flex-col gap-5">
            <div class="rounded-xl border border-gray-200 px-4 py-4 dark:border-white/10">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Recent receipt & parsing activity</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            {{ $this->latestReceiptActivity }}
                        </p>
                    </div>

                    <x-filament::button
                        tag="a"
                        size="sm"
                        color="primary"
                        icon="heroicon-m-plus"
                        :href="\App\Filament\Pages\ReceiptUploadPage::getUrl()"
                        class="shrink-0 self-center sm:self-auto"
                    >
                        Upload Receipts
                    </x-filament::button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->activityStats as $stat)
                    <div
                        class="fi-ollama-activity-stat-card relative overflow-hidden rounded-xl border border-gray-200 px-4 py-4 pb-10 dark:border-white/10"
                        wire:key="ollama-activity-stat-{{ \Illuminate\Support\Str::slug($stat['label']) }}"
                    >
                        <p class="text-xs font-medium uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">
                            {{ $stat['label'] }}
                        </p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">
                            {{ $stat['value'] }}
                        </p>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            {{ $stat['description'] }}
                        </p>

                        @if (! empty($stat['chart']))
                            <x-tido.stat-sparkline
                                :chart-key="'ollama-activity-' . \Illuminate\Support\Str::slug($stat['label'])"
                                :values="$stat['chart']"
                                sparkline-class="fi-ollama-activity-sparkline"
                            />
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</div>
