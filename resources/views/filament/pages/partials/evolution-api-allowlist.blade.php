@php
    /** @var array{primary: list<array{name: string, display_name: string|null, phone: string, avatar_url: string}>, family: list<array{name: string, display_name: string|null, phone: string, avatar_url: string}>} $allowedSenderEntries */
    $primaryEntries = $allowedSenderEntries['primary'] ?? [];
    $familyEntries = $allowedSenderEntries['family'] ?? [];
    $hasEntries = $primaryEntries !== [] || $familyEntries !== [];
@endphp

@if (! $hasEntries)
    <span class="text-warning-600 dark:text-warning-400">
        Not set —
        @isset($profileEditUrl)
            <a href="{{ $profileEditUrl }}" class="underline">set WhatsApp number in Profile</a>
        @else
            set WhatsApp number in Profile
        @endisset
    </span>
@else
    <div class="flex w-full flex-col gap-2">
        @foreach ($primaryEntries as $entry)
            @php
                $heading = filled($entry['display_name'] ?? null) ? $entry['display_name'] : $entry['name'];
            @endphp
            <div
                wire:key="allowlist-primary-{{ $entry['phone'] }}"
                class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-2.5 dark:border-slate-700"
            >
                <x-filament::avatar
                    :src="$entry['avatar_url']"
                    :alt="$heading"
                    size="sm"
                />
                <div class="min-w-0 flex-1 text-left">
                    <div class="truncate font-medium text-gray-950 dark:text-white">
                        {{ $heading }}
                    </div>
                    <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ $entry['name'] }}
                    </div>
                    <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
                        {{ $entry['phone'] }}
                    </div>
                </div>
                <x-filament::badge color="gray" size="sm">
                    Primary
                </x-filament::badge>
            </div>
        @endforeach

        @foreach ($familyEntries as $entry)
            @php
                $heading = filled($entry['display_name'] ?? null) ? $entry['display_name'] : $entry['name'];
            @endphp
            <div
                wire:key="allowlist-family-{{ $entry['phone'] }}"
                class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-2.5 dark:border-slate-700"
            >
                <x-filament::avatar
                    :src="$entry['avatar_url']"
                    :alt="$heading"
                    size="sm"
                />
                <div class="min-w-0 flex-1 text-left">
                    <div class="truncate font-medium text-gray-950 dark:text-white">
                        {{ $heading }}
                    </div>
                    <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ $entry['name'] }}
                    </div>
                    <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
                        {{ $entry['phone'] }}
                    </div>
                </div>
                <x-filament::badge color="gray" size="sm">
                    Family
                </x-filament::badge>
            </div>
        @endforeach
    </div>
@endif
