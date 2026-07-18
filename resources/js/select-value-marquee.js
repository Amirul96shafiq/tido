/**
 * Apply `.tido-text-marquee` to Filament JS select selected labels.
 * Opt in with `.tido-select-value-marquee` on the select field (extraAttributes).
 * See docs/ui-text-marquee.md.
 */
const ROOT_SELECTOR = '.tido-select-value-marquee';

/**
 * @param {HTMLElement} root
 */
function enhanceRoot(root) {
    const ctn = root.querySelector('.fi-select-input-value-ctn');
    const label = root.querySelector('.fi-select-input-value-label');

    if (! ctn || ! label) {
        return;
    }

    ctn.classList.add('tido-text-marquee-clip', 'min-w-0', 'overflow-hidden');
    label.classList.add('inline-block', 'whitespace-nowrap');

    const measure = () => {
        ctn.style.setProperty('--tido-marquee-clip', `${ctn.clientWidth}px`);
        label.classList.toggle(
            'tido-text-marquee',
            label.scrollWidth > ctn.clientWidth + 1,
        );
    };

    if (! ctn.dataset.tidoMarqueeRo) {
        ctn.dataset.tidoMarqueeRo = '1';
        new ResizeObserver(measure).observe(ctn);
    }

    measure();
}

/**
 * @param {HTMLElement} root
 */
function observeRoot(root) {
    enhanceRoot(root);

    if (root.dataset.tidoMarqueeMo) {
        return;
    }

    root.dataset.tidoMarqueeMo = '1';
    new MutationObserver(() => enhanceRoot(root)).observe(root, {
        childList: true,
        subtree: true,
    });
}

function bind() {
    document.querySelectorAll(ROOT_SELECTOR).forEach((root) => {
        if (root instanceof HTMLElement) {
            observeRoot(root);
        }
    });
}

function init() {
    bind();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

document.addEventListener('livewire:navigated', init);
