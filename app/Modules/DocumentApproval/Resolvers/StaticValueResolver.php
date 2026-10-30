<?php

namespace App\Modules\DocumentApproval\Resolvers;

use App\Models\DocumentField;
use App\Modules\DocumentApproval\Contracts\ValueResolverInterface;

class StaticValueResolver implements ValueResolverInterface
{
    public function resolve(DocumentField $field, array $context, array $document = []): array
    {
        $val = $field->source ?? '';
        return [
            'value' => $val,
            'displayValue' => (string) $val,
        ];
    }
}
