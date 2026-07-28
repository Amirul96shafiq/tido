<div class="tido-dashboard-sticky-toolbar">
    <div class="tido-dashboard-sticky-toolbar-filters">
        @include('filament.pages.partials.dashboard-filters-dropdown')
    </div>

    <div class="tido-dashboard-sticky-toolbar-nav">
        @include('filament.schemas.components.section-nav', [
            'sections' => $sections,
            'ariaLabel' => $ariaLabel,
        ])
    </div>
</div>
