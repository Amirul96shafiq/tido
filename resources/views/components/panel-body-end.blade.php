<x-changelog-modal />
@auth
    @if (filament()->hasDatabaseNotifications())
        @livewire(filament()->getDatabaseNotificationsLivewireComponent(), [
            'lazy' => false,
        ], key('panel-database-notifications'))
    @endif
@endauth
<x-restore-backup-modal />
<x-drag-drop-config />
<x-go-to-top />
<x-go-to-bottom />
<x-global-search-shortcut />
<x-hash-scroll />

@auth
    <x-tido.mobile-chrome-overlay />
    @livewire('tido.mobile_nav')
@endauth
