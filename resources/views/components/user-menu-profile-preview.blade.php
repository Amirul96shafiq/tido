@props([
    'user',
])

@php
    $name = filament()->getUserName($user);
    $phone = filled($user->phone ?? null) ? (string) $user->phone : null;
    $email = filled($user->email ?? null) ? (string) $user->email : null;
@endphp

<div {{ $attributes->class(['fi-user-menu-profile-preview']) }}>
    <div class="fi-user-menu-profile-preview-avatar">
        <x-filament-panels::avatar.user :user="$user" loading="lazy" />
    </div>

    <p class="fi-user-menu-profile-preview-name">
        {{ $name }}
    </p>

    @if ($phone)
        <p class="fi-user-menu-profile-preview-meta">
            {{ $phone }}
        </p>
    @endif

    @if ($email)
        <p class="fi-user-menu-profile-preview-meta">
            {{ $email }}
        </p>
    @endif
</div>
