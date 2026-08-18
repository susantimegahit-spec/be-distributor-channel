<?php

namespace App\Modules\DocumentApproval\Resolvers;

use App\Models\DocumentField;
use App\Modules\DocumentApproval\Contracts\ValueResolverInterface;

class DirectValueResolver implements ValueResolverInterface
{
    public function resolve(DocumentField $field, array $context, array $document = []): array
    {
        $sourceKey = $field->source ?? $field->field_code;
        
        // Strip table prefix if present (e.g. OPOR.DocDate -> DocDate)
        if (str_contains($sourceKey, '.')) {
            $parts = explode('.', $sourceKey);
            $sourceKey = end($parts);
        }

        $rawVal = data_get($context, $sourceKey);

        // Auto formatting based on field_type & formatter_config
        $displayVal = $this->formatDisplayValue($rawVal, $field);

        return [
            'value' => $rawVal,
            'displayValue' => $displayVal,
        ];
    }

    protected function formatDisplayValue(mixed $value, DocumentField $field): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $type = $field->field_type;
        $config = $field->formatter_config ?? [];

        switch ($type) {
            case 'currency':
                $currency = $config['currency'] ?? 'Rp';
                $decimals = $config['decimals'] ?? 0;
                $num = is_numeric($value) ? floatval($value) : 0;
                return $currency . ' ' . number_format($num, $decimals, ',', '.');

            case 'number':
                $decimals = $config['decimals'] ?? 0;
                $num = is_numeric($value) ? floatval($value) : 0;
                return number_format($num, $decimals, ',', '.');

            case 'date':
                return date($config['format'] ?? 'd M Y', strtotime($value));

            case 'datetime':
                return date($config['format'] ?? 'd M Y H:i', strtotime($value));

            case 'boolean':
                return $value ? ($config['true_label'] ?? 'Yes') : ($config['false_label'] ?? 'No');

            default:
                return (string) $value;
        }
    }
}
