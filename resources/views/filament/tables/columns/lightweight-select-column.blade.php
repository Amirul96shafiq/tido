@php
    use App\Models\Expense;

    /** @var Expense $record */
    $record = $getRecord();
    $recordKey = (string) $record->getKey();
    $state = $getState();
    $stateValue = $state === null ? '' : (string) $state;
    $options = $getOptions();
    $placeholder = $getPlaceholder();
    $canSelectPlaceholder = $isPlaceholderSelectable();
    $isDisabled = $isDisabled();
    $attribute = $getName();
    $wireMethod = $getWireMethod();
@endphp

<div
    wire:key="lightweight-select-{{ $attribute }}-{{ $recordKey }}"
    class="fi-ta-col-lightweight-select tido-expense-table-select"
    x-on:click.stop=""
>
    <div @class([
        'fi-input-wrp',
        'fi-disabled' => $isDisabled,
    ])>
        <select
            class="fi-select-input"
            @disabled($isDisabled)
            wire:loading.attr="disabled"
            wire:target="{{ $wireMethod }}"
            wire:change="{{ $wireMethod }}('{{ $attribute }}', '{{ $recordKey }}', $event.target.value === '' ? null : $event.target.value)"
        >
            @if ($canSelectPlaceholder)
                <option value="" @selected($stateValue === '')>
                    {{ $placeholder }}
                </option>
            @endif

            @foreach ($options as $value => $label)
                <option value="{{ $value }}" @selected($stateValue === (string) $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>
</div>
