@props([
    'textClass' => 'inline-flex items-center gap-x-1 whitespace-nowrap',
])

<div
    wire:ignore
    x-data="{
        offset: 0,
        overflowing: false,
        scrollDistance: 0,
        rafId: null,
        lastTime: null,
        speed: 40,
        reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        rafMeasure: null,

        init() {
            const track = this.$refs.marqueeTrack;
            const clip = this.$el;

            const readGap = () => {
                const styles = window.getComputedStyle(track);
                const parsed = Number.parseFloat(styles.columnGap) || Number.parseFloat(styles.gap);

                return Number.isFinite(parsed) ? parsed : 32;
            };

            const tick = (time) => {
                if (this.lastTime === null) {
                    this.lastTime = time;
                }

                const delta = (time - this.lastTime) / 1000;
                this.lastTime = time;

                if (! this.overflowing || this.reducedMotion || this.scrollDistance <= 0) {
                    this.rafId = null;
                    this.lastTime = null;

                    return;
                }

                this.offset += this.speed * delta;

                if (this.offset >= this.scrollDistance) {
                    this.offset -= this.scrollDistance;
                }

                track.style.transform = `translate3d(${-this.offset}px, 0, 0)`;
                this.rafId = requestAnimationFrame(tick);
            };

            const ensureTicker = () => {
                if (this.reducedMotion || this.rafId !== null) {
                    return;
                }

                this.rafId = requestAnimationFrame(tick);
            };

            const measure = () => {
                const segment = this.$refs.marqueeSegment;

                if (! segment || ! track.isConnected) {
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

                if (Math.abs(this.scrollDistance - scrollDistance) > 1) {
                    if (this.scrollDistance > 0 && this.offset > 0) {
                        this.offset = this.offset % scrollDistance;
                    } else {
                        this.offset = 0;
                    }

                    this.scrollDistance = scrollDistance;
                }

                this.overflowing = shouldOverflow;
                track.classList.toggle('is-overflowing', shouldOverflow);

                if (shouldOverflow && ! this.reducedMotion) {
                    ensureTicker();

                    return;
                }

                this.offset = 0;
                track.style.transform = '';

                if (this.rafId !== null) {
                    cancelAnimationFrame(this.rafId);
                    this.rafId = null;
                    this.lastTime = null;
                }
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
