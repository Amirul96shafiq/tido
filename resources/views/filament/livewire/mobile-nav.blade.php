@php
    use Filament\Support\Icons\Heroicon;
@endphp

<div
    class="tido-mobilenav-root lg:hidden"
    x-data="{
        addOpen: false,
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

                this.closeAdd();

                if (this.$store.sidebar.isOpen) {
                    this.$store.sidebar.close();
                }
            });
        },
        closeUserMenu() {
            const menu = this.$root.querySelector('.fi-user-menu--mobilenav');
            const data = menu ? Alpine.$data(menu) : null;
            data?.close?.();
        },
        openAdd() {
            this.closeUserMenu();
            if (this.$store.sidebar.isOpen) {
                this.$store.sidebar.close();
            }
            this.addOpen = true;
        },
        closeAdd() {
            this.addOpen = false;
        },
        openSearch() {
            this.closeAdd();
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
        },
        toggleSidebar() {
            this.closeAdd();
            this.closeUserMenu();
            if (this.$store.sidebar.isOpen) {
                this.$store.sidebar.close();
            } else {
                this.$store.sidebar.open();
            }
        },
    }"
    x-on:keydown.escape.window="if (addOpen) { closeAdd(); }"
>
    <div
        x-cloak
        x-show="addOpen"
        x-transition.opacity.300ms
        class="tido-chrome-overlay tido-mobilenav-add-backdrop"
        x-on:click="closeAdd()"
        aria-hidden="true"
    ></div>

    <div
        x-cloak
        x-show="$store.tidoNotifications && $store.tidoNotifications.menuOpen"
        x-transition.opacity.300ms
        class="tido-chrome-overlay tido-user-menu-overlay"
        x-on:click="closeUserMenu()"
        aria-hidden="true"
    ></div>

    <div
        x-cloak
        x-show="addOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="tido-mobilenav-add-sheet fixed inset-x-0 z-[35] mx-auto max-w-3xs"
        role="dialog"
        aria-label="Add"
    >
        <div class="tido-mobilenav-add-card overflow-hidden rounded-xl border border-gray-200 bg-white shadow-none dark:border-slate-700 dark:bg-slate-800">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-slate-700">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Add</h2>
            </div>

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
                                        Heroicon::OutlinedArrowPathRoundedSquare,
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
                                        Heroicon::OutlinedArrowPathRoundedSquare,
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
        >
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
        </a>

        <button
            type="button"
            class="tido-mobilenav-item"
            aria-label="Menu"
            x-bind:aria-expanded="$store.sidebar.isOpen"
            x-on:click="toggleSidebar()"
        >
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
                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon']),
                    )
                }}
            </span>
        </button>

        <button
            type="button"
            class="tido-mobilenav-item tido-mobilenav-item--primary"
            aria-label="Add"
            x-bind:aria-expanded="addOpen"
            x-on:click="addOpen ? closeAdd() : openAdd()"
        >
            <span x-cloak x-show="! addOpen">
                {{
                    \Filament\Support\generate_icon_html(
                        Heroicon::OutlinedPlusCircle,
                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon tido-mobilenav-icon--primary']),
                    )
                }}
            </span>
            <span x-cloak x-show="addOpen">
                {{
                    \Filament\Support\generate_icon_html(
                        Heroicon::PlusCircle,
                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon tido-mobilenav-icon--primary']),
                    )
                }}
            </span>
        </button>

        <button
            type="button"
            class="tido-mobilenav-item"
            aria-label="Search"
            x-on:click="openSearch()"
        >
            {{
                \Filament\Support\generate_icon_html(
                    Heroicon::OutlinedMagnifyingGlass,
                    attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon']),
                )
            }}
        </button>

        <div class="tido-mobilenav-item tido-mobilenav-item--avatar">
            <x-filament-panels::user-menu
                instance="mobilenav"
                anchor="mobilenav"
            />
        </div>
    </nav>

    <x-filament-actions::modals />
</div>
