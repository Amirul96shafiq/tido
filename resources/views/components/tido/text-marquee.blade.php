@props([
    'textClass' => 'inline-flex items-center gap-x-1 whitespace-nowrap',
])

<div
    x-data="{}"
    x-init="
        const track = $refs.marqueeTrack;
        const gap = 32;
        const speed = 40;
        let rafMeasure = null;

        const stopLoop = () => {
            if (track._marqueeRafId) {
                cancelAnimationFrame(track._marqueeRafId);
                track._marqueeRafId = null;
            }
        };

        const startLoop = () => {
            stopLoop();

            let lastTime = null;
            track._marqueeOffset = track._marqueeOffset ?? 0;

            const tick = (time) => {
                if (! track.isConnected) {
                    stopLoop();
                    return;
                }

                const scrollDistance = Number.parseFloat(track.dataset.scrollDistance ?? '0');

                if (scrollDistance <= 0) {
                    track.style.transform = '';
                    track._marqueeRafId = requestAnimationFrame(tick);
                    return;
                }

                if (lastTime === null) {
                    lastTime = time;
                }

                const delta = (time - lastTime) / 1000;
                lastTime = time;
                track._marqueeOffset = (track._marqueeOffset + (speed * delta)) % scrollDistance;
                track.style.transform = `translate3d(${-track._marqueeOffset}px, 0, 0)`;
                track._marqueeRafId = requestAnimationFrame(tick);
            };

            track._marqueeRafId = requestAnimationFrame(tick);
        };

        const applyOverflowState = (shouldOverflow) => {
            $el.classList.toggle('is-overflowing', shouldOverflow);

            if (shouldOverflow && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                startLoop();
                return;
            }

            stopLoop();
            track._marqueeOffset = 0;
            track.style.transform = '';
        };

        const measure = () => {
            const marqueeSegment = $refs.marqueeSegment;

            if (! marqueeSegment || ! track.isConnected) {
                applyOverflowState(false);
                return;
            }

            const clipWidth = $el.clientWidth;
            const segmentWidth = marqueeSegment.offsetWidth;
            const scrollDistance = segmentWidth + gap;
            const shouldOverflow = (segmentWidth - clipWidth) > 1;
            const previousDistance = track.dataset.scrollDistance ?? '';

            track.dataset.scrollDistance = String(scrollDistance);

            if (previousDistance !== String(scrollDistance)) {
                track._marqueeOffset = 0;
            }

            applyOverflowState(shouldOverflow);
        };

        const debouncedMeasure = () => {
            if (rafMeasure) {
                cancelAnimationFrame(rafMeasure);
            }

            rafMeasure = requestAnimationFrame(() => {
                rafMeasure = null;
                measure();
            });
        };

        $nextTick(measure);
        new ResizeObserver(debouncedMeasure).observe($el);
    "
    {{ $attributes->class(['tido-text-marquee-clip relative min-w-0 overflow-hidden']) }}
>
    <span
        x-ref="marqueeTrack"
        wire:ignore
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
