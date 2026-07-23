<x-filament-panels::page>
    @php
        $summaryStatus = $this->summaryStatus();
        $summaryIcon = match ($summaryStatus) {
            \App\Enums\ServiceHealthStatus::Operational => 'heroicon-o-check-circle',
            \App\Enums\ServiceHealthStatus::Degraded => 'heroicon-o-exclamation-triangle',
            \App\Enums\ServiceHealthStatus::Down => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
        $services = $this->services();
        $operationalCount = collect($services)->filter(
            static fn (array $service): bool => $service['currentStatus'] === \App\Enums\ServiceHealthStatus::Operational,
        )->count();
        $degradedCount = collect($services)->filter(
            static fn (array $service): bool => $service['currentStatus'] === \App\Enums\ServiceHealthStatus::Degraded,
        )->count();
        $downCount = collect($services)->filter(
            static fn (array $service): bool => $service['currentStatus'] === \App\Enums\ServiceHealthStatus::Down,
        )->count();
    @endphp

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] lg:items-start">
        <x-filament::section class="h-full">
            <x-slot name="heading">
                Summary report
            </x-slot>

            <x-slot name="description">
                {{ $this->periodLabel() }}
            </x-slot>

            <div class="flex flex-col gap-5">
                <div class="flex w-full flex-col items-center justify-center rounded-xl bg-white px-4 py-6 dark:bg-slate-800">
                    <div @class([
                        'relative mb-6 flex h-20 w-20 items-center justify-center rounded-full',
                        'bg-emerald-500/10' => $summaryStatus === \App\Enums\ServiceHealthStatus::Operational,
                        'bg-warning-500/10' => $summaryStatus === \App\Enums\ServiceHealthStatus::Degraded,
                        'bg-danger-500/10' => $summaryStatus === \App\Enums\ServiceHealthStatus::Down,
                        'bg-gray-500/10 dark:bg-slate-500/10' => $summaryStatus === \App\Enums\ServiceHealthStatus::Unknown,
                    ])>
                        @if ($summaryStatus === \App\Enums\ServiceHealthStatus::Operational)
                            <span
                                class="pointer-events-none absolute inset-0 rounded-full border-2 border-emerald-500/30"
                                style="animation: service-status-pulse 2s infinite;"
                            ></span>
                        @endif

                        <x-filament::icon
                            :icon="$summaryIcon"
                            @class([
                                'relative h-10 w-10',
                                'text-emerald-500' => $summaryStatus === \App\Enums\ServiceHealthStatus::Operational,
                                'text-warning-500' => $summaryStatus === \App\Enums\ServiceHealthStatus::Degraded,
                                'text-danger-500' => $summaryStatus === \App\Enums\ServiceHealthStatus::Down,
                                'text-gray-400 dark:text-gray-500' => $summaryStatus === \App\Enums\ServiceHealthStatus::Unknown,
                            ])
                        />
                    </div>

                    <h3 class="text-center text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ $this->summaryTitle() }}
                    </h3>

                    <p class="mt-4 max-w-sm text-center text-sm leading-6 text-gray-500 dark:text-gray-400">
                        {{ $this->summaryMessage() }}
                    </p>
                </div>

                <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 px-4 py-3 text-sm dark:border-white/10">
                    <span class="text-gray-500 dark:text-gray-400">Monitored services</span>
                    <span class="font-medium text-gray-950 dark:text-white">{{ count($services) }}</span>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 px-3 py-4 text-center dark:border-white/10">
                        <span class="size-2 rounded-full bg-emerald-500"></span>
                        <span class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $operationalCount }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Operational</span>
                    </div>

                    <div class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 px-3 py-4 text-center dark:border-white/10">
                        <span class="size-2 rounded-full bg-warning-500"></span>
                        <span class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $degradedCount }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Degraded</span>
                    </div>

                    <div class="flex flex-col items-center gap-2 rounded-lg border border-gray-200 px-3 py-4 text-center dark:border-white/10">
                        <span class="size-2 rounded-full bg-danger-500"></span>
                        <span class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $downCount }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Down</span>
                    </div>
                </div>
            </div>

            <style>
                @keyframes service-status-pulse {
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

        <x-filament::section class="h-full">
            <x-slot name="heading">
                System status
            </x-slot>

            <x-slot name="description">
                {{ $this->periodLabel() }}
            </x-slot>

            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($this->services() as $service)
                    <div class="flex flex-col gap-3 py-5 first:pt-0 last:pb-0" wire:key="service-status-{{ $service['service']->value }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <x-filament::icon
                                    :icon="match ($service['currentStatus']) {
                                        \App\Enums\ServiceHealthStatus::Operational => 'heroicon-o-check-circle',
                                        \App\Enums\ServiceHealthStatus::Degraded => 'heroicon-o-exclamation-triangle',
                                        \App\Enums\ServiceHealthStatus::Down => 'heroicon-o-x-circle',
                                        default => 'heroicon-o-question-mark-circle',
                                    }"
                                    @class([
                                        'size-5 shrink-0',
                                        $service['currentStatus']->iconColorClass(),
                                    ])
                                />

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                        {{ $service['label'] }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $service['currentStatus']->label() }}
                                    </p>
                                </div>
                            </div>

                            <p class="shrink-0 text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $service['uptimeLabel'] }}
                            </p>
                        </div>

                        <div
                            class="flex h-8 w-full gap-1 overflow-hidden rounded-md bg-gray-100 p-px dark:bg-gray-800"
                            role="list"
                            aria-label="{{ $service['label'] }} uptime history"
                        >
                            @foreach ($service['pieces'] as $piece)
                                <div
                                    wire:key="service-status-piece-{{ $service['service']->value }}-{{ $piece['startsAt']->timestamp }}"
                                    class="group relative min-w-0 flex-1"
                                    role="listitem"
                                >
                                    <span
                                        @class([
                                            'block h-full w-full rounded-[1px]',
                                            $piece['status']->barColorClass(),
                                        ])
                                        x-tooltip="{
                                            content: @js($piece['tooltip']),
                                            theme: $store.theme,
                                        }"
                                        aria-label="{{ $piece['ariaLabel'] }}"
                                    ></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-sm text-gray-500 dark:text-gray-400">
                        No monitored services are configured yet.
                    </p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
