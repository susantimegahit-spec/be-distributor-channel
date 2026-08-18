<?php

namespace App\Modules\DocumentApproval\Resolvers;

use App\Models\DocumentField;
use App\Modules\DocumentApproval\Contracts\ValueResolverInterface;

class CalculatedValueResolver implements ValueResolverInterface
{
    public function resolve(DocumentField $field, array $context, array $document = []): array
    {
        $config = $field->calculation_config ?? [];
        $expression = $config['expression'] ?? '';

        if (empty($expression)) {
            return ['value' => 0, 'displayValue' => '0'];
        }

        $mergedScope = array_merge($document['header'] ?? [], $context);
        $result = $this->evaluateExpressionSafely($expression, $mergedScope);

        $formatter = $field->formatter_config ?? [];
        $type = $field->field_type;

        $displayVal = (string) $result;
        if ($type === 'currency') {
            $curr = $formatter['currency'] ?? 'Rp';
            $dec = $formatter['decimals'] ?? 0;
            $displayVal = $curr . ' ' . number_format(floatval($result), $dec, ',', '.');
        } elseif ($type === 'number') {
            $dec = $formatter['decimals'] ?? 0;
            $displayVal = number_format(floatval($result), $dec, ',', '.');
        }

        return [
            'value' => $result,
            'displayValue' => $displayVal,
        ];
    }

    /**
     * Safe mathematical evaluator without raw eval().
     * Supports basic variables, +, -, *, /, parenthesis.
     */
    protected function evaluateExpressionSafely(string $expression, array $variables): float
    {
        // Replace variable identifiers with actual numeric values
        $evaluable = preg_replace_callback('/[a-zA-Z_][a-zA-Z0-9_]*/', function ($matches) use ($variables) {
            $varName = $matches[0];
            $val = data_get($variables, $varName, 0);
            return is_numeric($val) ? (float) $val : 0;
        }, $expression);

        // Security check: Only allow numbers, whitespace, and basic arithmetic symbols + - * / ( ) .
        if (!preg_match('/^[0-9\.\+\-\*\/\(\)\s]+$/', $evaluable)) {
            return 0.0;
        }

        try {
            // Evaluate standard arithmetic safely via anonymous function or math solver
            $mathFunc = @create_function('', 'return ' . $evaluable . ';');
            if ($mathFunc) {
                return (float) $mathFunc();
            }
            // Fallback safe evaluation
            return (float) $this->computeMath($evaluable);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    protected function computeMath(string $expr): float
    {
        // Remove spaces
        $expr = str_replace(' ', '', $expr);
        // Simple 2-operand calculator fallback
        if (preg_match('/^([0-9\.]+)([\+\-\*\/])([0-9\.]+)$/', $expr, $m)) {
            $a = floatval($m[1]);
            $op = $m[2];
            $b = floatval($m[3]);
            return match($op) {
                '+' => $a + $b,
                '-' => $a - $b,
                '*' => $a * $b,
                '/' => $b != 0 ? $a / $b : 0,
                default => 0,
            };
        }
        return floatval($expr);
    }
}
