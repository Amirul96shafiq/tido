@php
    /** @var array{primary: list<array{name: string, phone: string}>, family: list<array{name: string, phone: string}>} $allowedSenderEntries */
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
    <div class="flex flex-col gap-3 text-right">
        @if ($primaryEntries !== [])
            <div class="flex flex-col gap-0.5">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    Primary
                </div>
                @foreach ($primaryEntries as $entry)
                    <div class="text-gray-950 dark:text-white">
                        {{ $entry['name'] }}
                        <span class="font-mono">({{ $entry['phone'] }})</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($familyEntries !== [])
            <div class="flex flex-col gap-0.5">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    Family Member
                </div>
                @foreach ($familyEntries as $entry)
                    <div class="text-gray-950 dark:text-white">
                        {{ $entry['name'] }}
                        <span class="font-mono">({{ $entry['phone'] }})</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
