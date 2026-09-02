@php
    use Filament\Support\Icons\Heroicon;
@endphp

<div
    class="tido-mobilenav-root lg:hidden"
    x-data="{
        init() {
            if (! this.$store.tidoNotifications) {
                Alpine.store('tidoNotifications', { unread: 0, menuOpen: false });
            } else if (this.$store.tidoNotifications.menuOpen === undefined) {
                this.$store.tidoNotifications.menuOpen = false;
            }

            this.$watch('$store.tidoNotifications.menuOpen', (menuOpen) => {
                if (! menuOpen) {
                    return;
                }

                this.$store.tidoMobileChrome.addOpen = false;
                this.$store.tidoMobileChrome.closeSearchModal();

                if (this.$store.sidebar.isOpen) {
                    this.$store.sidebar.close();
                }

                this.$store.tidoMobileChrome.syncOverlay();
            });

            this.$store.tidoMobileChrome.syncOverlay();
        },
        closeUserMenu() {
            this.$store.tidoMobileChrome.closeUserMenu();
        },
        searchModal() {
            return document.getElementById('global-search-modal::plugin');
        },
        searchModalData() {
            const modal = this.searchModal();

            return modal ? Alpine.$data(modal) : null;
        },
        isSearchOpen() {
            return this.searchModalData()?.isOpen === true
                || this.$store.tidoMobileChrome.searchOpen === true;
        },
        closeSearch() {
            this.$store.tidoMobileChrome.closeSearchModal();
        },
        openAdd() {
            this.$store.tidoMobileChrome.primeOverlay();
            this.$store.tidoMobileChrome.addOpen = true;
            this.$store.tidoMobileChrome.syncOverlay();
            this.$store.tidoMobileChrome.closeSearchModal();
            this.closeUserMenu();
            if (this.$store.sidebar.isOpen) {
                this.$store.sidebar.close();
            }
            this.$store.tidoMobileChrome.syncOverlay();
        },
        closeAdd() {
            this.$store.tidoMobileChrome.addOpen = false;
            this.$store.tidoMobileChrome.dismissOverlay();
        },
        toggleSearch() {
            if (this.isSearchOpen()) {
                this.closeSearch();

                return;
            }

            this.$store.tidoMobileChrome.primeOverlay();
            this.$store.tidoMobileChrome.searchOpen = true;
            this.$store.tidoMobileChrome.syncOverlay();
            this.$store.tidoMobileChrome.addOpen = false;
            this.closeUserMenu();
            if (this.$store.sidebar.isOpen) {
                this.$store.sidebar.close();
            }

            window.dispatchEvent(
                new CustomEvent('open-global-search-modal', {
                    detail: { id: 'global-search-modal::plugin' },
                    bubbles: true,
                }),
            );

            this.$store.tidoMobileChrome.syncOverlay();
        },
        expandAllSidebarGroups() {
            this.$store.sidebar.collapsedGroups = [];
        },
        toggleSidebar() {
            if (this.$store.sidebar.isOpen) {
                this.$store.sidebar.close();
                this.$store.tidoMobileChrome.dismissOverlay();

                return;
            }

            this.expandAllSidebarGroups();
            this.$store.sidebar.open();
            this.$store.tidoMobileChrome.primeOverlay();
            this.$store.tidoMobileChrome.syncOverlay();
            this.$store.tidoMobileChrome.addOpen = false;
            this.closeUserMenu();

            if (this.isSearchOpen()) {
                this.closeSearch();
            } else {
                this.$store.tidoMobileChrome.searchOpen = false;
            }

            this.$store.tidoMobileChrome.syncOverlay();
        },
    }"
    x-on:keydown.escape.window="if ($store.tidoMobileChrome.addOpen) { closeAdd(); }"
>
    <div
        x-cloak
        x-show="$store.tidoMobileChrome.addOpen"
        x-transition:enter="fi-transition-enter"
        x-transition:leave="fi-transition-leave"
        x-transition:enter-start="fi-transition-enter-start"
        x-transition:enter-end="fi-transition-enter-end"
        x-transition:leave-start="fi-transition-leave-start"
        x-transition:leave-end="fi-transition-leave-end"
        class="tido-mobilenav-add-sheet fixed inset-x-0 mx-auto max-w-3xs"
        role="dialog"
        aria-label="Add"
    >
        <div class="tido-mobilenav-add-card overflow-hidden rounded-xl border border-gray-200 bg-white shadow-none dark:border-slate-700 dark:bg-slate-800">
            <div class="px-4 py-3">
                <p class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    Finances
                </p>

                <ul class="flex flex-col gap-1">
                    <li>
                        <a
                            href="{{ $receiptUrl }}"
                            wire:navigate
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-950 hover:bg-gray-50 dark:text-white dark:hover:bg-white/5"
                            x-on:click="closeAdd()"
                        >
                            {{
                                \Filament\Support\generate_icon_html(
                                    Heroicon::OutlinedDocumentPlus,
                                    attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0 text-primary-600 dark:text-primary-400']),
                                )
                            }}
                            <span>Add Receipt</span>
                        </a>
                    </li>

                    <li>
                        @if ($canCreateFinances)
                            <a
                                href="{{ $budgetCreateUrl }}"
                                wire:navigate
                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-950 hover:bg-gray-50 dark:text-white dark:hover:bg-white/5"
                                x-on:click="closeAdd()"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedBanknotes,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0 text-primary-600 dark:text-primary-400']),
                                    )
                                }}
                                <span>Add Budget</span>
                            </a>
                        @else
                            <span
                                class="flex w-full cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-400 opacity-60 dark:text-gray-500"
                                aria-disabled="true"
                                title="{{ $createDeniedMessage }}"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedBanknotes,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0']),
                                    )
                                }}
                                <span>Add Budget</span>
                            </span>
                        @endif
                    </li>

                    <li>
                        @if ($canCreateFinances)
                            <a
                                href="{{ $recurringCreateUrl }}"
                                wire:navigate
                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-950 hover:bg-gray-50 dark:text-white dark:hover:bg-white/5"
                                x-on:click="closeAdd()"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedArrowPath,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0 text-primary-600 dark:text-primary-400']),
                                    )
                                }}
                                <span>Add Recurring</span>
                            </a>
                        @else
                            <span
                                class="flex w-full cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-400 opacity-60 dark:text-gray-500"
                                aria-disabled="true"
                                title="{{ $createDeniedMessage }}"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedArrowPath,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0']),
                                    )
                                }}
                                <span>Add Recurring</span>
                            </span>
                        @endif
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <nav class="tido-mobilenav-bar" aria-label="Mobile navigation">
        <a
            href="{{ $homeUrl }}"
            wire:navigate
            wire:current.exact="tido-mobilenav-item--current"
            class="tido-mobilenav-item"
            aria-label="Home"
            x-on:click="closeSearch()"
        >
            <span class="tido-mobilenav-icon-wrap">
                {{
                    \Filament\Support\generate_icon_html(
                        Heroicon::OutlinedHome,
                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon tido-mobilenav-icon--outline']),
                    )
                }}
                {{
                    \Filament\Support\generate_icon_html(
                        Heroicon::Home,
                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon tido-mobilenav-icon--solid']),
                    )
                }}
            </span>
            <span class="tido-mobilenav-label">Home</span>
        </a>

        <button
            type="button"
            class="tido-mobilenav-item"
            aria-label="Menu"
            x-bind:class="{ 'tido-mobilenav-item--active text-primary-600 dark:text-primary-400': $store.sidebar.isOpen }"
            x-bind:aria-expanded="$store.sidebar.isOpen"
            x-on:click="toggleSidebar()"
        >
            <span class="tido-mobilenav-icon-wrap">
                <span x-cloak x-show="! $store.sidebar.isOpen">
                    {{
                        \Filament\Support\generate_icon_html(
                            Heroicon::OutlinedBars3,
                            attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon']),
                        )
                    }}
                </span>
                <span x-cloak x-show="$store.sidebar.isOpen">
                    {{
                        \Filament\Support\generate_icon_html(
                            Heroicon::OutlinedBars3BottomLeft,
                            attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon text-primary-600 dark:text-primary-400']),
                        )
                    }}
                </span>
            </span>
            <span
                class="tido-mobilenav-label"
                x-bind:class="{ 'text-primary-600 dark:text-primary-400': $store.sidebar.isOpen }"
            >Menu</span>
        </button>

        <button
            type="button"
            class="tido-mobilenav-item tido-mobilenav-item--add"
            aria-label="Add"
            x-bind:class="{ 'tido-mobilenav-item--active text-primary-600 dark:text-primary-400': $store.tidoMobileChrome.addOpen }"
            x-bind:aria-expanded="$store.tidoMobileChrome.addOpen"
            x-on:click="$store.tidoMobileChrome.addOpen ? closeAdd() : openAdd()"
        >
            <span
                class="tido-mobilenav-add-btn"
                x-bind:class="{ 'tido-mobilenav-add-btn--open': $store.tidoMobileChrome.addOpen }"
            >
                {{
                    \Filament\Support\generate_icon_html(
                        Heroicon::Plus,
                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-add-icon']),
                    )
                }}
            </span>
            <span
                class="tido-mobilenav-label"
                x-bind:class="{ 'text-primary-600 dark:text-primary-400': $store.tidoMobileChrome.addOpen }"
            >Add</span>
        </button>

        <button
            type="button"
            class="tido-mobilenav-item"
            aria-label="Search"
            x-bind:class="{ 'tido-mobilenav-item--active text-primary-600 dark:text-primary-400': isSearchOpen() }"
            x-bind:aria-expanded="isSearchOpen()"
            x-on:click="toggleSearch()"
        >
            <span class="tido-mobilenav-icon-wrap">
                {{
                    \Filament\Support\generate_icon_html(
                        Heroicon::OutlinedMagnifyingGlass,
                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon']),
                    )
                }}
            </span>
            <span
                class="tido-mobilenav-label"
                x-bind:class="{ 'text-primary-600 dark:text-primary-400': isSearchOpen() }"
            >Search</span>
        </button>

        <div
            class="tido-mobilenav-item tido-mobilenav-item--avatar"
        >
            <x-filament-panels::user-menu
                instance="mobilenav"
                anchor="mobilenav"
            />
        </div>
    </nav>

    <x-filament-actions::modals />
</div>
