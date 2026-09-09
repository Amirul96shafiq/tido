@php
    /** @var \App\Models\User|null $currentUser */
    /** @var \App\Models\User|null $primaryUser */
    /** @var bool $isImpersonating */
    /** @var bool $hasFamilyMembers */
    /** @var \Illuminate\Support\Collection<int, \App\Models\FamilyMember> $switchableMembers */

    $visible = $this->isVisible();
    $previewMembers = $switchableMembers
        ->filter(fn ($member): bool => $currentUser?->family_member_id !== $member->id)
        ->take($isImpersonating ? 1 : 2);
@endphp

<div
    wire:key="account-switcher"
    x-bind:class="{ 'fi-account-switcher--all-members-open': allMembersOpen }"
    x-data="{
        allMembersOpen: false,
        mobilenavAnchor: null,
        captureMobilenavAnchor() {
            const menu = this.$el.querySelector('.fi-account-switcher-menu');
            const preview = this.$el.querySelector('.fi-account-switcher-preview');
            const cta = this.$el.querySelector('.fi-account-switcher-preview .fi-account-switcher-cta');
            const mobilenavContent = this.$el.closest('.fi-user-menu-mobilenav-content');
            const previewBox = preview?.querySelector('.fi-account-switcher-list')?.parentElement
                ?? preview?.querySelector('.fi-account-switcher-empty-panel')?.parentElement;

            if (! menu || ! previewBox || ! cta || ! mobilenavContent) {
                this.mobilenavAnchor = null;

                return;
            }

            const contentRect = mobilenavContent.getBoundingClientRect();
            const menuRect = menu.getBoundingClientRect();
            const previewBoxRect = previewBox.getBoundingClientRect();
            const ctaRect = cta.getBoundingClientRect();

            this.mobilenavAnchor = {
                top: previewBoxRect.top - contentRect.top,
                listHeight: Math.max(80, ctaRect.top - previewBoxRect.top),
                navOverlayHeight: Math.max(0, contentRect.bottom - ctaRect.bottom),
                left: menuRect.left - contentRect.left,
                right: contentRect.right - menuRect.right,
            };
        },
        clearMobilenavExpandedStyles(expanded) {
            expanded.style.removeProperty('position');
            expanded.style.removeProperty('top');
            expanded.style.removeProperty('bottom');
            expanded.style.removeProperty('left');
            expanded.style.removeProperty('right');
            expanded.style.removeProperty('max-height');
            expanded.style.removeProperty('padding-bottom');

            const list = expanded.querySelector('.fi-account-switcher-expanded-list');
            const navOverlay = expanded.querySelector('.fi-account-switcher-nav-overlay');

            if (list) {
                list.style.removeProperty('height');
                list.style.removeProperty('max-height');
                list.style.removeProperty('flex');
            }

            if (navOverlay) {
                navOverlay.style.removeProperty('height');
            }
        },
        closeProfileMenu() {
            const dropdown = this.$el.closest('.fi-dropdown');
            const dropdownData = dropdown ? Alpine.$data(dropdown) : null;

            if (dropdownData && typeof dropdownData.close === 'function') {
                dropdownData.close();
            }

            Alpine.store('tidoMobileChrome')?.closeUserMenu?.();
        },
        positionMobilenavExpandedUpward() {
            const expanded = this.$el.querySelector('.fi-account-switcher-expanded');
            const menu = this.$el.querySelector('.fi-account-switcher-menu');
            const cta = this.$el.querySelector('.fi-account-switcher-preview .fi-account-switcher-cta');
            const mobilenavContent = this.$el.closest('.fi-user-menu-mobilenav-content');

            if (! expanded) {
                return;
            }

            if (! mobilenavContent) {
                return;
            }

            if (! this.allMembersOpen || ! menu || (! cta && ! this.mobilenavAnchor)) {
                this.clearMobilenavExpandedStyles(expanded);
                this.mobilenavAnchor = null;

                return;
            }

            this.$nextTick(() => {
                const contentRect = mobilenavContent.getBoundingClientRect();
                const menuRect = menu.getBoundingClientRect();
                const preview = menu.querySelector('.fi-account-switcher-preview');
                const previewBox = preview?.querySelector('.fi-account-switcher-list')?.parentElement
                    ?? preview?.querySelector('.fi-account-switcher-empty-panel')?.parentElement;
                const previewBoxRect = previewBox?.getBoundingClientRect();
                const ctaRect = cta?.getBoundingClientRect() ?? { top: 0, bottom: 0 };
                const anchor = this.mobilenavAnchor;
                const top = anchor?.top ?? (previewBoxRect ? previewBoxRect.top - contentRect.top : menuRect.top - contentRect.top);
                const listHeight = anchor?.listHeight ?? Math.max(80, ctaRect.top - (previewBoxRect?.top ?? menuRect.top));
                const navOverlayHeight = anchor?.navOverlayHeight ?? Math.max(0, contentRect.bottom - ctaRect.bottom);
                const left = anchor?.left ?? menuRect.left - contentRect.left;
                const right = anchor?.right ?? contentRect.right - menuRect.right;

                expanded.style.position = 'absolute';
                expanded.style.top = `${top}px`;
                expanded.style.bottom = '0';
                expanded.style.left = `${left}px`;
                expanded.style.right = `${right}px`;
                expanded.style.maxHeight = 'none';
                expanded.style.paddingBottom = '0';

                const list = expanded.querySelector('.fi-account-switcher-expanded-list');
                const closeCta = expanded.querySelector('.fi-account-switcher-expanded-cta');
                const navOverlay = expanded.querySelector('.fi-account-switcher-nav-overlay');

                if (list) {
                    list.style.height = `${listHeight}px`;
                    list.style.maxHeight = `${listHeight}px`;
                    list.style.flex = '0 0 auto';
                }

                if (navOverlay) {
                    navOverlay.style.height = `${navOverlayHeight}px`;
                }

                const expandedRect = expanded.getBoundingClientRect();
                const listRect = list ? list.getBoundingClientRect() : null;
                const closeRect = closeCta ? closeCta.getBoundingClientRect() : null;

                // #region agent log
                fetch('http://127.0.0.1:7630/ingest/872a83a7-c278-4561-81ba-5cb19a834c46',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'6da26a'},body:JSON.stringify({sessionId:'6da26a',runId:'post-fix-v2',hypothesisId:'F-G',location:'account-switcher.blade.php:positionMobilenavExpandedUpward',message:'mobilenav expanded section-anchored layout',data:{top,listHeight,navOverlayHeight,expandedHeight:Math.round(expandedRect.height),closeTop:closeRect?Math.round(closeRect.top):null,ctaTop:Math.round(ctaRect.top),closeCtaDelta:closeRect?Math.round(closeRect.top-ctaRect.top):null,listHeightActual:listRect?Math.round(listRect.height):null,accountRowCount:list?list.querySelectorAll('.fi-account-switcher-account').length:0,usedAnchor:Boolean(anchor),menuPosition:getComputedStyle(menu).position},timestamp:Date.now()})}).catch(()=>{});
                // #endregion
            });
        },
    }"
    x-effect="positionMobilenavExpandedUpward()"
    x-on:click.outside="allMembersOpen = false"
    x-on:keydown.escape.window="allMembersOpen = false"
    x-on:livewire:navigate.window="allMembersOpen = false"
>
    @if ($visible)
        <div class="fi-account-switcher-menu">
            <p class="fi-account-switcher-heading">Swap Account</p>

            <div
                class="fi-account-switcher-preview"
                x-bind:class="{ 'fi-account-switcher-preview-hidden': allMembersOpen }"
                x-tooltip="{
                    content: @js($isImpersonating ? 'Switch account (impersonating)' : 'Switch account'),
                    theme: $store.theme,
                }"
            >
                @if ($switchableMembers->isEmpty() && ! $isImpersonating)
                    <div class="fi-account-switcher-section">
                        <x-empty-state-panel
                            :heading="$hasFamilyMembers ? 'No switchable members' : 'No family members yet'"
                            :description="$hasFamilyMembers
                                ? 'Enable panel login via WhatsApp OTP to allow account switching.'
                                : 'Add a family member to enable account switching.'"
                            icon="heroicon-o-user-group"
                            icon-color="gray"
                            class="fi-account-switcher-empty-panel"
                        >
                            <x-slot name="actions">
                                <x-filament::button
                                    :href="$hasFamilyMembers
                                        ? \App\Filament\Resources\FamilyMembers\FamilyMemberResource::getUrl('index')
                                        : \App\Filament\Resources\FamilyMembers\FamilyMemberResource::getUrl('create')"
                                    tag="a"
                                    wire:navigate
                                    color="primary"
                                >
                                    {{ $hasFamilyMembers ? 'Enable Family Member Switch' : 'Add New Family Member' }}
                                </x-filament::button>
                            </x-slot>
                        </x-empty-state-panel>
                    </div>
                @else
                    <div class="fi-account-switcher-section">
                        <div class="fi-account-switcher-list" aria-label="Recent family members">
                            @if ($primaryUser && $currentUser?->id !== $primaryUser->id)
                                @include('filament.livewire.partials.account-switcher-account', [
                                    'account' => $primaryUser,
                                    'isPrimaryAccount' => true,
                                    'fadeBottom' => false,
                                    'rowKeyPrefix' => 'preview',
                                ])
                            @endif

                            @foreach ($previewMembers as $member)
                                @include('filament.livewire.partials.account-switcher-account', [
                                    'account' => $member,
                                    'isPrimaryAccount' => false,
                                    'fadeBottom' => $switchableMembers->count() > 2 && $loop->last,
                                    'rowKeyPrefix' => 'preview',
                                ])
                            @endforeach
                        </div>

                        @if ($switchableMembers->count() > 2)
                            <div class="fi-account-switcher-cta">
                                <x-filament::button
                                    type="button"
                                    color="primary"
                                    size="sm"
                                    class="w-full"
                                    aria-controls="account-switcher-all-members"
                                    aria-expanded="false"
                                    x-on:click="captureMobilenavAnchor(); allMembersOpen = true"
                                >
                                    View All Family Members
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div
                id="account-switcher-all-members"
                x-cloak
                x-show="allMembersOpen"
                x-transition:enter-start="fi-opacity-0"
                x-transition:leave-end="fi-opacity-0"
                x-on:click.stop
                class="fi-account-switcher-expanded"
            >
                <div class="fi-account-switcher-expanded-list custom-scrollbar" aria-label="All family members">
                    @if ($primaryUser && $currentUser?->id !== $primaryUser->id)
                        @include('filament.livewire.partials.account-switcher-account', [
                            'account' => $primaryUser,
                            'isPrimaryAccount' => true,
                            'fadeBottom' => false,
                            'rowKeyPrefix' => 'expanded',
                        ])
                    @endif

                    @foreach ($switchableMembers as $member)
                        @if ($currentUser?->family_member_id !== $member->id)
                            @include('filament.livewire.partials.account-switcher-account', [
                                'account' => $member,
                                'isPrimaryAccount' => false,
                                'fadeBottom' => false,
                                'rowKeyPrefix' => 'expanded',
                            ])
                        @endif
                    @endforeach
                </div>

                <div class="fi-account-switcher-cta fi-account-switcher-expanded-cta">
                    <x-filament::button
                        type="button"
                        color="primary"
                        size="sm"
                        class="w-full"
                        aria-controls="account-switcher-all-members"
                        aria-expanded="true"
                        x-on:click="allMembersOpen = false"
                    >
                        Close
                    </x-filament::button>
                </div>

                <div
                    class="fi-account-switcher-nav-overlay"
                    x-show="allMembersOpen"
                    aria-hidden="true"
                ></div>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
