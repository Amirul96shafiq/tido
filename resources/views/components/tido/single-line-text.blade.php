@props([
    'textClass' => '',
])

<div
    x-data="{}"
    x-init="
        const measure = () => {
            const text = $refs.singleLineText;

            if (! text) {
                return;
            }

            const overflowDistance = Math.max(
                0,
                text.scrollWidth - $el.clientWidth,
            );
            $el.style.setProperty(
                '--tido-single-line-text-overflow',
                overflowDistance + 'px',
            );
        };
        $nextTick(measure);
        new ResizeObserver(() => measure()).observe($el);
    "
    {{ $attributes->class(['tido-single-line-text-clip relative min-w-0 overflow-hidden']) }}
>
    <span
        x-ref="singleLineText"
        @class(['tido-single-line-text inline-block whitespace-nowrap', $textClass])
    >{{ $slot }}</span>
</div>
