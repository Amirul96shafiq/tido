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
    x-data="{ allMembersOpen: false }"
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
                                    x-on:click="allMembersOpen = true"
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
