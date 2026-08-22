@php
    use App\Services\Ollama\OllamaSettings;
    use App\Support\ClipboardCopy;
@endphp

<div class="flex flex-col gap-4">
    <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">
        Weaker GPUs or CPU-only setups can use a lighter vision model below. OCR quality may be lower — run
        <strong class="font-medium text-gray-700 dark:text-gray-200">Run test extraction</strong>
        after switching.
    </p>

    @foreach (OllamaSettings::lighterVisionModelTiers() as $tier)
        @php
            $pullCommand = OllamaSettings::pullCommandFor($tier['name']);
        @endphp

        <div
            class="rounded-xl border border-gray-200 px-4 py-3 dark:border-white/10"
            wire:key="ollama-vision-tier-{{ $tier['name'] }}"
        >
            <div class="flex flex-col gap-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $tier['label'] }}: {{ $tier['name'] }}
                    </h4>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $tier['vramHint'] }} · {{ $tier['sizeHint'] }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <code class="min-w-0 flex-1 truncate rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs text-gray-950 dark:bg-white/5 dark:text-white">
                        {{ $pullCommand }}
                    </code>

                    <x-filament::icon-button
                        type="button"
                        color="gray"
                        size="sm"
                        icon="heroicon-o-clipboard-document-list"
                        label="Copy"
                        tooltip="Copy"
                        x-on:click="{!! ClipboardCopy::alpineClickHandler($pullCommand, 'Copied') !!}"
                    />
                </div>
            </div>
        </div>
    @endforeach
</div>
