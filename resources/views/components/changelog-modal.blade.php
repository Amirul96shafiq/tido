<style>
[x-cloak] { display: none !important; }
</style>

<div x-data="{
        show: false,
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
        }
     }"
     @open-changelog-modal.window="show = true; if (commits.length === 0) { loadCommits() }"
     @close-changelog-modal.window="show = false"
     @keydown.escape.window="show = false"
     x-show="show"
     x-cloak
     class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
     style="z-index: 99999 !important;">
    
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-gray-950/50 dark:bg-gray-950/75 backdrop-blur-md transition-opacity" 
         @click="show = false" 
         aria-hidden="true"></div>
    
    {{-- Modal Card --}}
    <div role="dialog" 
         :aria-modal="show ? 'true' : 'false'"
         aria-labelledby="changelog-heading" 
         class="relative w-full max-w-4xl mx-auto cursor-default flex flex-col rounded-xl bg-white dark:bg-gray-900 shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10 pointer-events-auto min-h-[90vh] max-h-[90vh] overflow-hidden">
        
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 pt-6 pb-4 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-full bg-primary-100 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400">
                    <x-heroicon-o-code-bracket class="h-5 w-5" />
                </div>
                <div>
                    <h2 id="changelog-heading" class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Changelogs
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="totalCommits ? totalCommits + ' commits' : 'Loading...'">
                    </p>
                </div>
            </div>
            
            {{-- Close Button --}}
            <button type="button" 
                    @click="show = false" 
                    aria-label="Close"
                    x-tooltip="{
                        content: @js('Close'),
                        theme: $store.theme,
                        zIndex: 100000,
                    }"
                    class="inline-flex items-center justify-center p-2 rounded-lg text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-400 dark:hover:bg-gray-800 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/30">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        {{-- Content - Scrollable --}}
        <div class="overflow-y-auto px-6 py-4 flex-1 bg-white dark:bg-gray-900 custom-scrollbar">

            {{-- Loading State --}}
            <div x-show="loading" class="flex flex-col items-center justify-center h-full min-h-[50vh] space-y-6">
                {{-- Spinner --}}
                <div class="relative animate-spin duration-1000">
                    <svg class="w-12 h-12 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </div>
                
                {{-- Loading Text --}}
                <div class="text-center space-y-2">
                    <p class="text-base font-medium text-gray-700 dark:text-gray-300">
                        Loading commits...
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Please wait
                    </p>
                </div>
            </div>
            
            {{-- Commits List --}}
            <div x-show="!loading && commits.length > 0" class="space-y-0">
                <template x-for="commit in commits" :key="commit.short_hash">
                    <div class="group border-b border-gray-200 dark:border-gray-700 last:border-b-0 py-4 transition-colors">
                        
                        {{-- Avatar and Actions Row --}}
                        <div class="flex items-start justify-between gap-3 mb-2">
                            {{-- Author Avatar --}}
                            <img :src="commit.author_avatar" 
                                 :alt="commit.author_name"
                                 class="w-6 h-6 rounded-full flex-shrink-0"
                                 draggable="false">
                            
                            {{-- Actions --}}
                            <div class="flex items-center gap-2 flex-shrink-0">
                                
                                {{-- Chevron Icon (only show if description exists) --}}
                                <button type="button"
                                        x-show="commit.description && commit.description.length > 0"
                                        @click.stop="toggleCommitDescription(commit.short_hash)"
                                        aria-label="View Commit Description"
                                        x-tooltip="{
                                            content: @js('View Commit Description'),
                                            theme: $store.theme,
                                            zIndex: 100000,
                                        }"
                                        class="flex-shrink-0 p-1 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition-all duration-200"
                                        :class="expandedCommit === commit.short_hash ? 'rotate-180' : 'rotate-0'">
                                    <x-heroicon-o-chevron-down class="w-4 h-4" />
                                </button>

                                {{-- View Details Button --}}
                                <button type="button"
                                        @click.stop="showCommitDetail(commit.short_hash)"
                                        aria-label="View Commit Details"
                                        x-tooltip="{
                                            content: @js('View Commit Details'),
                                            theme: $store.theme,
                                            zIndex: 100000,
                                        }"
                                        class="p-1 text-primary-400 hover:text-primary-600 dark:text-primary-500 dark:hover:text-primary-300 transition-colors">
                                    <x-heroicon-o-code-bracket class="w-4 h-4" />
                                </button>

                            </div>
                        </div>

                        {{-- Commit Content --}}
                        <div @click="commit.description && commit.description.length > 0 ? toggleCommitDescription(commit.short_hash) : null"
                             :class="commit.description && commit.description.length > 0 ? 'cursor-pointer' : ''"
                             class="w-full">

                            {{-- Tag Badges and Commit Hash --}}
                            <div class="mb-2 flex flex-wrap gap-1.5">

                                {{-- Tag Badges --}}
                                <template x-for="tag in commit.tags" :key="tag">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-normal bg-primary-100 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400"
                                          x-text="tag">
                                    </span>
                                </template>
                                
                                {{-- Commit Hash Badge --}}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-mono font-normal bg-primary-100 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400"
                                      x-text="commit.short_hash">
                                </span>
                                
                            </div>

                            {{-- Commit Message --}}
                            <div class="mb-2 w-full">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-relaxed" x-text="commit.message">
                                </p>
                            </div>
                            
                            {{-- Author & Time --}}
                            <div class="flex items-center gap-2 text-[9px] md:text-xs text-gray-500 dark:text-gray-400 md:gap-1 w-full">

                                <div class="w-1/2 text-start md:w-auto md:text-inherit">
                                    <span class="font-medium text-gray-700 dark:text-gray-300" x-text="commit.author_name">
                                    </span>
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

                        {{-- Commit Description (Collapsible) --}}
                        <div x-show="expandedCommit === commit.short_hash && commit.description && commit.description.length > 0"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-2"
                             class="mt-3 w-full">
                            <div class="py-2">
                                <p class="font-mono text-xs text-gray-500 dark:text-gray-400 leading-relaxed whitespace-pre-wrap" x-text="commit.description"></p>
                            </div>
                        </div>

                    </div>
                </template>
            </div>
            
            {{-- Empty State --}}
            <div x-show="!loading && commits.length === 0" class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 mb-4">
                    <x-heroicon-o-code-bracket class="w-8 h-8 text-gray-400 dark:text-gray-500" />
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">No commits found</p>
            </div>
        </div>
        
        {{-- Footer / Pagination --}}
        <div x-show="!loading && pagination && pagination.last_page > 1" class="px-6 py-4 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <span x-text="pagination ? 'Page ' + pagination.current_page + ' of ' + pagination.last_page + ' (' + pagination.total + ' commits)' : ''"></span>
                </div>
                
                <div class="flex items-center space-x-3">
                    
                    {{-- Previous Page --}}
                    <button x-show="pagination && pagination.current_page > 1"
                            @click="loadPage(pagination.current_page - 1)"
                            aria-label="Previous"
                            x-tooltip="{
                                content: @js('Previous'),
                                theme: $store.theme,
                                zIndex: 100000,
                            }"
                            class="w-10 h-10 bg-primary-500/80 dark:bg-primary-500/80 hover:bg-primary-400 dark:hover:bg-primary-400 rounded-lg flex items-center justify-center transition-all duration-300 group">
                        <x-heroicon-o-arrow-left class="w-5 h-5 text-primary-900 dark:text-primary-900 transition-colors" />
                    </button>
                    <button x-show="pagination && pagination.current_page === 1" disabled class="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center cursor-not-allowed opacity-50">
                        <x-heroicon-o-arrow-left class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                    </button>
                    
                    {{-- Next Page --}}
                    <button x-show="pagination && pagination.current_page < pagination.last_page"
                            @click="loadPage(pagination.current_page + 1)"
                            aria-label="Next"
                            x-tooltip="{
                                content: @js('Next'),
                                theme: $store.theme,
                                zIndex: 100000,
                            }"
                            class="w-10 h-10 bg-primary-500/80 dark:bg-primary-500/80 hover:bg-primary-400 dark:hover:bg-primary-400 rounded-lg flex items-center justify-center transition-all duration-300 group">
                        <x-heroicon-o-arrow-right class="w-5 h-5 text-primary-900 dark:text-primary-900 transition-colors" />
                    </button>
                    <button x-show="pagination && pagination.current_page === pagination.last_page" disabled class="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center cursor-not-allowed opacity-50">
                        <x-heroicon-o-arrow-right class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                    </button>
                    
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
    // Global function to trigger modal open from anywhere (e.g. user menu click, header badge click)
    window.showChangelogModal = function() {
        window.dispatchEvent(new CustomEvent('open-changelog-modal'));
    };

    // Auto-intercept profile/sidebar menu item clicks for Changelogs
    document.addEventListener('click', function(event) {
        let element = event.target;
        while (element) {
            if (element.textContent && (element.textContent.includes('Changelogs') || element.textContent.includes("What's New") || element.textContent.includes('Apa Yang Baru'))) {
                // Ensure it is part of a menu or dropdown
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
