<?php

namespace App\Modules\DocumentApproval\Resolvers\Lookups;

use App\Models\User;
use App\Modules\DocumentApproval\Contracts\LookupResolverInterface;

class UserLookupResolver implements LookupResolverInterface
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

        $users = User::whereIn('id', $uniqueKeys)->pluck('name', 'id')->toArray();
        foreach ($uniqueKeys as $k) {
            $results[$k] = $users[$k] ?? "User #{$k}";
        }

        return $results;
    }
}
