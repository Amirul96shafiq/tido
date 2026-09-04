<?php

declare(strict_types=1);

namespace App\Filament\Tables\Columns;

use Closure;
use Filament\Tables\Columns\Column;
use Illuminate\Contracts\Support\Htmlable;

class LightweightSelectColumn extends Column
{
    protected string $view = 'filament.tables.columns.lightweight-select-column';

    /**
     * @var array<array-key, string | Htmlable> | Closure
     */
    protected array|Closure $options = [];

    protected bool|Closure $isSelectablePlaceholder = true;

    protected string $wireMethod = 'updateExpenseInlineSelect';

    protected function setUp(): void
    {
        parent::setUp();

        $this->disabledClick();
    }

    /**
     * @param  array<array-key, string | Htmlable> | Closure  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<array-key, string | Htmlable>
     */
    public function getOptions(): array
    {
        return $this->evaluate($this->options) ?? [];
    }

    public function selectablePlaceholder(bool|Closure $condition = true): static
    {
        $this->isSelectablePlaceholder = $condition;

        return $this;
    }

    public function isPlaceholderSelectable(): bool
    {
        return (bool) $this->evaluate($this->isSelectablePlaceholder);
    }

    public function wireMethod(string $method): static
    {
        $this->wireMethod = $method;

        return $this;
    }

    public function getWireMethod(): string
    {
        return $this->wireMethod;
    }
}
