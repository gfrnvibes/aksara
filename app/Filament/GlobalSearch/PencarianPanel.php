<?php

namespace App\Filament\GlobalSearch;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;
use Filament\Navigation\NavigationItem;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

/** Search visible panel navigation in addition to authorized resource records. */
class PencarianPanel extends DefaultGlobalSearchProvider
{
    public function getResults(string $query): ?GlobalSearchResults
    {
        $user = Filament::auth()->user();
        $panel = Filament::getCurrentPanel();
        if (! $user instanceof User || $panel === null || ! $user->canAccessPanel($panel)) {
            return GlobalSearchResults::make();
        }
        $query = trim($query);
        if ($query === '') {
            return GlobalSearchResults::make();
        }
        $results = parent::getResults($query) ?? GlobalSearchResults::make();
        $menus = [];
        foreach (Filament::getNavigation() as $group) {
            $items = $group->getItems();
            if ($items instanceof Arrayable) {
                $items = $items->toArray();
            }
            foreach ($items as $item) {
                if (! $item instanceof NavigationItem) {
                    continue;
                }
                $children = $item->getChildItems();
                if ($children instanceof Arrayable) {
                    $children = $children->toArray();
                }
                foreach ([$item, ...$children] as $menu) {
                    if (! $menu instanceof NavigationItem || ! $menu->isVisible()) {
                        continue;
                    }
                    $label = $menu->getLabel();
                    if ($label instanceof Htmlable) {
                        $label = strip_tags($label->toHtml());
                    }
                    $url = $menu->getUrl();
                    if ($url !== null && Str::contains(Str::lower($label), Str::lower($query))) {
                        $menus[] = new GlobalSearchResult(title: $label, url: $url);
                    }
                }
            }
        }
        if ($menus !== []) {
            $results->category('Menu Panel', $menus);
        }

        return $results;
    }
}
