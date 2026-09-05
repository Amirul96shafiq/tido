@props([
    'textClass' => 'inline-flex items-center gap-x-1 whitespace-nowrap',
])

{{--
    Overflow measure stays in JS; motion is CSS animation so the compositor
    owns the scroll and the main thread is not writing transform every frame.
--}}
<div
    wire:ignore
    x-data="{
        overflowing: false,
        scrollDistance: 0,
        speed: 40,
        rafMeasure: null,

        prefersReducedMotion() {
            if (typeof window.tidoPrefersReducedMotion === 'function') {
                return window.tidoPrefersReducedMotion();
            }

            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        },

        init() {
            const track = this.$refs.marqueeTrack;
            const clip = this.$el;
            const sidebar = clip.closest('.fi-sidebar');

            const readGap = () => {
                const styles = window.getComputedStyle(track);
                const parsed = Number.parseFloat(styles.columnGap) || Number.parseFloat(styles.gap);

                return Number.isFinite(parsed) ? parsed : 32;
            };

            const applyMotion = (shouldOverflow, scrollDistance) => {
                this.overflowing = shouldOverflow;
                this.scrollDistance = scrollDistance;
                track.classList.toggle(
                    'is-overflowing',
                    shouldOverflow && ! this.prefersReducedMotion(),
                );

                if (shouldOverflow && ! this.prefersReducedMotion() && scrollDistance > 0) {
                    track.style.setProperty('--tido-marquee-distance', `${scrollDistance}px`);
                    track.style.setProperty(
                        '--tido-marquee-duration',
                        `${(scrollDistance / this.speed).toFixed(2)}s`,
                    );

                    return;
                }

                track.style.removeProperty('--tido-marquee-distance');
                track.style.removeProperty('--tido-marquee-duration');
            };

            const sidebarAllowsMarquee = () => {
                if (! sidebar) {
                    return true;
                }

                // Closed / expanding icon-rail widths falsely overflow every label.
                return sidebar.classList.contains('fi-sidebar-open')
                    && ! sidebar.classList.contains('fi-sidebar-animating');
            };

            const measure = () => {
                const segment = this.$refs.marqueeSegment;

                if (! segment || ! track.isConnected) {
                    return;
                }

                if (! sidebarAllowsMarquee()) {
                    applyMotion(false, 0);

                    return;
                }

                const clipWidth = clip.clientWidth;
                const segmentWidth = segment.offsetWidth;

                if (clipWidth === 0 || segmentWidth === 0) {
                    return;
                }

                const gap = readGap();
                const scrollDistance = segmentWidth + gap;
                const shouldOverflow = (segmentWidth - clipWidth) > 1;

                applyMotion(shouldOverflow, scrollDistance);
            };

            const debouncedMeasure = () => {
                if (this.rafMeasure) {
                    cancelAnimationFrame(this.rafMeasure);
                }

                this.rafMeasure = requestAnimationFrame(() => {
                    this.rafMeasure = null;
                    measure();
                });
            };

            new ResizeObserver(debouncedMeasure).observe(clip);

            if (sidebar) {
                const sidebarClassObserver = new MutationObserver(debouncedMeasure);
                sidebarClassObserver.observe(sidebar, {
                    attributes: true,
                    attributeFilter: ['class'],
                });
                this._sidebarClassObserver = sidebarClassObserver;
            }

            this._onReduceMotionChanged = () => debouncedMeasure();
            window.addEventListener('tido-reduce-motion-changed', this._onReduceMotionChanged);

            if (typeof this.$cleanup === 'function') {
                this.$cleanup(() => {
                    window.removeEventListener('tido-reduce-motion-changed', this._onReduceMotionChanged);
                    this._sidebarClassObserver?.disconnect();
                });
            }

            this.$nextTick(measure);
        },
    }"
    {{ $attributes->class(['tido-text-marquee-clip relative min-w-0 overflow-hidden']) }}
>
    <span
        x-ref="marqueeTrack"
        class="tido-text-marquee-track"
    >
        <span
            x-ref="marqueeSegment"
            @class(['tido-text-marquee-segment', $textClass])
        >{{ $slot }}</span>

        <span
            @class(['tido-text-marquee-segment', $textClass])
            aria-hidden="true"
        >{{ $slot }}</span>
    </span>
</div>
