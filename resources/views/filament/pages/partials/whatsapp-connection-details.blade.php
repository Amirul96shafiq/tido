<div class="fi-wa-connection-details-list divide-y divide-gray-200 text-left text-sm dark:divide-white/10">
    <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
        <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Connected number</dt>
        <dd class="font-mono text-gray-950 dark:text-white">
            {{ $connectedNumber ?? 'Unknown — refresh status' }}
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

    <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
        <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Bot allowlist</dt>
        <dd class="font-mono text-gray-950 dark:text-white">
            @forelse ($allowedSenderNumbers as $allowedNumber)
                <span @class(['block' => ! $loop->first])>{{ $allowedNumber }}</span>
            @empty
                <span class="font-sans text-warning-600 dark:text-warning-400">Not set — set PERSONAL_WHATSAPP_NUMBER</span>
            @endforelse
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
