<?php

namespace App\Modules\DocumentApproval\Resolvers\Lookups;

use App\Models\Item;
use App\Modules\DocumentApproval\Contracts\LookupResolverInterface;

class ItemLookupResolver implements LookupResolverInterface
{
    public function resolve(string|int $key, array $config = []): ?string
    {
        $resolved = $this->resolveMany([$key], $config);
        return $resolved[$key] ?? (string) $key;
    }

    public function resolveMany(array $keys, array $config = []): array
    {
        if (empty($keys)) {
            return [];
        }

        $results = [];
        $uniqueKeys = array_values(array_unique(array_filter($keys)));

        // Try local items table
        $items = Item::whereIn('code', $uniqueKeys)->pluck('name', 'code')->toArray();
        foreach ($uniqueKeys as $k) {
            if (isset($items[$k])) {
                $results[$k] = $items[$k];
            } else {
                $results[$k] = "Item ({$k})";
            }
        }

        return $results;
    }
}
