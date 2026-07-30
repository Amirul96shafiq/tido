@props([
    'user',
])

@php
    use App\Models\User;

    $name = filament()->getUserName($user);
    $username = $user->display_name ?? $name;
    $phone = filled($user->phone ?? null) ? (string) $user->phone : null;
    $isFamilyMember = $user instanceof User && $user->isFamilyMember();
    $email = (! $isFamilyMember && filled($user->email ?? null))
        ? (string) $user->email
        : null;

    $maskedPhone = null;
    if ($phone !== null) {
        if (str_starts_with($phone, '+60')) {
            $prefix = '+60';
        } elseif (str_starts_with($phone, '60')) {
            $prefix = '60';
        } elseif (str_starts_with($phone, '0')) {
            $prefix = '0';
        } else {
            $prefix = substr($phone, 0, 2);
        }

        $last4 = (strlen($phone) >= 4) ? substr($phone, -4) : '';
        $middleLen = max(0, strlen($phone) - strlen($prefix) - strlen($last4));
        $maskedPhone = $prefix . str_repeat('x', $middleLen) . $last4;
    }

    $maskedEmail = null;
    if ($email !== null) {
        if (str_contains($email, '@')) {
            [$local, $domain] = explode('@', $email, 2);
            $maskedEmail = str_repeat('x', max(5, strlen($local))) . '@' . $domain;
        } else {
            $maskedEmail = str_repeat('x', strlen($email));
        }
    }
@endphp

<div
    x-data="{
        hidden: localStorage.getItem('tido_hide_profile_details') !== 'false',
        toggle() {
            this.hidden = ! this.hidden;
            localStorage.setItem('tido_hide_profile_details', this.hidden ? 'true' : 'false');
        },
    }"
    {{ $attributes->class(['fi-user-menu-profile-preview', 'relative']) }}
>
    @if ($phone || $email)
        <button
            type="button"
            x-on:click="toggle()"
            x-tooltip="{
                content: hidden ? @js('Show details') : @js('Hide details'),
                theme: $store.theme,
            }"
            aria-label="Toggle profile details visibility"
            class="absolute left-2.5 top-2.5 flex size-7 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-slate-700/60 dark:hover:text-gray-300"
        >
            <x-filament::icon
                icon="heroicon-o-eye"
                x-show="hidden"
                class="size-4"
            />
            <x-filament::icon
                icon="heroicon-o-eye-slash"
                x-show="! hidden"
                x-cloak
                class="size-4"
            />
        </button>
    @endif

    <div class="fi-user-menu-profile-preview-avatar">
        <x-filament-panels::avatar.user :user="$user" loading="lazy" />
    </div>

    <p class="fi-user-menu-profile-preview-name">{{ $username }}</p>

    @if ($phone)
        <p class="fi-user-menu-profile-preview-meta">
            <span x-show="hidden">{{ $maskedPhone }}</span>
            <span x-show="! hidden" x-cloak>{{ $phone }}</span>
        </p>
    @endif

    @if ($email)
        <p class="fi-user-menu-profile-preview-meta">
            <span x-show="hidden">{{ $maskedEmail }}</span>
            <span x-show="! hidden" x-cloak>{{ $email }}</span>
        </p>
    @endif
</div>
