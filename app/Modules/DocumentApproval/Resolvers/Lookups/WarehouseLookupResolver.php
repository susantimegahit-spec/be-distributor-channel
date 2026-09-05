<?php

namespace App\Modules\DocumentApproval\Resolvers\Lookups;

use App\Models\Warehouse;
use App\Modules\DocumentApproval\Contracts\LookupResolverInterface;

class WarehouseLookupResolver implements LookupResolverInterface
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

        $warehouses = Warehouse::whereIn('code', $uniqueKeys)->pluck('name', 'code')->toArray();
        foreach ($uniqueKeys as $k) {
            $results[$k] = $warehouses[$k] ?? "Gudang ({$k})";
        }

        return $results;
    }
}
