<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

final class RecordActionsGroup
{
    /**
     * @param  array<Action|ActionGroup>  $actions
     */
    public static function make(array $actions): ActionGroup
    {
        return ActionGroup::make($actions)
            ->label('Actions')
            ->tooltip('Actions');
    }
}
