<?php

namespace App\Modules\DocumentApproval\Resolvers\Lookups;

use App\Modules\DocumentApproval\Contracts\LookupResolverInterface;
use InvalidArgumentException;

class LookupResolverFactory
{
    /**
     * @var array<string, string>
     */
    protected array $resolvers = [
        'business_partner' => BusinessPartnerLookupResolver::class,
        'vendor' => BusinessPartnerLookupResolver::class,
        'customer' => BusinessPartnerLookupResolver::class,
        'item' => ItemLookupResolver::class,
        'warehouse' => WarehouseLookupResolver::class,
        'user' => UserLookupResolver::class,
    ];

    public function register(string $type, string $resolverClass): void
    {
        $this->resolvers[$type] = $resolverClass;
    }

    public function make(string $type): LookupResolverInterface
    {
        $normalizedType = strtolower($type);

        if (!isset($this->resolvers[$normalizedType])) {
            throw new InvalidArgumentException("Lookup resolver not found for type: {$type}");
        }

        $resolverClass = $this->resolvers[$normalizedType];
        return app($resolverClass);
    }
}
