<x-filament-panels::page>
    @php
        $summaryStatus = $this->summaryStatus();
        $summaryColor = match ($summaryStatus) {
            \App\Enums\ServiceHealthStatus::Operational => 'success',
            \App\Enums\ServiceHealthStatus::Degraded => 'warning',
            \App\Enums\ServiceHealthStatus::Down => 'danger',
            default => 'gray',
        };
        $summaryIcon = match ($summaryStatus) {
            \App\Enums\ServiceHealthStatus::Operational => 'heroicon-o-check-circle',
            \App\Enums\ServiceHealthStatus::Degraded => 'heroicon-o-exclamation-triangle',
            \App\Enums\ServiceHealthStatus::Down => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    @endphp

    <div class="grid gap-6">
        <div @class([
            'rounded-xl border px-5 py-4',
            'border-success-300 bg-success-50 dark:border-success-500/30 dark:bg-success-500/10' => $summaryColor === 'success',
            'border-warning-300 bg-warning-50 dark:border-warning-500/30 dark:bg-warning-500/10' => $summaryColor === 'warning',
            'border-danger-300 bg-danger-50 dark:border-danger-500/30 dark:bg-danger-500/10' => $summaryColor === 'danger',
            'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40' => $summaryColor === 'gray',
        ])>
            <div class="flex items-start gap-3">
                <x-filament::icon
                    :icon="$summaryIcon"
                    @class([
                        'mt-0.5 size-6 shrink-0',
                        'text-success-600 dark:text-success-400' => $summaryColor === 'success',
                        'text-warning-600 dark:text-warning-400' => $summaryColor === 'warning',
                        'text-danger-600 dark:text-danger-400' => $summaryColor === 'danger',
                        'text-gray-500 dark:text-gray-400' => $summaryColor === 'gray',
                    ])
                />

                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->summaryTitle() }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {{ $this->summaryMessage() }}
                    </p>
                </div>
            </div>
        </div>

        <x-filament::section>
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
