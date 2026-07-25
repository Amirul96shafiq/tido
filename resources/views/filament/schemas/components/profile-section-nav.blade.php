@php
    /** @var list<array{label: string, id: string}> $sections */
    $sectionIds = collect($sections)->pluck('id')->values()->all();
    $ariaLabel = $ariaLabel ?? 'Profile sections';
@endphp

<div
    class="tido-profile-section-nav"
    x-data="{
        activeId: @js($sections[0]['id'] ?? ''),
        sectionIds: @js($sectionIds),
        canScrollLeft: false,
        canScrollRight: false,
        scrollToSection(id) {
            const element = document.getElementById(id);

            if (! element) {
                return;
            }

            this.activeId = id;

            if (decodeURIComponent(window.location.hash.slice(1)) !== id) {
                history.replaceState(null, '', '#' + encodeURIComponent(id));
            }

            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
            this.$nextTick(() => this.scrollActiveTabIntoView());
        },
        onNavClick(event) {
            const link = event.target.closest('a[href^=\'#\']');

            if (! link || ! this.$el.contains(link)) {
                return;
            }

            const id = decodeURIComponent((link.getAttribute('href') || '').slice(1));

            if (! this.sectionIds.includes(id)) {
                return;
            }

            event.preventDefault();
            this.scrollToSection(id);
        },
        updateScrollHints() {
            const tabs = this.$refs.tabs;

            if (! tabs) {
                this.canScrollLeft = false;
                this.canScrollRight = false;

                return;
            }

            const epsilon = 1;
            const maxScrollLeft = tabs.scrollWidth - tabs.clientWidth;

            this.canScrollLeft = tabs.scrollLeft > epsilon;
            this.canScrollRight = tabs.scrollLeft < maxScrollLeft - epsilon;
        },
        scrollActiveTabIntoView() {
            const tabs = this.$refs.tabs;

            if (! tabs || ! this.activeId) {
                return;
            }

            const activeTab = tabs.querySelector(`a[href='#${CSS.escape(this.activeId)}']`);

            if (activeTab) {
                activeTab.scrollIntoView({ inline: 'nearest', block: 'nearest' });
            }

            this.updateScrollHints();
        },
        init() {
            const syncHash = () => {
                const hash = decodeURIComponent(window.location.hash.slice(1));

                if (hash && this.sectionIds.includes(hash)) {
                    this.activeId = hash;
                }
            };

            syncHash();
            window.addEventListener('hashchange', syncHash);

            const observer = new IntersectionObserver(
                (entries) => {
                    const visible = entries
                        .filter((entry) => entry.isIntersecting)
                        .sort((a, b) => b.intersectionRatio - a.intersectionRatio);

                    if (visible.length > 0) {
                        this.activeId = visible[0].target.id;
                    }
                },
                { rootMargin: '-30% 0px -55% 0px', threshold: [0, 0.25, 0.5, 1] },
            );

            this.$nextTick(() => {
                this.sectionIds.forEach((id) => {
                    const element = document.getElementById(id);

                    if (element) {
                        observer.observe(element);
                    }
                });

                const tabs = this.$refs.tabs;

                if (! tabs) {
                    return;
                }

                tabs.addEventListener('scroll', () => this.updateScrollHints(), { passive: true });

                const resizeObserver = new ResizeObserver(() => {
                    this.updateScrollHints();
                    this.scrollActiveTabIntoView();
                });

                resizeObserver.observe(tabs);
                this.updateScrollHints();
            });

            this.$watch('activeId', () => {
                this.$nextTick(() => this.scrollActiveTabIntoView());
            });
        },
    }"
    x-bind:class="{
        'tido-profile-section-nav--can-scroll-left': canScrollLeft,
        'tido-profile-section-nav--can-scroll-right': canScrollRight,
    }"
    x-on:click.capture="onNavClick($event)"
    x-on:open-section.window="if ($event.detail?.id) { activeId = $event.detail.id }"
>
    <div class="tido-profile-section-nav__frame">
        <div
            class="tido-profile-section-nav__fade tido-profile-section-nav__fade--left"
            aria-hidden="true"
        ></div>
        <div
            class="tido-profile-section-nav__fade tido-profile-section-nav__fade--right"
            aria-hidden="true"
        ></div>
        <x-filament::tabs :label="$ariaLabel" x-ref="tabs">
            @foreach ($sections as $section)
                <x-filament::tabs.item
                    :alpine-active="'activeId === \'' . e($section['id']) . '\''"
                    tag="a"
                    :href="'#' . $section['id']"
                    :spa-mode="false"
                >
                    {{ $section['label'] }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    </div>
</div>
