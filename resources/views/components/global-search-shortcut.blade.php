{{--
    Filament's x-mousetrap unbinds on livewire:navigating and does not always
    rebind after SPA morph. Keep Alt+K working via a document-level listener.
--}}
<script data-navigate-once>
    (() => {
        if (window.__tidoGlobalSearchShortcutInstalled) {
            return;
        }

        window.__tidoGlobalSearchShortcutInstalled = true;

        const openGlobalSearchModal = () => {
            window.dispatchEvent(new CustomEvent('open-global-search-modal', {
                detail: { id: 'global-search-modal::plugin' },
                bubbles: true,
            }));
        };

        const visibleModal = () => Array.from(document.querySelectorAll('[aria-modal="true"]'))
            .find((el) => window.getComputedStyle(el).display !== 'none');

        document.addEventListener('keydown', (event) => {
            if (! event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
                return;
            }

            if (event.key !== 'k' && event.key !== 'K') {
                return;
            }

            const modal = visibleModal();

            if (modal) {
                const isGlobalSearchModal = Boolean(
                    modal.closest('[id="global-search-modal::plugin"]')
                    || (typeof modal.id === 'string' && modal.id.includes('global-search-modal'))
                );

                if (! isGlobalSearchModal) {
                    return;
                }
            }

            event.preventDefault();
            openGlobalSearchModal();
        }, true);
    })();
</script>
