<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers\Pages;

use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Resources\FamilyMembers\Widgets\PrimaryMemberTableWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFamilyMembers extends ListRecords
{
    use PrependsHomeBreadcrumb;

    protected static string $resource = FamilyMemberResource::class;

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            PrimaryMemberTableWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
