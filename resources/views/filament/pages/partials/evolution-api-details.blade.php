<div class="fi-evolution-api-details-list divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
    <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
        <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Connected number</dt>
        <dd class="font-mono text-gray-950 dark:text-white">
            @if (filled($connectedNumber))
                <a
                    href="{{ \App\Support\PhoneNumber::whatsAppMeUrl($connectedNumber) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-primary-600 underline underline-offset-2 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                >
                    {{ $connectedNumber }}
                </a>
            @else
                Unknown — refresh status
            @endif
        </dd>
    </div>

    @if (filled($connectedProfileName))
        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Profile name</dt>
            <dd class="text-gray-950 dark:text-white">
                {{ $connectedProfileName }}
            </dd>
        </div>
    @endif

    @if (filled($connectedVia))
        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Connected via</dt>
            <dd class="text-gray-950 dark:text-white">
                {{ $connectedVia }}
            </dd>
        </div>
    @endif

    <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
        <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Device label</dt>
        <dd class="text-gray-950 dark:text-white">
            {{ $deviceLabel }}
        </dd>
    </div>

    <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
        <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Integration</dt>
        <dd class="font-mono text-gray-950 dark:text-white">
            {{ $connectedIntegration ?? 'WHATSAPP-BAILEYS' }}
        </dd>
    </div>

    @if (filled($connectedInstanceId))
        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Instance ID</dt>
            <dd class="break-all font-mono text-xs text-gray-950 dark:text-white">
                {{ $connectedInstanceId }}
            </dd>
        </div>
    @endif

    @if ($connectedMessageCount !== null || $connectedContactCount !== null || $connectedChatCount !== null)
        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Synced data</dt>
            <dd class="text-gray-950 dark:text-white">
                {{ number_format($connectedMessageCount ?? 0) }} messages ·
                {{ number_format($connectedContactCount ?? 0) }} contacts ·
                {{ number_format($connectedChatCount ?? 0) }} chats
            </dd>
        </div>
    @endif

    <div class="flex flex-col gap-3 px-6 py-4">
        <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Contact allowlist</dt>
        <dd class="min-w-0 w-full">
            @include('filament.pages.partials.evolution-api-allowlist', [
                'allowedSenderEntries' => $allowedSenderEntries,
            ])
        </dd>
    </div>

    @if (filled($connectedUpdatedAt))
        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Last updated</dt>
            <dd class="text-gray-950 dark:text-white">
                {{ \Illuminate\Support\Carbon::parse($connectedUpdatedAt)->timezone(config('app.timezone'))->toDayDateTimeString() }}
            </dd>
        </div>
    @endif
</div>
