@php
    /** @var \App\Models\User|null $currentUser */
    /** @var \App\Models\User|null $primaryUser */
    /** @var bool $isImpersonating */
    /** @var \Illuminate\Support\Collection<int, \App\Models\FamilyMember> $switchableMembers */

    $visible = $this->isVisible();
@endphp

<div wire:key="account-switcher">
    @if ($visible)
        <div class="fi-account-switcher-menu">
            <p class="fi-account-switcher-heading">Swap Account</p>

            <div
                class="fi-account-switcher-section"
                x-tooltip="{
                    content: @js($isImpersonating ? 'Switch account (impersonating)' : 'Switch account'),
                    theme: $store.theme,
                }"
            >
                <div class="fi-account-switcher-list" aria-label="Switch account">
                    @if ($primaryUser && $currentUser?->id !== $primaryUser->id)
                        @php
                            $primaryDisplayName = $primaryUser->display_name ?? $primaryUser->name;
                        @endphp

                        <button
                            type="button"
                            wire:click="switchBack"
                            wire:loading.attr="disabled"
                            class="fi-account-switcher-account"
                        >
                            <span class="fi-account-switcher-account-avatar">
                                @if ($primaryUser->getFilamentAvatarUrl())
                                    <img
                                        src="{{ $primaryUser->getFilamentAvatarUrl() }}"
                                        alt="{{ $primaryDisplayName }}"
                                        loading="lazy"
                                    />
                                @else
                                    <span class="fi-account-switcher-account-avatar-placeholder">
                                        {{ str($primaryDisplayName)->substr(0, 1)->upper() }}
                                    </span>
                                @endif
                            </span>

                            <x-tido.single-line-text class="flex-1">{{ $primaryDisplayName }}</x-tido.single-line-text>

                            <span class="fi-account-switcher-account-chevron">
                                <x-filament::icon icon="heroicon-m-chevron-right" />
                            </span>
                        </button>
                    @endif

                    @foreach ($switchableMembers as $member)
                        @if ($currentUser?->family_member_id !== $member->id)
                            @php
                                $memberDisplayName = $member->display_name ?? $member->name;
                            @endphp

                            <button
                                type="button"
                                wire:click="switchTo({{ $member->id }})"
                                wire:loading.attr="disabled"
                                class="fi-account-switcher-account"
                                wire:key="account-switcher-member-{{ $member->id }}"
                            >
                                <span class="fi-account-switcher-account-avatar">
                                    @if ($member->getFilamentAvatarUrl())
                                        <img
                                            src="{{ $member->getFilamentAvatarUrl() }}"
                                            alt="{{ $memberDisplayName }}"
                                            loading="lazy"
                                        />
                                    @else
                                        <span class="fi-account-switcher-account-avatar-placeholder">
                                            {{ str($memberDisplayName)->substr(0, 1)->upper() }}
                                        </span>
                                    @endif
                                </span>

                                <x-tido.single-line-text class="flex-1">{{ $memberDisplayName }}</x-tido.single-line-text>

                                <span class="fi-account-switcher-account-chevron">
                                    <x-filament::icon icon="heroicon-m-chevron-right" />
                                </span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
