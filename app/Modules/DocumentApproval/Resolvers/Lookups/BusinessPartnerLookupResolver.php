<?php

namespace App\Modules\DocumentApproval\Resolvers\Lookups;

use App\Models\Distributor;
use App\Modules\DocumentApproval\Contracts\LookupResolverInterface;
use Illuminate\Support\Facades\DB;

class BusinessPartnerLookupResolver implements LookupResolverInterface
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

        // 1. Try local distributors / customers table if present
        $distributors = Distributor::whereIn('code_customer', $uniqueKeys)->pluck('name', 'code_customer')->toArray();
        foreach ($uniqueKeys as $k) {
            if (isset($distributors[$k])) {
                $results[$k] = $distributors[$k];
            }
        }

        // 2. Default fallback to key or simulated SAP BP name
        foreach ($uniqueKeys as $k) {
            if (!isset($results[$k])) {
                $results[$k] = "Vendor/BP ({$k})";
            }
        }

        return $results;
    }
}
