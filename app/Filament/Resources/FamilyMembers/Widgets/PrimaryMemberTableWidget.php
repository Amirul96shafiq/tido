<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers\Widgets;

use App\Filament\Resources\FamilyMembers\Tables\PrimaryMemberTable;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PrimaryMemberTableWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return PrimaryMemberTable::configure($table);
    }
}
