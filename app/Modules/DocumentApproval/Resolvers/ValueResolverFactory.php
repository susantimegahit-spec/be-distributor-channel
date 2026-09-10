<?php

namespace App\Modules\DocumentApproval\Resolvers;

use App\Modules\DocumentApproval\Contracts\ValueResolverInterface;
use InvalidArgumentException;

class ValueResolverFactory
{
    /**
     * @var array<string, string>
     */
    protected array $resolvers = [
        'direct' => DirectValueResolver::class,
        'lookup' => LookupValueResolver::class,
        'calculated' => CalculatedValueResolver::class,
        'static' => StaticValueResolver::class,
    ];

    public function make(string $sourceType): ValueResolverInterface
    {
        $normalized = strtolower($sourceType);

        if (!isset($this->resolvers[$normalized])) {
            throw new InvalidArgumentException("Unsupported source type: {$sourceType}");
        }

        $resolverClass = $this->resolvers[$normalized];
        return app($resolverClass);
    }
}
