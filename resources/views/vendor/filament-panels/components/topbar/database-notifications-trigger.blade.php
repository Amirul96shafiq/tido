{{--
    Visible bell moved to the user menu. This trigger stays mounted so Filament's
    modal + wire:poll keep working, and syncs unread count to the avatar badge.
--}}
<div
    class="fi-topbar-database-notifications-trigger-sync sr-only"
    aria-hidden="true"
    data-unread-count="{{ (int) $unreadNotificationsCount }}"
    x-data
    x-init="
        if (! Alpine.store('tidoNotifications')) {
            Alpine.store('tidoNotifications', { unread: 0, menuOpen: false });
        } else if (Alpine.store('tidoNotifications').menuOpen === undefined) {
            Alpine.store('tidoNotifications').menuOpen = false;
        }

        const sync = () => {
            Alpine.store('tidoNotifications').unread = Number($el.dataset.unreadCount || 0);
        };

        sync();

        new MutationObserver(sync).observe($el, {
            attributes: true,
            attributeFilter: ['data-unread-count'],
        });
    "
></div>
