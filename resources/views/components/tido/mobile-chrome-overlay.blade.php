<div
    x-data
    x-effect="
        const mobilenav = $store.tidoMobileChrome?.mobilenavActive ?? false;
        const shown = mobilenav && ($store.tidoMobileChrome?.overlayShown ?? false);
        $el.style.display = mobilenav ? 'block' : 'none';
        $el.classList.toggle('opacity-0', ! shown);
        $el.classList.toggle('pointer-events-none', ! shown);
    "
    x-on:click="$store.tidoMobileChrome?.closeActiveChrome()"
    class="fi-sidebar-close-overlay tido-chrome-overlay tido-mobilenav-shared-chrome-overlay"
    aria-hidden="true"
></div>
