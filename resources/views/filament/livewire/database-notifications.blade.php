@php
    use Filament\Support\Enums\Alignment;
    use Filament\Support\Icons\Heroicon;
    use Filament\Support\View\Components\BadgeComponent;
    use Illuminate\View\ComponentAttributeBag;

    $notifications = $this->getNotifications();
    $unreadNotificationsCount = $this->getUnreadNotificationsCount();
    $hasAnyNotifications = $this->hasAnyNotifications();
    $hasNotifications = $notifications->count() > 0;
    $isPaginated = $notifications instanceof \Illuminate\Contracts\Pagination\Paginator && $notifications->hasPages();
    $pollingInterval = $this->getPollingInterval();
    $activeFiltersCount = $this->getActiveFiltersCount();
@endphp

<div class="fi-no-database">
    <style>
        /* Unread badge sits absolute beside the heading; avoid clipping it. */
        .fi-no-database .fi-modal-window-ctn > .fi-modal-window > .fi-modal-header {
            overflow: visible;
            padding-top: 1.75rem;
            z-index: 20;
        }

        .fi-no-database .fi-modal-window-ctn > .fi-modal-window .fi-modal-heading {
            overflow: visible;
        }

        .fi-no-database .fi-modal-window-ctn > .fi-modal-window .fi-modal-heading .fi-badge {
            top: 0;
        }

        .fi-no-database .fi-modal-window-ctn > .fi-modal-window .fi-modal-content {
            /* No overflow-x here — it forces overflow-y:auto and a double scrollbar. */
            position: relative;
            z-index: 0;
        }

        /*
         * Must beat `.fi-modal-content > :first-child { padding-top: 1rem }`
         * so the empty panel is not cramped under the sticky header border.
         */
        .fi-no-database .fi-modal-window-ctn > .fi-modal-window .fi-modal-content > :first-child.fi-no-database-empty-filter,
        .fi-no-database .fi-modal-window-ctn > .fi-modal-window .fi-modal-content > .fi-no-database-empty-filter {
            padding-top: 3rem;
            padding-bottom: 2.5rem;
            padding-inline: 1.5rem;
            overflow: visible;
            position: relative;
            z-index: 0;
        }

        .fi-no-database .fi-no-empty-panel-icon,
        .fi-no-database .fi-no-empty-panel-icon-ctn {
            z-index: 0;
        }
    </style>

    <x-filament::modal
        :alignment="$hasAnyNotifications ? null : Alignment::Center"
        close-button
        :description="$hasAnyNotifications ? null : __('filament-notifications::database.modal.empty.description')"
        :heading="$hasAnyNotifications ? null : __('filament-notifications::database.modal.empty.heading')"
        :icon="$hasAnyNotifications ? null : Heroicon::OutlinedBellSlash"
        :icon-alias="
            $hasAnyNotifications
                ? null
                : \Filament\Notifications\View\NotificationsIconAlias::DATABASE_MODAL_EMPTY_STATE
        "
        :icon-color="$hasAnyNotifications ? null : 'gray'"
        id="database-notifications"
        slide-over
        :sticky-header="$hasAnyNotifications"
        :sticky-footer="$isPaginated"
        teleport="body"
        width="md"
        class="fi-no-database"
        :attributes="
            new ComponentAttributeBag([
                'wire:poll.' . $pollingInterval => $pollingInterval ? '' : false,
            ])
        "
    >
        @if ($trigger = $this->getTrigger())
            <x-slot name="trigger">
                {{ $trigger->with(['unreadNotificationsCount' => $unreadNotificationsCount]) }}
            </x-slot>
        @endif

        @if ($hasAnyNotifications)
            <x-slot name="header">
                <div class="w-full">
                    <h2 class="fi-modal-heading">
                        {{ __('filament-notifications::database.modal.heading') }}

                        @if ($unreadNotificationsCount)
                            <span
                                {{
                                    (new ComponentAttributeBag)->color(BadgeComponent::class, 'primary')->class([
                                        'fi-badge fi-size-xs',
                                    ])
                                }}
                            >
                                {{ $unreadNotificationsCount }}
                            </span>
                        @endif
                    </h2>

                    <div class="fi-ac">
                        @if ($unreadNotificationsCount && $this->markAllNotificationsAsReadAction?->isVisible())
                            {{ $this->markAllNotificationsAsReadAction }}
                        @endif

                        @if ($this->clearNotificationsAction?->isVisible())
                            {{ $this->clearNotificationsAction }}
                        @endif
                    </div>

                    <div class="fi-no-database-toolbar mt-3 flex w-full items-center gap-3">
                        <div
                            class="fi-ta-search-field fi-no-database-search relative min-w-0 flex-1"
                            wire:ignore.self
                            x-data="{
                                query: @js($this->search),
                                sync() {
                                    $wire.set('search', this.query)
                                },
                                clear() {
                                    this.query = ''
                                    this.sync()
                                },
                            }"
                            x-on:database-notifications-search-cleared.window="query = ''"
                        >
                            <label class="fi-sr-only" for="database-notifications-search">
                                Search notifications
                            </label>

                            <x-filament::input.wrapper
                                inline-prefix
                                :prefix-icon="Heroicon::MagnifyingGlass"
                                wire:target="search"
                            >
                                <x-filament::input
                                    id="database-notifications-search"
                                    type="search"
                                    autocomplete="off"
                                    maxlength="1000"
                                    placeholder="Search notifications..."
                                    inline-prefix
                                    x-model="query"
                                    x-bind:class="query.length > 0 ? 'pe-8' : ''"
                                    x-on:input.debounce.500ms="sync()"
                                    x-on:keydown.enter.prevent="sync()"
                                    class="fi-no-database-search-input"
                                />
                            </x-filament::input.wrapper>

                            <div
                                class="fi-no-database-search-clear absolute inset-e-1 top-1/2 z-10 -translate-y-1/2"
                                x-show="query.length > 0"
                                x-cloak
                            >
                                <x-filament::icon-button
                                    type="button"
                                    size="sm"
                                    color="gray"
                                    :icon="Heroicon::XMark"
                                    label="Clear search"
                                    x-on:click="clear()"
                                    :loading-indicator="false"
                                />
                            </div>

                            <style>
                                .fi-no-database-search-input::-webkit-search-cancel-button,
                                .fi-no-database-search-input::-webkit-search-decoration {
                                    -webkit-appearance: none;
                                    appearance: none;
                                    display: none;
                                }
                            </style>
                        </div>

                        <div class="fi-ta-filters-trigger-action-ctn">
                            <button
                                type="button"
                                class="fi-icon-btn fi-size-md fi-color fi-color-gray relative"
                                wire:click="toggleFilters"
                                wire:loading.attr="disabled"
                                wire:target="toggleFilters, resetFilters, search, filters"
                                aria-label="{{ __('filament-tables::table.actions.filter.label') }}"
                                aria-expanded="{{ $this->filtersOpen ? 'true' : 'false' }}"
                                x-tooltip="{
                                    content: @js(__('filament-tables::table.actions.filter.label')),
                                    theme: $store.theme,
                                }"
                            >
                                <x-filament::icon
                                    :icon="Heroicon::Funnel"
                                    class="fi-icon fi-size-md"
                                    wire:loading.remove.delay.default
                                    wire:target="toggleFilters, resetFilters, search, filters"
                                />

                                <x-filament::loading-indicator
                                    class="fi-icon fi-size-md"
                                    wire:loading.delay.default
                                    wire:target="toggleFilters, resetFilters, search, filters"
                                />

                                @if ($activeFiltersCount > 0)
                                    <span
                                        {{
                                            (new ComponentAttributeBag)->color(BadgeComponent::class, 'primary')->class([
                                                'fi-badge fi-size-xs absolute -top-1 -end-1',
                                            ])
                                        }}
                                        wire:loading.remove.delay.default
                                        wire:target="toggleFilters, resetFilters, search, filters"
                                    >
                                        {{ $activeFiltersCount }}
                                    </span>
                                @endif
                            </button>

                            @if ($this->filtersOpen)
                                <div
                                    wire:key="database-notifications-filters-panel"
                                    class="fi-no-database-filters-panel"
                                >
                                    <div class="mb-3 flex items-center justify-between gap-2">
                                        <h3 class="text-sm font-medium text-gray-950 dark:text-white">
                                            {{ __('filament-tables::table.filters.heading') }}
                                        </h3>

                                        <x-filament::link
                                            tag="button"
                                            color="danger"
                                            size="sm"
                                            wire:click="resetFilters"
                                            type="button"
                                        >
                                            {{ __('filament-tables::table.filters.actions.reset.label') }}
                                        </x-filament::link>
                                    </div>

                                    {{ $this->getSchema('filtersForm') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </x-slot>

            @forelse ($notifications as $notification)
                <div
                    wire:key="database-notification-{{ $notification->getKey() }}"
                    @class([
                        'fi-no-notification-read-ctn' => ! $notification->unread(),
                        'fi-no-notification-unread-ctn' => $notification->unread(),
                    ])
                >
                    {{ $this->getNotification($notification)->inline() }}
                </div>
            @empty
                <x-empty-state-panel
                    class="fi-no-database-empty-filter"
                    heading="No matches found"
                    description="No notifications match your current search or filters. Try adjusting your criteria, or clear them to see everything again."
                    icon="heroicon-o-magnifying-glass"
                    icon-color="primary"
                >
                    <x-slot name="actions">
                        <x-filament::button
                            color="primary"
                            wire:click="clearSearchAndFilters"
                            type="button"
                        >
                            Clear search &amp; filters
                        </x-filament::button>
                    </x-slot>
                </x-empty-state-panel>
            @endforelse

            @if ($broadcastChannel = $this->getBroadcastChannel())
                @script
                    <script>
                        window.addEventListener('EchoLoaded', () => {
                            window.Echo.private(@js($broadcastChannel)).listen(
                                '.database-notifications.sent',
                                () => {
                                    setTimeout(
                                        () => $wire.call('$refresh'),
                                        500,
                                    )
                                },
                            )
                        })

                        if (window.Echo) {
                            window.dispatchEvent(new CustomEvent('EchoLoaded'))
                        }
                    </script>
                @endscript
            @endif

            @if ($isPaginated)
                <x-slot name="footer">
                    <div
                        wire:key="database-notifications-pagination-{{ $notifications->currentPage() }}"
                        class="fi-no-database-pagination flex w-full justify-center"
                    >
                        <x-filament::pagination :paginator="$notifications" />
                    </div>
                </x-slot>
            @endif
        @endif
    </x-filament::modal>

    <x-filament-actions::modals />
</div>
