<?php

namespace App\Modules\DocumentApproval\Contracts;

use App\Models\DocumentField;

interface ValueResolverInterface
{
    /**
     * Resolve raw value and display value for a specific field.
     *
     * @param DocumentField $field
     * @param array $context Current row / section data (e.g. line item or header)
     * @param array $document Full document data
     * @return array ['value' => mixed, 'displayValue' => string|null]
     */
    public function resolve(DocumentField $field, array $context, array $document = []): array;
}
