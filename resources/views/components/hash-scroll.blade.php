{{-- SPA-safe scroll to URL hash after navigation (global search section links). --}}
<script data-navigate-once>
    (() => {
        if (window.__tidoHashScrollInstalled) {
            return;
        }

        window.__tidoHashScrollInstalled = true;

        const scrollToHash = () => {
            const hash = window.location.hash;

            if (! hash || hash.length < 2) {
                return;
            }

            const id = decodeURIComponent(hash.slice(1));
            const target = document.getElementById(id);

            if (! target) {
                return;
            }

            window.dispatchEvent(new CustomEvent('open-section', {
                detail: { id },
            }));

            requestAnimationFrame(() => {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        };

        const scheduleScroll = () => {
            window.setTimeout(scrollToHash, 150);
        };

        document.addEventListener('DOMContentLoaded', scheduleScroll);
        document.addEventListener('livewire:navigated', scheduleScroll);
        window.addEventListener('hashchange', scrollToHash);
    })();
</script>
