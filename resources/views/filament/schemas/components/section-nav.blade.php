@php
    /** @var list<array{label: string, id: string}> $sections */
    $sectionIds = collect($sections)->pluck('id')->values()->all();
    $ariaLabel = $ariaLabel ?? 'Page sections';
@endphp

<div
    class="tido-section-nav"
    x-data="{
        activeId: @js($sections[0]['id'] ?? ''),
        sectionIds: @js($sectionIds),
        canScrollLeft: false,
        canScrollRight: false,
        isDragging: false,
        dragMoved: false,
        dragStartX: 0,
        dragScrollLeft: 0,
        dragThreshold: 6,
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
            if (this.dragMoved) {
                event.preventDefault();
                this.dragMoved = false;

                return;
            }

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
        onTabPointerDown(event) {
            if (event.button !== 0) {
                return;
            }

            const tabs = this.$refs.tabs;

            if (! tabs) {
                return;
            }

            this.isDragging = true;
            this.dragMoved = false;
            this.dragStartX = event.clientX;
            this.dragScrollLeft = tabs.scrollLeft;
            tabs.setPointerCapture(event.pointerId);
        },
        onTabPointerMove(event) {
            if (! this.isDragging) {
                return;
            }

            const tabs = this.$refs.tabs;

            if (! tabs) {
                return;
            }

            const delta = event.clientX - this.dragStartX;

            if (! this.dragMoved && Math.abs(delta) < this.dragThreshold) {
                return;
            }

            this.dragMoved = true;
            tabs.scrollLeft = this.dragScrollLeft - delta;
            this.updateScrollHints();
        },
        endTabDrag(event) {
            if (! this.isDragging) {
                return;
            }

            const tabs = this.$refs.tabs;

            if (tabs?.hasPointerCapture?.(event.pointerId)) {
                tabs.releasePointerCapture(event.pointerId);
            }

            this.isDragging = false;
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
                tabs.addEventListener('pointerdown', (event) => this.onTabPointerDown(event));
                tabs.addEventListener('pointermove', (event) => this.onTabPointerMove(event));
                tabs.addEventListener('pointerup', (event) => this.endTabDrag(event));
                tabs.addEventListener('pointercancel', (event) => this.endTabDrag(event));
                tabs.addEventListener('lostpointercapture', (event) => this.endTabDrag(event));
                tabs.addEventListener('dragstart', (event) => event.preventDefault());
                tabs.querySelectorAll('a[href]').forEach((link) => {
                    link.setAttribute('draggable', 'false');
                });

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
        'tido-section-nav--can-scroll-left': canScrollLeft,
        'tido-section-nav--can-scroll-right': canScrollRight,
        'tido-section-nav--dragging': isDragging,
    }"
    x-on:click.capture="onNavClick($event)"
    x-on:open-section.window="if ($event.detail?.id) { activeId = $event.detail.id }"
>
    <div class="tido-section-nav__frame">
        <div
            class="tido-section-nav__fade tido-section-nav__fade--left"
            aria-hidden="true"
        ></div>
        <div
            class="tido-section-nav__fade tido-section-nav__fade--right"
            aria-hidden="true"
        ></div>
        <x-filament::tabs :label="$ariaLabel" x-ref="tabs">
            @foreach ($sections as $section)
                <x-filament::tabs.item
                    :alpine-active="'activeId === \'' . e($section['id']) . '\''"
                    tag="a"
                    :href="'#' . $section['id']"
                    :spa-mode="false"
                    draggable="false"
                >
                    {{ $section['label'] }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    </div>
</div>
