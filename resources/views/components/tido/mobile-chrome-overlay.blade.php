<div
    x-data
    x-effect="
        const mobilenav = $store.tidoMobileChrome?.mobilenavActive ?? false;
        const shown = mobilenav && ($store.tidoMobileChrome?.overlayShown ?? false);
        $el.style.display = (mobilenav && shown) ? 'block' : 'none';
        $el.classList.toggle('tido-chrome-overlay-shown', shown);
        $el.classList.toggle('opacity-0', ! shown);
        $el.classList.toggle('pointer-events-none', ! shown);
        $el.style.setProperty('opacity', shown ? '1' : '0', 'important');
        $el.style.setProperty('visibility', shown ? 'visible' : 'hidden', 'important');
        $el.style.setProperty('pointer-events', shown ? 'auto' : 'none', 'important');
    "
    x-on:click="$store.tidoMobileChrome?.closeActiveChrome()"
    class="fi-sidebar-close-overlay tido-chrome-overlay tido-mobilenav-shared-chrome-overlay"
    aria-hidden="true"
></div>
