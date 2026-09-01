<div
    x-cloak
    x-data
    x-show="document.documentElement.classList.contains('tido-mobilenav')"
    x-effect="
        const shown = $store.tidoMobileChrome?.overlayShown ?? false;
        $el.classList.toggle('opacity-0', ! shown);
        $el.classList.toggle('pointer-events-none', ! shown);
    "
    x-on:click="$store.tidoMobileChrome?.closeActiveChrome()"
    class="fi-sidebar-close-overlay tido-chrome-overlay tido-mobilenav-shared-chrome-overlay"
    aria-hidden="true"
></div>
