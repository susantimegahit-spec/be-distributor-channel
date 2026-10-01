<?php

namespace App\Modules\Dashboard\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SaveDashboardLayoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'version'              => 'required|integer|min:0',
            'rows'                 => 'required|array|min:1|max:10',
            'rows.*.row'           => 'required|integer|min:1',
            'rows.*.columns'       => 'required|integer|between:1,3',
            'widgets'              => 'present|array',
            'widgets.*.id'         => 'required|string|max:100',
            'widgets.*.sort'       => 'required|integer|min:1',
            'widgets.*.row'        => 'required|integer|min:1',
            'widgets.*.column'     => 'required|integer|min:1|between:1,3',
            'widgets.*.span'       => 'required|integer|min:1|between:1,3',
            'widgets.*.properties' => 'nullable|array',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rows = $this->input('rows', []);
            if (!is_array($rows) || empty($rows)) {
                return;
            }

            // 1. Validate sequential rows starting from 1
            $rowNumbers = array_map(fn($r) => (int) ($r['row'] ?? 0), $rows);
            sort($rowNumbers);
            $expectedRowNumbers = range(1, count($rows));

            if ($rowNumbers !== $expectedRowNumbers) {
                $validator->errors()->add('rows', 'Row numbers must be sequential starting from 1 without gaps.');
            }

            // Map row number to columns count
            $rowMap = [];
            foreach ($rows as $r) {
                if (isset($r['row']) && isset($r['columns'])) {
                    $rowMap[(int) $r['row']] = (int) $r['columns'];
                }
            }

            $widgets = $this->input('widgets', []);
            if (!is_array($widgets)) {
                return;
            }

            // 2. Validate widget ID uniqueness
            $widgetIds = [];
            $widgetSorts = [];

            foreach ($widgets as $index => $widget) {
                $wId = trim((string) ($widget['id'] ?? ''));
                if ($wId !== '') {
                    if (in_array($wId, $widgetIds, true)) {
                        $validator->errors()->add("widgets.{$index}.id", "Widget ID '{$wId}' is duplicated in the layout.");
                    } else {
                        $widgetIds[] = $wId;
                    }
                }

                $wSort = $widget['sort'] ?? null;
                if ($wSort !== null) {
                    if (in_array($wSort, $widgetSorts, true)) {
                        $validator->errors()->add("widgets.{$index}.sort", "Widget sort order '{$wSort}' is duplicated.");
                    } else {
                        $widgetSorts[] = $wSort;
                    }
                }

                $wRow = isset($widget['row']) ? (int) $widget['row'] : null;
                $wCol = isset($widget['column']) ? (int) $widget['column'] : null;
                $wSpan = isset($widget['span']) ? (int) $widget['span'] : null;

                // 3. Validate row existence
                if ($wRow !== null && !isset($rowMap[$wRow])) {
                    $validator->errors()->add("widgets.{$index}.row", "Widget references row {$wRow} which is not defined in rows.");
                    continue;
                }

                if ($wRow !== null && isset($rowMap[$wRow])) {
                    $maxColumns = $rowMap[$wRow];

                    // 4. Validate column position within row bounds
                    if ($wCol !== null && $wCol > $maxColumns) {
                        $validator->errors()->add("widgets.{$index}.column", "Widget column {$wCol} exceeds the number of columns available in row {$wRow}.");
                    }

                    // 5. Validate span within row bounds
                    if ($wSpan !== null && $wSpan > $maxColumns) {
                        $validator->errors()->add("widgets.{$index}.span", "Widget span exceeds the number of columns available in row {$wRow}.");
                    }

                    // 6. Validate column + span boundary
                    if ($wCol !== null && $wSpan !== null && ($wCol + $wSpan - 1) > $maxColumns) {
                        $validator->errors()->add("widgets.{$index}.span", "Widget span exceeds the number of columns available in row {$wRow}.");
                    }
                }
            }
        });
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Dashboard layout validation failed',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
