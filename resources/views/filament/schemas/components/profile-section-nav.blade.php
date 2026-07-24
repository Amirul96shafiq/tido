@php
    /** @var list<array{label: string, id: string}> $sections */
    $sectionIds = collect($sections)->pluck('id')->values()->all();
@endphp

<div
    class="tido-profile-section-nav"
    x-data="{
        activeId: @js($sections[0]['id'] ?? ''),
        sectionIds: @js($sectionIds),
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
            });
        },
    }"
    x-on:open-section.window="if ($event.detail?.id) { activeId = $event.detail.id }"
>
    <x-filament::tabs label="Profile sections">
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
