@php
    /** @var \App\Models\User|null $currentUser */
    /** @var bool $isImpersonating */
    /** @var \Illuminate\Support\Collection<int, \App\Models\FamilyMember> $switchableMembers */

    $visible = $this->isVisible();
@endphp

<div wire:key="account-switcher">
@if ($visible)
    <div class="fi-account-switcher">
        {{-- Impersonation banner --}}
        @if ($isImpersonating)
            <div class="fi-account-switcher-banner">
                <span class="fi-account-switcher-banner-text">
                    Viewing as
                    <strong>{{ $currentUser?->display_name ?? $currentUser?->name }}</strong>
                </span>
                <button
                    type="button"
                    wire:click="switchBack"
                    wire:loading.attr="disabled"
                    class="fi-account-switcher-banner-btn"
                >
                    <x-filament::icon
                        icon="heroicon-m-arrow-uturn-left"
                        class="fi-account-switcher-banner-btn-icon"
                    />
                    Switch back
                </button>
            </div>
        @endif

        {{-- Dropdown trigger + menu --}}
        <x-filament::dropdown
            placement="bottom-start"
            teleport
            :offset="4"
            width="xs"
        >
            <x-slot name="trigger">
                <button
                    type="button"
                    class="fi-account-switcher-trigger"
                    x-tooltip="{
                        content: @js($isImpersonating ? 'Switch account (impersonating)' : 'Switch account'),
                        theme: $store.theme,
                    }"
                >
                    @if ($currentUser?->getFilamentAvatarUrl())
                        <img
                            src="{{ $currentUser->getFilamentAvatarUrl() }}"
                            alt="{{ $currentUser->display_name ?? $currentUser->name }}"
                            class="fi-account-switcher-trigger-avatar"
                            loading="lazy"
                        />
                    @else
                        <span class="fi-account-switcher-trigger-avatar fi-account-switcher-trigger-avatar-placeholder">
                            {{ str($currentUser?->display_name ?? $currentUser?->name ?? '?')->substr(0, 1)->upper() }}
                        </span>
                    @endif

                    <span class="fi-account-switcher-trigger-name">
                        {{ $currentUser?->display_name ?? $currentUser?->name }}
                    </span>

                    <x-filament::icon
                        icon="heroicon-m-chevron-down"
                        class="fi-account-switcher-trigger-chevron"
                    />
                </button>
            </x-slot>

            {{-- Switch back option (when impersonating) --}}
            @if ($isImpersonating)
                <x-filament::dropdown.list>
                    <x-filament::dropdown.list.item
                        icon="heroicon-o-shield-check"
                        color="primary"
                        wire:click="switchBack"
                        wire:loading.attr="disabled"
                        tag="button"
                    >
                        Back to Primary
                    </x-filament::dropdown.list.item>
                </x-filament::dropdown.list>
            @endif

            {{-- Family members list --}}
            @if ($switchableMembers->isNotEmpty())
                <x-filament::dropdown.header>
                    Switch to
                </x-filament::dropdown.header>

                <x-filament::dropdown.list>
                    @foreach ($switchableMembers as $member)
                        @php
                            $isCurrentMember = $currentUser?->family_member_id === $member->id;
                            $memberDisplayName = $member->display_name ?? $member->name;
                        @endphp

                        <x-filament::dropdown.list.item
                            :color="$isCurrentMember ? 'primary' : 'gray'"
                            wire:click="switchTo({{ $member->id }})"
                            wire:loading.attr="disabled"
                            tag="button"
                            :disabled="$isCurrentMember"
                        >
                            <div class="fi-account-switcher-member-item">
                                @if ($member->getFilamentAvatarUrl())
                                    <img
                                        src="{{ $member->getFilamentAvatarUrl() }}"
                                        alt="{{ $memberDisplayName }}"
                                        class="fi-account-switcher-member-avatar"
                                        loading="lazy"
                                    />
                                @else
                                    <span class="fi-account-switcher-member-avatar fi-account-switcher-member-avatar-placeholder">
                                        {{ str($memberDisplayName)->substr(0, 1)->upper() }}
                                    </span>
                                @endif

                                <span class="fi-account-switcher-member-info">
                                    <span class="fi-account-switcher-member-name">
                                        {{ $memberDisplayName }}
                                        @if ($isCurrentMember)
                                            <span class="fi-account-switcher-member-current">(current)</span>
                                        @endif
                                    </span>

                                    @if ($member->relationshipLabel())
                                        <span class="fi-account-switcher-member-role">
                                            {{ $member->relationshipLabel() }}
                                        </span>
                                    @endif
                                </span>
                            </div>
                        </x-filament::dropdown.list.item>
                    @endforeach
                </x-filament::dropdown.list>
            @endif
        </x-filament::dropdown>
    </div>
@endif
</div>
