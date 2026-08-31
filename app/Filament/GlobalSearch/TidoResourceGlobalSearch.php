<?php

declare(strict_types=1);

namespace App\Filament\GlobalSearch;

use CharrafiMed\GlobalSearchModal\GlobalSearchResults;
use Filament\Facades\Filament;

final class TidoResourceGlobalSearch
{
    public static function search(string $query): ?GlobalSearchResults
    {
        $builder = GlobalSearchResults::make();
        $criteria = GlobalSearchCriteria::instance();
        $type = $criteria->type();

        foreach (Filament::getResources() as $resource) {
            if (! $resource::canGloballySearch()) {
                continue;
            }

            if (! $type->includesResource($resource)) {
                continue;
            }

            $resourceResults = $resource::getGlobalSearchResults($query);

            if (! $resourceResults->count()) {
                continue;
            }

            $builder->category($resource::getPluralModelLabel(), $resourceResults);
        }

        return $builder;
    }
}
