@php
    use Filament\Support\Icons\Heroicon;
@endphp

<div
    class="tido-mobilenav-root lg:hidden"
    x-data="{
        addOpen: false,
        openAdd() {
            this.addOpen = true;
        },
        closeAdd() {
            this.addOpen = false;
        },
        openSearch() {
            window.dispatchEvent(
                new CustomEvent('open-global-search-modal', {
                    detail: { id: 'global-search-modal::plugin' },
                    bubbles: true,
                }),
            );
        },
        toggleSidebar() {
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
        x-transition.opacity
        class="tido-mobilenav-add-backdrop fixed inset-0 z-[34] bg-gray-950/50 backdrop-blur-md dark:bg-gray-950/75"
        x-on:click="closeAdd()"
        aria-hidden="true"
    ></div>

    <div
        x-cloak
        x-show="addOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-full opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-full opacity-0"
        class="tido-mobilenav-add-sheet fixed inset-x-0 bottom-[var(--tido-mobilenav-height,4rem)] z-[35] mx-auto max-w-lg px-3"
        role="dialog"
        aria-label="Add"
    >
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-none dark:border-slate-700 dark:bg-slate-800">
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
            class="tido-mobilenav-item"
            aria-label="Home"
        >
            {{
                \Filament\Support\generate_icon_html(
                    Heroicon::OutlinedHome,
                    attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon']),
                )
            }}
        </a>

        <button
            type="button"
            class="tido-mobilenav-item"
            aria-label="Menu"
            x-on:click="toggleSidebar()"
        >
            {{
                \Filament\Support\generate_icon_html(
                    Heroicon::OutlinedBars3,
                    attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon']),
                )
            }}
        </button>

        <button
            type="button"
            class="tido-mobilenav-item tido-mobilenav-item--primary"
            aria-label="Add"
            x-on:click="openAdd()"
        >
            {{
                \Filament\Support\generate_icon_html(
                    Heroicon::OutlinedPlus,
                    attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['tido-mobilenav-icon tido-mobilenav-icon--primary']),
                )
            }}
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
