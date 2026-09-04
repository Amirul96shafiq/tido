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
        class="tido-mobilenav-add-sheet fixed inset-x-0 mx-auto min-w-0 max-w-3xs"
        role="dialog"
        aria-label="Add"
    >
        <div class="tido-mobilenav-add-card min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-none dark:border-slate-700 dark:bg-slate-800">
            <div class="min-w-0 px-4 py-3">
                <p class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    Finances
                </p>

                <ul class="flex min-w-0 flex-col gap-1">
                    <li class="min-w-0">
                        <a
                            href="{{ $receiptUrl }}"
                            wire:navigate
                            class="flex min-w-0 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-950 hover:bg-gray-50 dark:text-white dark:hover:bg-white/5"
                            x-on:click="closeAdd()"
                        >
                            {{
                                \Filament\Support\generate_icon_html(
                                    Heroicon::OutlinedDocumentPlus,
                                    attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0 text-primary-600 dark:text-primary-400']),
                                )
                            }}
                            <x-tido.text-marquee
                                class="min-w-0 flex-1"
                                text-class="inline-block whitespace-nowrap"
                            >Add Receipt</x-tido.text-marquee>
                        </a>
                    </li>

                    <li class="min-w-0">
                        @if ($canCreateFinances)
                            <a
                                href="{{ $budgetCreateUrl }}"
                                wire:navigate
                                class="flex min-w-0 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-950 hover:bg-gray-50 dark:text-white dark:hover:bg-white/5"
                                x-on:click="closeAdd()"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedBanknotes,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0 text-primary-600 dark:text-primary-400']),
                                    )
                                }}
                                <x-tido.text-marquee
                                    class="min-w-0 flex-1"
                                    text-class="inline-block whitespace-nowrap"
                                >Add Budget</x-tido.text-marquee>
                            </a>
                        @else
                            <span
                                class="flex w-full min-w-0 cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-400 opacity-60 dark:text-gray-500"
                                aria-disabled="true"
                                title="{{ $createDeniedMessage }}"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedBanknotes,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0']),
                                    )
                                }}
                                <x-tido.text-marquee
                                    class="min-w-0 flex-1"
                                    text-class="inline-block whitespace-nowrap"
                                >Add Budget</x-tido.text-marquee>
                            </span>
                        @endif
                    </li>

                    <li class="min-w-0">
                        @if ($canCreateFinances)
                            <a
                                href="{{ $recurringCreateUrl }}"
                                wire:navigate
                                class="flex min-w-0 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-950 hover:bg-gray-50 dark:text-white dark:hover:bg-white/5"
                                x-on:click="closeAdd()"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedArrowPath,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0 text-primary-600 dark:text-primary-400']),
                                    )
                                }}
                                <x-tido.text-marquee
                                    class="min-w-0 flex-1"
                                    text-class="inline-block whitespace-nowrap"
                                >Add Recurring</x-tido.text-marquee>
                            </a>
                        @else
                            <span
                                class="flex w-full min-w-0 cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-400 opacity-60 dark:text-gray-500"
                                aria-disabled="true"
                                title="{{ $createDeniedMessage }}"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedArrowPath,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0']),
                                    )
                                }}
                                <x-tido.text-marquee
                                    class="min-w-0 flex-1"
                                    text-class="inline-block whitespace-nowrap"
                                >Add Recurring</x-tido.text-marquee>
                            </span>
                        @endif
                    </li>
                </ul>
            </div>

            <div class="min-w-0 border-t border-gray-200 px-4 py-3 dark:border-slate-700">
                <p class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    Settings
                </p>

                <ul class="flex min-w-0 flex-col gap-1">
                    <li class="min-w-0">
                        @if ($canCreateSettings)
                            <a
                                href="{{ $labelCreateUrl }}"
                                wire:navigate
                                class="flex min-w-0 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-950 hover:bg-gray-50 dark:text-white dark:hover:bg-white/5"
                                x-on:click="closeAdd()"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedTag,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0 text-primary-600 dark:text-primary-400']),
                                    )
                                }}
                                <x-tido.text-marquee
                                    class="min-w-0 flex-1"
                                    text-class="inline-block whitespace-nowrap"
                                >Add Labels</x-tido.text-marquee>
                            </a>
                        @else
                            <span
                                class="flex w-full min-w-0 cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-400 opacity-60 dark:text-gray-500"
                                aria-disabled="true"
                                title="{{ $createDeniedMessage }}"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedTag,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0']),
                                    )
                                }}
                                <x-tido.text-marquee
                                    class="min-w-0 flex-1"
                                    text-class="inline-block whitespace-nowrap"
                                >Add Labels</x-tido.text-marquee>
                            </span>
                        @endif
                    </li>

                    <li class="min-w-0">
                        @if ($canCreateSettings)
                            <a
                                href="{{ $paymentMethodCreateUrl }}"
                                wire:navigate
                                class="flex min-w-0 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-950 hover:bg-gray-50 dark:text-white dark:hover:bg-white/5"
                                x-on:click="closeAdd()"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedCreditCard,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0 text-primary-600 dark:text-primary-400']),
                                    )
                                }}
                                <x-tido.text-marquee
                                    class="min-w-0 flex-1"
                                    text-class="inline-block whitespace-nowrap"
                                >Add Payment Methods</x-tido.text-marquee>
                            </a>
                        @else
                            <span
                                class="flex w-full min-w-0 cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-400 opacity-60 dark:text-gray-500"
                                aria-disabled="true"
                                title="{{ $createDeniedMessage }}"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedCreditCard,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0']),
                                    )
                                }}
                                <x-tido.text-marquee
                                    class="min-w-0 flex-1"
                                    text-class="inline-block whitespace-nowrap"
                                >Add Payment Methods</x-tido.text-marquee>
                            </span>
                        @endif
                    </li>

                    <li class="min-w-0">
                        @if ($canCreateSettings)
                            <a
                                href="{{ $familyMemberCreateUrl }}"
                                wire:navigate
                                class="flex min-w-0 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-950 hover:bg-gray-50 dark:text-white dark:hover:bg-white/5"
                                x-on:click="closeAdd()"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedUserGroup,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0 text-primary-600 dark:text-primary-400']),
                                    )
                                }}
                                <x-tido.text-marquee
                                    class="min-w-0 flex-1"
                                    text-class="inline-block whitespace-nowrap"
                                >Add Family Members</x-tido.text-marquee>
                            </a>
                        @else
                            <span
                                class="flex w-full min-w-0 cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-400 opacity-60 dark:text-gray-500"
                                aria-disabled="true"
                                title="{{ $createDeniedMessage }}"
                            >
                                {{
                                    \Filament\Support\generate_icon_html(
                                        Heroicon::OutlinedUserGroup,
                                        attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['size-5 shrink-0']),
                                    )
                                }}
                                <x-tido.text-marquee
                                    class="min-w-0 flex-1"
                                    text-class="inline-block whitespace-nowrap"
                                >Add Family Members</x-tido.text-marquee>
                            </span>
                        @endif
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div
        class="tido-mobilenav-add-border-behind"
        aria-hidden="true"
        x-bind:class="{ 'tido-mobilenav-add-btn--open': $store.tidoMobileChrome.addOpen }"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="-4 -4 111 77"
            class="tido-mobilenav-add-svg tido-mobilenav-add-svg--default"
            x-cloak
            x-show="! $store.tidoMobileChrome.addOpen"
            overflow="visible"
        >
            <path
                class="tido-mobilenav-add-border"
                fill="none"
                d="M96.441,39.687 C96.441,39.687 103.175,32.786 102.976,28.795 C102.777,24.804 97.952,23.475 89.906,23.349 C85.873,22.129 83.371,20.082 83.371,20.082 L84.460,17.903 C84.460,17.903 91.374,9.890 90.995,7.012 C90.616,4.134 84.798,0.649 67.033,7.012 C64.105,9.119 61.999,8.430 60.498,7.012 C58.997,5.593 30.029,-9.342 9.307,9.190 C-6.185,22.980 6.040,39.687 6.040,39.687 C6.040,39.687 -8.764,56.532 9.307,63.648 C27.379,70.765 53.132,67.687 59.409,66.916 C62.003,66.269 62.879,63.648 62.677,62.559 C62.474,61.470 61.618,58.077 53.963,58.202 C46.308,58.328 24.612,60.131 20.199,59.292 C15.786,58.453 8.218,57.103 7.129,53.846 C6.040,50.588 8.877,47.778 10.397,47.311 C11.542,47.655 13.096,48.945 14.753,50.578 C16.410,52.211 37.650,56.729 53.963,53.846 C54.984,53.771 55.614,54.039 56.142,54.935 C56.669,55.831 65.519,58.202 70.301,58.202 C75.082,58.202 84.460,58.202 84.460,58.202 C84.460,58.202 97.126,57.546 98.619,52.757 C100.112,47.967 98.450,45.556 96.441,42.954 C95.118,40.914 96.441,39.687 96.441,39.687 Z"
            />
        </svg>

        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="-4 -4 111 77"
            class="tido-mobilenav-add-svg tido-mobilenav-add-svg--active"
            x-cloak
            x-show="$store.tidoMobileChrome.addOpen"
            overflow="visible"
        >
            <path
                class="tido-mobilenav-add-border"
                fill="none"
                d="M96.441,39.687 C96.441,39.687 103.175,32.786 102.976,28.795 C102.777,24.804 97.952,23.475 89.906,23.349 C85.873,22.129 83.371,20.082 83.371,20.082 L84.460,17.903 C84.460,17.903 91.374,9.890 90.995,7.012 C90.616,4.134 84.798,0.649 67.033,7.012 C64.105,9.119 61.999,8.430 60.498,7.012 C58.997,5.593 30.029,-9.342 9.307,9.190 C-6.185,22.980 6.040,39.687 6.040,39.687 C6.040,39.687 -8.764,56.532 9.307,63.648 C27.379,70.765 53.132,67.687 59.409,66.916 C62.003,66.269 62.879,63.648 62.677,62.559 C62.474,61.470 61.618,58.077 53.963,58.202 C46.308,58.328 24.612,60.131 20.199,59.292 C15.786,58.453 8.218,57.103 7.129,53.846 C6.040,50.588 8.877,47.778 10.397,47.311 C11.542,47.655 13.096,48.945 14.753,50.578 C16.410,52.211 37.650,56.729 53.963,53.846 C54.984,53.771 55.614,54.039 56.142,54.935 C56.669,55.831 65.519,58.202 70.301,58.202 C75.082,58.202 84.460,58.202 84.460,58.202 C84.460,58.202 97.126,57.546 98.619,52.757 C100.112,47.967 98.450,45.556 96.441,42.954 C95.118,40.914 96.441,39.687 96.441,39.687 Z"
            />
        </svg>
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
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="-4 -4 111 77"
                    class="tido-mobilenav-add-svg tido-mobilenav-add-svg--default"
                    x-cloak
                    x-show="! $store.tidoMobileChrome.addOpen"
                    overflow="visible"
                    aria-hidden="true"
                >
                    <path
                        class="tido-mobilenav-add-bg"
                        fill-rule="evenodd"
                        fill="rgb(206, 138, 0)"
                        d="M96.441,39.687 C96.441,39.687 103.175,32.786 102.976,28.795 C102.777,24.804 97.952,23.475 89.906,23.349 C85.873,22.129 83.371,20.082 83.371,20.082 L84.460,17.903 C84.460,17.903 91.374,9.890 90.995,7.012 C90.616,4.134 84.798,0.649 67.033,7.012 C64.105,9.119 61.999,8.430 60.498,7.012 C58.997,5.593 30.029,-9.342 9.307,9.190 C-6.185,22.980 6.040,39.687 6.040,39.687 C6.040,39.687 -8.764,56.532 9.307,63.648 C27.379,70.765 53.132,67.687 59.409,66.916 C62.003,66.269 62.879,63.648 62.677,62.559 C62.474,61.470 61.618,58.077 53.963,58.202 C46.308,58.328 24.612,60.131 20.199,59.292 C15.786,58.453 8.218,57.103 7.129,53.846 C6.040,50.588 8.877,47.778 10.397,47.311 C11.542,47.655 13.096,48.945 14.753,50.578 C16.410,52.211 37.650,56.729 53.963,53.846 C54.984,53.771 55.614,54.039 56.142,54.935 C56.669,55.831 65.519,58.202 70.301,58.202 C75.082,58.202 84.460,58.202 84.460,58.202 C84.460,58.202 97.126,57.546 98.619,52.757 C100.112,47.967 98.450,45.556 96.441,42.954 C95.118,40.914 96.441,39.687 96.441,39.687 Z"
                    />
                    <path fill-rule="evenodd" fill="rgb(255, 255, 255)" d="M76.622,37.102 L88.693,48.531 C89.259,49.066 89.283,49.958 88.748,50.523 L88.575,50.706 C88.040,51.271 87.148,51.295 86.583,50.760 L74.512,39.331 C73.946,38.796 73.922,37.904 74.457,37.339 L74.630,37.156 C75.165,36.591 76.057,36.567 76.622,37.102 Z" />
                    <path fill-rule="evenodd" fill="rgb(255, 255, 255)" d="M59.196,20.764 L71.267,32.194 C71.832,32.729 71.856,33.621 71.321,34.186 L71.148,34.368 C70.613,34.933 69.721,34.958 69.156,34.423 L57.085,22.993 C56.520,22.458 56.495,21.566 57.031,21.001 L57.203,20.819 C57.738,20.253 58.630,20.229 59.196,20.764 Z" />
                    <g class="tido-mobilenav-add-zzz" aria-hidden="true">
                        <text x="93" y="16" class="tido-mobilenav-add-z tido-mobilenav-add-z--1">z</text>
                        <text x="104" y="6" class="tido-mobilenav-add-z tido-mobilenav-add-z--2">z</text>
                    </g>
                </svg>

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="-4 -4 111 77"
                    class="tido-mobilenav-add-svg tido-mobilenav-add-svg--active"
                    x-cloak
                    x-show="$store.tidoMobileChrome.addOpen"
                    x-data="{
                        blinking: false,
                        blinkCount: 1,
                        timer: null,
                        init() {
                            this.$watch('$store.tidoMobileChrome.addOpen', (open) => {
                                if (open) {
                                    this.scheduleBlink(1000 + Math.random() * 1200);
                                } else {
                                    this.stopBlinking();
                                }
                            });
                            if (this.$store.tidoMobileChrome.addOpen) {
                                this.scheduleBlink(1000 + Math.random() * 1200);
                            }
                        },
                        scheduleBlink(delay) {
                            this.stopBlinking();
                            this.timer = setTimeout(() => {
                                this.triggerBlink();
                            }, delay);
                        },
                        triggerBlink() {
                            if (! this.$store.tidoMobileChrome.addOpen) {
                                return;
                            }
                            this.blinkCount = Math.floor(Math.random() * 3) + 1;
                            this.blinking = true;
                            const duration = this.blinkCount * 220;
                            setTimeout(() => {
                                this.blinking = false;
                                if (this.$store.tidoMobileChrome.addOpen) {
                                    const nextDelay = 1800 + Math.random() * 3200;
                                    this.scheduleBlink(nextDelay);
                                }
                            }, duration);
                        },
                        stopBlinking() {
                            if (this.timer) {
                                clearTimeout(this.timer);
                                this.timer = null;
                            }
                            this.blinking = false;
                        },
                    }"
                    x-bind:class="{ ['is-blinking-' + blinkCount]: blinking }"
                    overflow="visible"
                    aria-hidden="true"
                >
                    <path
                        class="tido-mobilenav-add-bg"
                        fill-rule="evenodd"
                        fill="rgb(206, 138, 0)"
                        d="M96.441,39.687 C96.441,39.687 103.175,32.786 102.976,28.795 C102.777,24.804 97.952,23.475 89.906,23.349 C85.873,22.129 83.371,20.082 83.371,20.082 L84.460,17.903 C84.460,17.903 91.374,9.890 90.995,7.012 C90.616,4.134 84.798,0.649 67.033,7.012 C64.105,9.119 61.999,8.430 60.498,7.012 C58.997,5.593 30.029,-9.342 9.307,9.190 C-6.185,22.980 6.040,39.687 6.040,39.687 C6.040,39.687 -8.764,56.532 9.307,63.648 C27.379,70.765 53.132,67.687 59.409,66.916 C62.003,66.269 62.879,63.648 62.677,62.559 C62.474,61.470 61.618,58.077 53.963,58.202 C46.308,58.328 24.612,60.131 20.199,59.292 C15.786,58.453 8.218,57.103 7.129,53.846 C6.040,50.588 8.877,47.778 10.397,47.311 C11.542,47.655 13.096,48.945 14.753,50.578 C16.410,52.211 37.650,56.729 53.963,53.846 C54.984,53.771 55.614,54.039 56.142,54.935 C56.669,55.831 65.519,58.202 70.301,58.202 C75.082,58.202 84.460,58.202 84.460,58.202 C84.460,58.202 97.126,57.546 98.619,52.757 C100.112,47.967 98.450,45.556 96.441,42.954 C95.118,40.914 96.441,39.687 96.441,39.687 Z"
                    />
                    <g class="tido-mobilenav-add-eye tido-mobilenav-add-eye--right">
                        <path class="tido-mobilenav-add-eye-sclera" fill-rule="evenodd" fill="rgb(255, 255, 255)" d="M80.656,35.165 L81.304,35.165 C85.722,35.165 89.304,38.746 89.304,43.165 L89.304,43.800 C88.800,46.500 85.500,47.800 81.304,47.800 L77.500,47.800 C74.200,47.800 72.656,45.800 72.656,43.165 L72.656,42.500 C72.656,38.746 76.238,35.165 80.656,35.165 Z" />
                        <path class="tido-mobilenav-add-eye-pupil" fill-rule="evenodd" fill="rgb(206, 138, 0)" d="M79.983,38.585 C81.336,38.585 82.433,39.682 82.433,41.035 C82.433,42.388 81.336,43.484 79.983,43.484 C78.630,43.484 77.534,42.388 77.534,41.035 C77.534,39.682 78.630,38.585 79.983,38.585 Z" />
                    </g>
                    <g class="tido-mobilenav-add-eye tido-mobilenav-add-eye--left">
                        <path class="tido-mobilenav-add-eye-sclera" fill-rule="evenodd" fill="rgb(255, 255, 255)" d="M64.656,18.165 L65.304,18.165 C69.722,18.165 73.304,21.746 73.304,26.165 L73.304,26.812 C73.304,31.231 69.722,34.812 65.304,34.812 L64.656,34.812 C60.238,34.812 56.656,31.231 56.656,26.812 L56.656,26.165 C56.656,21.746 60.238,18.165 64.656,18.165 Z" />
                        <path class="tido-mobilenav-add-eye-pupil" fill-rule="evenodd" fill="rgb(206, 138, 0)" d="M63.983,21.585 C65.336,21.585 66.433,22.682 66.433,24.035 C66.433,25.388 65.336,26.484 63.983,26.484 C62.630,26.484 61.534,25.388 61.534,24.035 C61.534,22.682 62.630,21.585 63.983,21.585 Z" />
                    </g>
                </svg>
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
