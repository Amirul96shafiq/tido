@php
    /** @var \App\Models\User|\App\Models\FamilyMember $account */
    /** @var bool $isPrimaryAccount */
    /** @var bool $fadeBottom */
    /** @var string $rowKeyPrefix */

    $accountDisplayName = $account->display_name ?? $account->name;
    $rowKey = $isPrimaryAccount
        ? "account-switcher-{$rowKeyPrefix}-primary"
        : "account-switcher-{$rowKeyPrefix}-member-{$account->id}";
@endphp

<button
    type="button"
    wire:loading.attr="disabled"
    wire:key="{{ $rowKey }}"
    @if ($isPrimaryAccount)
        wire:click="mountAction('confirmSwitchBack')"
    @else
        wire:click="mountAction('confirmSwitchTo', { familyMemberId: {{ $account->id }} })"
    @endif
    class="fi-account-switcher-account{{ $fadeBottom ? ' fi-account-switcher-account-preview-faded' : '' }}"
>
    <span class="fi-account-switcher-account-avatar">
        @if ($account->getFilamentAvatarUrl())
            <img
                src="{{ $account->getFilamentAvatarUrl() }}"
                alt="{{ $accountDisplayName }}"
                loading="lazy"
            />
        @elseif ($isPrimaryAccount)
            <span class="fi-account-switcher-account-avatar-placeholder">
                {{ str($accountDisplayName)->substr(0, 1)->upper() }}
            </span>
        @else
            <img
                src="{{ app(\Filament\AvatarProviders\UiAvatarsProvider::class)->get($account) }}"
                alt="{{ $accountDisplayName }}"
                loading="lazy"
            />
        @endif
    </span>

    <x-tido.text-marquee
        class="min-w-0 flex-1"
        text-class="inline-block whitespace-nowrap"
        wire:key="marquee-{{ $rowKey }}-{{ $accountDisplayName }}"
    >{{ $accountDisplayName }}</x-tido.text-marquee>

    <span class="fi-account-switcher-account-chevron">
        <x-filament::icon icon="heroicon-m-chevron-right" />
    </span>
</button>
