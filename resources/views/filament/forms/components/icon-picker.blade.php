@php
    use Filament\Support\Enums\Width;

    $statePath = $getStatePath();
    $modalId = 'icon-picker-'.str_replace(['.', '[', ']'], '-', $statePath);
    $selectedLabel = \App\Filament\Forms\Components\IconPicker::iconOptionLabel($getState());
    $icons = $getIconsForPicker();
    $curatedIcons = $getCuratedIconsForPicker();
    $pageSize = $getPageSize();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            search: '',
            limit: {{ $pageSize }},
            pageSize: {{ $pageSize }},
            icons: {{ \Illuminate\Support\Js::from($icons) }},
            get filtered() {
                const query = this.search.trim().toLowerCase()

                if (! query) {
                    return this.icons
                }

                return this.icons.filter((icon) =>
                    icon.label.toLowerCase().includes(query)
                    || icon.value.toLowerCase().includes(query)
                )
            },
            get visible() {
                return this.filtered.slice(0, this.limit)
            },
            get hasMore() {
                return this.limit < this.filtered.length
            },
            get selectedLabel() {
                const selected = this.icons.find((icon) => icon.value === this.state)

                return selected ? selected.label : null
            },
            select(value) {
                this.state = value
                this.search = ''
                this.limit = this.pageSize
                this.$dispatch('close-modal', { id: @js($modalId) })
            },
            loadMore() {
                this.limit += this.pageSize
            },
            resetBrowse() {
                this.search = ''
                this.limit = this.pageSize
            },
        }"
        {{ $getExtraAttributeBag()->class(['fi-fo-icon-picker flex flex-col gap-3']) }}
    >
        @if (count($curatedIcons))
            <div class="@container flex flex-col gap-2">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    Quick picks
                </p>
                <div class="flex flex-nowrap gap-2 overflow-hidden">
                    @foreach ($curatedIcons as $index => $icon)
                        @php
                            // size-10 (2.5rem) + gap-2 (0.5rem) per extra icon
                            $visibilityClass = match (true) {
                                $index < 3 => 'flex',
                                $index < 4 => 'hidden @[11.5rem]:flex',
                                $index < 5 => 'hidden @[14.5rem]:flex',
                                $index < 6 => 'hidden @[17.5rem]:flex',
                                $index < 7 => 'hidden @[20.5rem]:flex',
                                default => 'hidden',
                            };
                        @endphp

                        <button
                            type="button"
                            x-on:click="select(@js($icon['value']))"
                            class="{{ $visibilityClass }} size-10 shrink-0 items-center justify-center rounded-lg border transition"
                            :class="state === @js($icon['value'])
                                ? 'border-primary-600 bg-primary-50 text-primary-700 dark:border-primary-400 dark:bg-primary-400/10 dark:text-primary-300'
                                : 'border-gray-200 text-gray-700 hover:border-primary-400 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5'"
                            title="{{ $icon['label'] }}"
                        >
                            {!! $icon['html'] !!}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between gap-3">
            <p class="truncate text-sm text-gray-600 dark:text-gray-300">
                <span x-text="selectedLabel ?? @js($selectedLabel ?? 'No icon selected')"></span>
            </p>

            <x-filament::modal
                :id="$modalId"
                :width="Width::TwoExtraLarge"
                sticky-header
                teleport="body"
                :close-button="true"
                x-on:open-modal.window="if ($event.detail.id === @js($modalId)) resetBrowse()"
            >
                <x-slot name="trigger">
                    <x-filament::button color="gray" size="sm" type="button">
                        <span x-text="state ? 'Change icon' : 'Choose icon'"></span>
                    </x-filament::button>
                </x-slot>

                <x-slot name="header">
                    <div class="flex flex-col gap-3">
                        <div>
                            <h2 class="fi-modal-heading">
                                Choose icon
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Search or browse Heroicons. Click an icon to select it.
                            </p>
                        </div>

                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="search"
                                x-model="search"
                                x-on:input="limit = pageSize"
                                placeholder="Search icons…"
                            />
                        </x-filament::input.wrapper>
                    </div>
                </x-slot>

                <div class="flex flex-col gap-4">
                    <div class="grid max-h-96 grid-cols-3 gap-2 overflow-y-auto sm:grid-cols-4 md:grid-cols-6">
                        <template x-for="icon in visible" :key="icon.value">
                            <button
                                type="button"
                                x-on:click="select(icon.value)"
                                class="flex flex-col items-center gap-2 rounded-xl border p-3 text-center transition"
                                :class="state === icon.value
                                    ? 'border-primary-600 bg-primary-50 text-primary-700 dark:border-primary-400 dark:bg-primary-400/10 dark:text-primary-300'
                                    : 'border-gray-200 text-gray-700 hover:border-primary-400 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5'"
                            >
                                <span class="flex size-8 items-center justify-center" x-html="icon.html"></span>
                                <span class="line-clamp-2 text-xs font-medium" x-text="icon.label"></span>
                            </button>
                        </template>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            <span x-text="`Showing ${visible.length} of ${filtered.length}`"></span>
                        </p>

                        <x-filament::button
                            color="gray"
                            size="sm"
                            type="button"
                            x-show="hasMore"
                            x-on:click="loadMore()"
                        >
                            Load more
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::modal>
        </div>
    </div>
</x-dynamic-component>
