<?php

namespace App\Modules\DocumentApproval\Contracts;

interface LookupResolverInterface
{
    /**
     * Resolve a single code/key into its display name.
     */
    public function resolve(string|int $key, array $config = []): ?string;

    /**
     * Resolve multiple keys in bulk (Batch Resolver to avoid N+1).
     *
     * @param array $keys
     * @param array $config
     * @return array Map of [key => display_value]
     */
    public function resolveMany(array $keys, array $config = []): array;
}
