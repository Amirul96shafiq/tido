@php
    /** @var \App\Models\User|null $currentUser */
    /** @var \App\Models\User|null $primaryUser */
    /** @var bool $isImpersonating */
    /** @var \Illuminate\Support\Collection<int, \App\Models\FamilyMember> $switchableMembers */

    $visible = $this->isVisible();
    $previewMembers = $switchableMembers
        ->filter(fn ($member): bool => $currentUser?->family_member_id !== $member->id)
        ->take($isImpersonating ? 1 : 2);
@endphp

<div
    wire:key="account-switcher"
    x-data="{ allMembersOpen: false }"
    x-on:keydown.escape.window="allMembersOpen = false"
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
                                'fadeBottom' => $loop->last,
                                'rowKeyPrefix' => 'preview',
                            ])
                        @endforeach
                    </div>

                    <div class="fi-account-switcher-cta">
                        <x-filament::button
                            type="button"
                            color="primary"
                            size="sm"
                            class="w-full"
                            aria-controls="account-switcher-all-members"
                            aria-expanded="false"
                            x-on:click="allMembersOpen = true"
                        >
                            View All Family Members
                        </x-filament::button>
                    </div>
                </div>
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

                <div class="fi-account-switcher-cta">
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
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
