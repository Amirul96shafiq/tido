@php
    /** @var string $contentHeight */
    /** @var list<array<string, mixed>> $items */
    /** @var string $totalAmount */
    /** @var int $totalCount */
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'id' => $this->getDashboardSectionId(),
            ], escape: false)
            ->class(['fi-wi-due-recurrings'])
    "
>
    <x-filament::section>
        <x-slot name="heading">
            {{ $totalCount }} Recurring {{ $totalCount === 1 ? 'Due' : 'Dues' }}
        </x-slot>

        <x-slot name="afterHeader">
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $totalAmount }}
                </span>
                <x-filament::button
                    tag="a"
                    size="sm"
                    color="primary"
                    icon="heroicon-m-arrow-right"
                    :href="$manageUrl"
                >
                    Manage
                </x-filament::button>
            </div>
        </x-slot>

        @if (count($items) === 0)
            <div
                class="fi-wi-due-recurrings-empty flex flex-1 items-center justify-center"
                style="min-height: {{ $contentHeight }}"
            >
                <x-empty-state-panel
                    heading="No recurring due"
                    description="Active reminders appear here when an occurrence is due or overdue."
                    icon="heroicon-o-arrow-path"
                />
            </div>
        @else
            <ul
                class="custom-scrollbar mt-3 flex flex-1 flex-col gap-1 overflow-y-auto pr-2"
                style="min-height: {{ $contentHeight }}; max-height: {{ $contentHeight }}"
            >
                @foreach ($items as $item)
                    <li
                        wire:key="due-recurrings-{{ $item['id'] }}"
                        class="-mx-1 flex flex-col gap-2 rounded-xl px-4 py-3 transition-colors duration-200 hover:bg-gray-100 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-slate-700/60"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a
                                    @if ($item['editUrl'])
                                        href="{{ $item['editUrl'] }}"
                                    @endif
                                    class="truncate font-medium text-gray-950 dark:text-white"
                                >
                                    {{ $item['title'] }}
                                </a>
                                <span @class([
                                    'fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-1.5 py-0.5',
                                    'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400' => $item['status'] === 'due',
                                    'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400' => $item['status'] === 'overdue',
                                ])>
                                    {{ $item['statusLabel'] }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $item['type'] }} · {{ $item['amount'] }} · due {{ $item['dueOn'] }}
                                @if ($item['progress'] !== null)
                                    · {{ $item['progress'] }}% of goal
                                @endif
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <x-filament::button
                                size="sm"
                                color="gray"
                                wire:click="skipOccurrence({{ $item['id'] }})"
                                wire:confirm="Skip this occurrence?"
                            >
                                Skip
                            </x-filament::button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
