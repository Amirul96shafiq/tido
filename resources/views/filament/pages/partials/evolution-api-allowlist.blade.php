@php
    use App\Filament\Resources\FamilyMembers\FamilyMemberResource;

    /** @var array{primary: list<array{name: string, display_name: string|null, phone: string, avatar_url: string}>, family: list<array{id: int, name: string, display_name: string|null, phone: string, avatar_url: string}>} $allowedSenderEntries */
    $primaryEntries = $allowedSenderEntries['primary'] ?? [];
    $familyEntries = $allowedSenderEntries['family'] ?? [];
    $visibleFamily = array_slice($familyEntries, 0, 3);
    $hiddenFamilyCount = max(0, count($familyEntries) - 3);
    $hasEntries = $primaryEntries !== [] || $familyEntries !== [];
    $cardClasses = 'flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-2.5 transition dark:border-slate-700';
    $linkCardClasses = $cardClasses.' hover:border-primary-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 dark:hover:border-primary-500';
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
            @if (isset($profileEditUrl))
                <a
                    href="{{ $profileEditUrl }}"
                    wire:key="allowlist-primary-{{ $entry['phone'] }}"
                    wire:navigate
                    class="{{ $linkCardClasses }}"
                >
            @else
                <div
                    wire:key="allowlist-primary-{{ $entry['phone'] }}"
                    class="{{ $cardClasses }}"
                >
            @endif
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
            @if (isset($profileEditUrl))
                </a>
            @else
                </div>
            @endif
        @endforeach

        @foreach ($visibleFamily as $entry)
            @php
                $heading = filled($entry['display_name'] ?? null) ? $entry['display_name'] : $entry['name'];
                $familyEditUrl = isset($entry['id'])
                    ? FamilyMemberResource::getUrl('edit', ['record' => $entry['id']])
                    : null;
            @endphp
            @if (filled($familyEditUrl))
                <a
                    href="{{ $familyEditUrl }}"
                    wire:key="allowlist-family-{{ $entry['phone'] }}"
                    wire:navigate
                    class="{{ $linkCardClasses }}"
                >
            @else
                <div
                    wire:key="allowlist-family-{{ $entry['phone'] }}"
                    class="{{ $cardClasses }}"
                >
            @endif
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
            @if (filled($familyEditUrl))
                </a>
            @else
                </div>
            @endif
        @endforeach

        @if ($hiddenFamilyCount > 0)
            @php
                $moreLabel = '+'.$hiddenFamilyCount.' more Family Member'.($hiddenFamilyCount === 1 ? '' : 's');
            @endphp
            <div class="px-1 pt-0.5 text-left text-xs text-gray-500 dark:text-gray-400">
                @isset($familyMembersUrl)
                    <a
                        href="{{ $familyMembersUrl }}"
                        wire:navigate
                        class="text-primary-600 underline underline-offset-2 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        {{ $moreLabel }}
                    </a>
                @else
                    {{ $moreLabel }}
                @endisset
            </div>
        @endif
    </div>
@endif
