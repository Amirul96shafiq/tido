<?php

declare(strict_types=1);

namespace App\Filament\GlobalSearch;

use CharrafiMed\GlobalSearchModal\GlobalSearchModalPlugin;
use CharrafiMed\GlobalSearchModal\GlobalSearchResults;
use CharrafiMed\GlobalSearchModal\Pages\GlobalSearch;
use CharrafiMed\GlobalSearchModal\SearchEngine;
use CharrafiMed\GlobalSearchModal\Utils\Highlighter;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

class TidoSearchEngine extends SearchEngine
{
    public function search(string $query): ?GlobalSearchResults
    {
        if (! $this->hasTenantOrIsAuthenticated()) {
            return null;
        }

        $query = trim($query);

        if ($query === '') {
            return GlobalSearchResults::make();
        }

        /** @var GlobalSearchModalPlugin $plugin */
        $plugin = filament()->getPlugin('global-search-modal');

        if ($plugin->hasCustomSearch() && ! $plugin->mergesWithCore()) {
            $customResults = $plugin->executeSearchCallback($query);

            return $this->applyHighlightingIfNeeded($customResults, $query);
        }

        $builder = GlobalSearchResults::make();
        $criteria = GlobalSearchCriteria::instance();
        $type = $criteria->type();

        if ($plugin->hasCustomSearch() && $plugin->mergesWithCore() && $type->isDestinationType()) {
            $builder->merge($plugin->executeSearchCallback($query));
        }

        if ($type === GlobalSearchType::All || $type->resourceClass() !== null) {
            $resourceResults = TidoResourceGlobalSearch::search($query);

            if ($resourceResults !== null) {
                $builder->merge($resourceResults);
            }
        }

        if ($plugin->isCustomPagesAreSearchable()) {
            $builder->merge(GlobalSearch::search($query));
        }

        if ($plugin->isSortable()) {
            $builder->sort($plugin->getSort());
        }

        if ($type === GlobalSearchType::All && in_array($criteria->sort(), ['title_asc', 'title_desc'], true)) {
            $categories = AppliesGlobalSearchCriteria::sortAllTypeCategories(
                $builder->getCategories()->all(),
                $criteria->sort(),
            );

            foreach ($categories as $name => $results) {
                $builder->category($name, $results);
            }
        }

        return $this->applyHighlightingIfNeeded($builder, $query);
    }

    protected function applyHighlightingIfNeeded(GlobalSearchResults $results, string $query): GlobalSearchResults
    {
        /** @var GlobalSearchModalPlugin $plugin */
        $plugin = filament()->getPlugin('global-search-modal');

        if (! $plugin->isMustHighlightQueryMatches()) {
            return $results;
        }

        return $this->highlightResults($results, $query);
    }

    protected function highlightResults(GlobalSearchResults $builder, string $query): GlobalSearchResults
    {
        /** @var GlobalSearchModalPlugin $plugin */
        $plugin = filament()->getPlugin('global-search-modal');
        $classes = $plugin->getHighlightQueryClasses() ?? 'text-primary-500 font-semibold hover:underline';
        $styles = $plugin->getHighlightQueryStyles() ?? '';

        foreach ($builder->getCategories() as $categoryName => $categoryResults) {
            $highlightedResults = collect($categoryResults)->map(function ($result) use ($query, $classes, $styles) {
                $result->highlightedTitle = Highlighter::make(
                    text: $result->title,
                    pattern: $query,
                    styles: $styles,
                    classes: $classes
                );

                return $result;
            });

            $builder->category(name: $categoryName, results: $highlightedResults);
        }

        return $builder;
    }

    protected function hasTenantOrIsAuthenticated(): bool
    {
        return Filament::getTenant() || Auth::check();
    }
}
