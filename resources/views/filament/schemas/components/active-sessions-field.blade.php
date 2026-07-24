@php
    /** @var \Illuminate\Support\Collection<int, \App\Services\ActiveSessionData> $sessions */
    /** @var string $currentId */
@endphp

<div
    data-field-wrapper
    class="fi-fo-field"
>
  <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
    <table class="w-full min-w-lg divide-y divide-gray-200 text-sm dark:divide-white/10">
      <thead class="bg-gray-50 dark:bg-white/5">
        <tr>
          <th scope="col" class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Device
          </th>
          <th scope="col" class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Created At
          </th>
          <th scope="col" class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Actions
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-transparent">
        @forelse ($sessions as $session)
          <tr wire:key="active-session-{{ $session->id }}">
            <td class="px-4 py-3 align-top">
              <div class="flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-medium text-gray-950 dark:text-white">
                    {{ $session->deviceClass }}
                  </span>
                  @if ($session->isCurrent)
                    <span class="rounded-full bg-primary-500/15 px-2 py-0.5 text-xs font-medium text-primary-700 dark:text-primary-300">
                      This device
                    </span>
                  @endif
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  {{ $session->deviceDetail }}
                </span>
              </div>
            </td>
            <td class="px-4 py-3 align-top text-gray-700 dark:text-gray-300">
              <span
                x-tooltip="{
                    content: @js($session->createdAt->format('M j, Y g:i A')),
                    theme: $store.theme,
                }"
              >
                {{ $session->createdAt->diffForHumans() }}
              </span>
            </td>
            <td class="px-4 py-3 align-top text-end">
              @if ($session->isCurrent)
                <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
              @else
                <x-filament::button
                  type="button"
                  color="danger"
                  size="sm"
                  wire:click="prepareRevokeSession({{ \Illuminate\Support\Js::from($session->id) }})"
                >
                  Revoke
                </x-filament::button>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
              No active sessions found.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
