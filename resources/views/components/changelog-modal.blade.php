<div
    class="fi-changelog"
    x-data="{
        loading: true,
        commits: [],
        totalCommits: 0,
        pagination: null,
        currentPage: 1,
        expandedCommit: null,

        async loadCommits(page = 1) {
            this.loading = true;
            this.currentPage = page;

            const startTime = Date.now();
            const minLoadingTime = 1000;

            try {
                const response = await fetch(`/changelog?page=${page}`);
                const data = await response.json();

                if (data.success) {
                    this.commits = data.commits;
                    this.totalCommits = data.total;
                    this.pagination = data.pagination;
                } else {
                    console.error('Failed to load commits:', data.error);
                    this.commits = [];
                    this.totalCommits = 0;
                }
            } catch (error) {
                console.error('Error loading commits:', error);
                this.commits = [];
                this.totalCommits = 0;
            } finally {
                const elapsedTime = Date.now() - startTime;
                const remainingTime = Math.max(0, minLoadingTime - elapsedTime);

                if (remainingTime > 0) {
                    setTimeout(() => {
                        this.loading = false;
                    }, remainingTime);
                } else {
                    this.loading = false;
                }
            }
        },

        loadPage(page) {
            this.loadCommits(page);
        },

        showCommitDetail(hash) {
            const githubUrl = `https://github.com/Amirul96shafiq/tido/commit/${hash}`;
            window.open(githubUrl, '_blank');
        },

        toggleCommitDescription(hash) {
            this.expandedCommit = this.expandedCommit === hash ? null : hash;
        },
    }"
    x-on:open-modal.window="if ($event.detail.id === 'changelog' && commits.length === 0) loadCommits()"
>
    <x-filament::modal
        id="changelog"
        slide-over
        sticky-header
        sticky-footer
        teleport="body"
        width="md"
        close-button
        class="fi-changelog"
    >
        <x-slot name="header">
            <div class="flex w-full items-center gap-3">
                <div class="rounded-full bg-primary-100 p-2 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400">
                    <x-heroicon-o-code-bracket class="h-5 w-5" />
                </div>
                <div>
                    <h2 id="changelog-heading" class="fi-modal-heading">
                        Changelogs
                    </h2>
                    <p
                        class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                        x-text="totalCommits ? totalCommits + ' commits' : 'Loading...'"
                    ></p>
                </div>
            </div>
        </x-slot>

        {{-- Loading State --}}
        <div x-show="loading" class="flex min-h-[50vh] flex-col items-center justify-center space-y-6 px-6 py-4">
            <div class="relative animate-spin duration-1000">
                <svg class="h-12 w-12 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
            </div>

            <div class="space-y-2 text-center">
                <p class="text-base font-medium text-gray-700 dark:text-gray-300">
                    Loading commits...
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Please wait
                </p>
            </div>
        </div>

        {{-- Commits List --}}
        <div x-show="!loading && commits.length > 0" class="space-y-0 px-6 py-4 custom-scrollbar">
            <template x-for="commit in commits" :key="commit.short_hash">
                <div class="group border-b border-gray-200 py-4 transition-colors last:border-b-0 dark:border-gray-700">
                    <div class="mb-2 flex items-start justify-between gap-3">
                        <img
                            :src="commit.author_avatar"
                            :alt="commit.author_name"
                            class="h-6 w-6 flex-shrink-0 rounded-full"
                            draggable="false"
                        >

                        <div class="flex flex-shrink-0 items-center gap-2">
                            <button
                                type="button"
                                x-show="commit.description && commit.description.length > 0"
                                x-on:click.stop="toggleCommitDescription(commit.short_hash)"
                                aria-label="View Commit Description"
                                x-tooltip="{
                                    content: @js('View Commit Description'),
                                    theme: $store.theme,
                                }"
                                class="flex-shrink-0 p-1 text-gray-400 transition-all duration-200 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                x-bind:class="expandedCommit === commit.short_hash ? 'rotate-180' : 'rotate-0'"
                            >
                                <x-heroicon-o-chevron-down class="h-4 w-4" />
                            </button>

                            <button
                                type="button"
                                x-on:click.stop="showCommitDetail(commit.short_hash)"
                                aria-label="View Commit Details"
                                x-tooltip="{
                                    content: @js('View Commit Details'),
                                    theme: $store.theme,
                                }"
                                class="p-1 text-primary-400 transition-colors hover:text-primary-600 dark:text-primary-500 dark:hover:text-primary-300"
                            >
                                <x-heroicon-o-code-bracket class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div
                        x-on:click="commit.description && commit.description.length > 0 ? toggleCommitDescription(commit.short_hash) : null"
                        x-bind:class="commit.description && commit.description.length > 0 ? 'cursor-pointer' : ''"
                        class="w-full"
                    >
                        <div class="mb-2 flex flex-wrap gap-1.5">
                            <template x-for="tag in commit.tags" :key="tag">
                                <span
                                    class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-normal text-primary-600 dark:bg-primary-500/20 dark:text-primary-400"
                                    x-text="tag"
                                ></span>
                            </template>

                            <span
                                class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 font-mono text-xs font-normal text-primary-600 dark:bg-primary-500/20 dark:text-primary-400"
                                x-text="commit.short_hash"
                            ></span>
                        </div>

                        <div class="mb-2 w-full">
                            <p class="text-sm font-medium leading-relaxed text-gray-900 dark:text-gray-100" x-text="commit.message"></p>
                        </div>

                        <div class="flex w-full items-center gap-2 text-[9px] text-gray-500 md:gap-1 md:text-xs dark:text-gray-400">
                            <div class="w-1/2 text-start md:w-auto md:text-inherit">
                                <span class="font-medium text-gray-700 dark:text-gray-300" x-text="commit.author_name"></span>
                            </div>

                            <span class="hidden md:inline">Committed</span>

                            <div class="w-1/2 text-end md:w-auto md:text-inherit">
                                <time :datetime="commit.date" :title="commit.date_formatted">
                                    <span x-text="commit.date_relative"></span>
                                    <span> • </span>
                                    <span x-text="new Date(commit.date).toLocaleString('en-GB', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit', hour12: true }).replace(',', '').toUpperCase()"></span>
                                </time>
                            </div>
                        </div>
                    </div>

                    <div
                        x-show="expandedCommit === commit.short_hash && commit.description && commit.description.length > 0"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 -translate-y-2"
                        class="mt-3 w-full"
                    >
                        <div class="py-2">
                            <p class="whitespace-pre-wrap font-mono text-xs leading-relaxed text-gray-500 dark:text-gray-400" x-text="commit.description"></p>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Empty State --}}
        <div x-show="!loading && commits.length === 0" class="px-6 py-12 text-center">
            <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                <x-heroicon-o-code-bracket class="h-8 w-8 text-gray-400 dark:text-gray-500" />
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">No commits found</p>
        </div>

        <x-slot name="footer">
            <div
                x-show="!loading && pagination && pagination.last_page > 1"
                class="flex w-full items-center justify-between px-6 py-4"
            >
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <span x-text="pagination ? 'Page ' + pagination.current_page + ' of ' + pagination.last_page + ' (' + pagination.total + ' commits)' : ''"></span>
                </div>

                <div class="flex items-center space-x-3">
                    <button
                        x-show="pagination && pagination.current_page > 1"
                        x-on:click="loadPage(pagination.current_page - 1)"
                        aria-label="Previous"
                        x-tooltip="{
                            content: @js('Previous'),
                            theme: $store.theme,
                        }"
                        class="group flex h-10 w-10 items-center justify-center rounded-lg bg-primary-500/80 transition-all duration-300 hover:bg-primary-400 dark:bg-primary-500/80 dark:hover:bg-primary-400"
                    >
                        <x-heroicon-o-arrow-left class="h-5 w-5 text-primary-900 transition-colors dark:text-primary-900" />
                    </button>
                    <button
                        x-show="pagination && pagination.current_page === 1"
                        disabled
                        class="flex h-10 w-10 cursor-not-allowed items-center justify-center rounded-lg bg-gray-200 opacity-50 dark:bg-gray-700"
                    >
                        <x-heroicon-o-arrow-left class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                    </button>

                    <button
                        x-show="pagination && pagination.current_page < pagination.last_page"
                        x-on:click="loadPage(pagination.current_page + 1)"
                        aria-label="Next"
                        x-tooltip="{
                            content: @js('Next'),
                            theme: $store.theme,
                        }"
                        class="group flex h-10 w-10 items-center justify-center rounded-lg bg-primary-500/80 transition-all duration-300 hover:bg-primary-400 dark:bg-primary-500/80 dark:hover:bg-primary-400"
                    >
                        <x-heroicon-o-arrow-right class="h-5 w-5 text-primary-900 transition-colors dark:text-primary-900" />
                    </button>
                    <button
                        x-show="pagination && pagination.current_page === pagination.last_page"
                        disabled
                        class="flex h-10 w-10 cursor-not-allowed items-center justify-center rounded-lg bg-gray-200 opacity-50 dark:bg-gray-700"
                    >
                        <x-heroicon-o-arrow-right class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                    </button>
                </div>
            </div>
        </x-slot>
    </x-filament::modal>
</div>

<script>
    window.showChangelogModal = function () {
        window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'changelog' } }));
    };

    document.addEventListener('click', function (event) {
        let element = event.target;

        while (element) {
            if (element.textContent && (element.textContent.includes('Changelogs') || element.textContent.includes("What's New") || element.textContent.includes('Apa Yang Baru'))) {
                if (element.closest('[role="menuitem"], .fi-menu-item, [data-filament-menu-item], a[href*="javascript"]')) {
                    event.preventDefault();
                    event.stopPropagation();
                    window.showChangelogModal();

                    return;
                }
            }

            element = element.parentElement;
        }
    });
</script>
