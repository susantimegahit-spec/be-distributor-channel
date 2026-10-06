<?php

namespace App\Modules\DocumentApproval\Resolvers;

use App\Models\DocumentField;
use App\Modules\DocumentApproval\Contracts\ValueResolverInterface;
use App\Modules\DocumentApproval\Resolvers\Lookups\LookupResolverFactory;

class LookupValueResolver implements ValueResolverInterface
{
    protected LookupResolverFactory $lookupFactory;

    public function __construct(LookupResolverFactory $lookupFactory)
    {
        $this->lookupFactory = $lookupFactory;
    }

    public function resolve(DocumentField $field, array $context, array $document = []): array
    {
        $sourceKey = $field->source ?? $field->field_code;

        if (str_contains($sourceKey, '.')) {
            $parts = explode('.', $sourceKey);
            $sourceKey = end($parts);
        }

        $rawVal = data_get($context, $sourceKey);

        if ($rawVal === null || $rawVal === '') {
            return [
                'value' => null,
                'displayValue' => '-',
            ];
        }

        $lookupConfig = $field->lookup_config ?? [];
        $lookupType = $lookupConfig['type'] ?? 'item';

        try {
            $lookupResolver = $this->lookupFactory->make($lookupType);
            $displayVal = $lookupResolver->resolve($rawVal, $lookupConfig);
        } catch (\Throwable $e) {
            $displayVal = (string) $rawVal;
        }

        return [
            'value' => $rawVal,
            'displayValue' => $displayVal ?? (string) $rawVal,
        ];
    }
}
