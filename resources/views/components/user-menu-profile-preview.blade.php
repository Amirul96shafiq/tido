@props([
    'user',
])

@php
    use App\Models\User;

    $name = filament()->getUserName($user);
    $username = $user->display_name ?? $name;
    $fullName = filled($user->name ?? null) ? (string) $user->name : null;
    $phone = filled($user->phone ?? null) ? (string) $user->phone : null;
    $isFamilyMember = $user instanceof User && $user->isFamilyMember();
    $email = (! $isFamilyMember && filled($user->email ?? null))
        ? (string) $user->email
        : null;
    $dateOfBirth = $user instanceof User && $user->date_of_birth !== null
        ? $user->formatDate($user->date_of_birth)
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

    $maskedDateOfBirth = $dateOfBirth !== null
        ? preg_replace('/\d/', 'x', $dateOfBirth)
        : null;
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
    @if ($phone || $email || $dateOfBirth)
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

    <div class="fi-user-menu-profile-preview-identity">
        <p class="fi-user-menu-profile-preview-name">{{ $username }}</p>

        @if ($fullName)
            <p class="fi-user-menu-profile-preview-meta">
                {{ $fullName }}
            </p>
        @endif
    </div>

    @if ($email || $phone || $dateOfBirth)
        <div class="fi-user-menu-profile-preview-details">
            @if ($email)
                <p class="fi-user-menu-profile-preview-meta">
                    <span x-show="hidden">{{ $maskedEmail }}</span>
                    <span x-show="! hidden" x-cloak>{{ $email }}</span>
                </p>
            @endif

            @if ($phone || $dateOfBirth)
                <p class="fi-user-menu-profile-preview-meta">
                    @if ($phone)
                        <span x-show="hidden">{{ $maskedPhone }}</span>
                        <span x-show="! hidden" x-cloak>{{ $phone }}</span>
                    @endif

                    @if ($phone && $dateOfBirth)
                        <span aria-hidden="true"> | </span>
                    @endif

                    @if ($dateOfBirth)
                        <span x-show="hidden">{{ $maskedDateOfBirth }}</span>
                        <span x-show="! hidden" x-cloak>{{ $dateOfBirth }}</span>
                    @endif
                </p>
            @endif
        </div>
    @endif
</div>
