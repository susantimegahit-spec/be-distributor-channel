<?php

namespace App\Modules\DocumentApproval\Services;

use App\Models\DocumentApproval;
use App\Models\DocumentField;
use App\Models\DocumentSchema;
use App\Modules\DocumentApproval\Resolvers\ValueResolverFactory;

class DocumentRenderer
{
    protected ValueResolverFactory $resolverFactory;

    public function __construct(ValueResolverFactory $resolverFactory)
    {
        $this->resolverFactory = $resolverFactory;
    }

    /**
     * Render normalized document DTO array for Frontend consumption.
     */
    public function render(DocumentSchema $schema, array $sapDoc, ?DocumentApproval $approval = null): array
    {
        $headerContext = $sapDoc['header'] ?? [];
        $linesContext = $sapDoc['lines'] ?? [];
        $summaryContext = $sapDoc['summary'] ?? $headerContext;

        // 1. Render Header Fields
        $renderedHeader = [];
        $headerFields = $schema->headerFields()->get();
        foreach ($headerFields as $field) {
            $renderedHeader[] = $this->renderField($field, $headerContext, $sapDoc);
        }

        // 2. Render Lines Section (Columns Metadata + Row Data)
        $lineFields = $schema->lineFields()->get();
        $renderedColumns = [];
        foreach ($lineFields as $field) {
            $ui = $field->ui_props ?? [];
            $renderedColumns[] = [
                'field' => $field->field_code,
                'label' => $field->label,
                'type' => $field->field_type,
                'align' => $ui['align'] ?? ($field->field_type === 'currency' || $field->field_type === 'number' ? 'right' : 'left'),
                'width' => $ui['width'] ?? 'auto',
            ];
        }

        $renderedRows = [];
        foreach ($linesContext as $rowIdx => $lineRow) {
            $renderedRow = [];
            foreach ($lineFields as $field) {
                $renderedRow[$field->field_code] = $this->renderField($field, $lineRow, $sapDoc);
            }
            $renderedRows[] = $renderedRow;
        }

        // 3. Render Summary Fields
        $renderedSummary = [];
        $summaryFields = $schema->summaryFields()->get();
        foreach ($summaryFields as $field) {
            $renderedSummary[] = $this->renderField($field, $summaryContext, $sapDoc);
        }

        // 4. Extract Approval Timeline / History
        $renderedHistory = [];
        if ($approval) {
            $approval->loadMissing('histories.user');
            foreach ($approval->histories as $h) {
                $renderedHistory[] = [
                    'id' => $h->id,
                    'level' => $h->level,
                    'stageName' => $h->stage_name,
                    'action' => $h->action,
                    'userName' => $h->user_name ?? ($h->user?->name ?? 'System'),
                    'userRole' => $h->user_role,
                    'notes' => $h->notes,
                    'actionAt' => $h->action_at ? $h->action_at->format('Y-m-d H:i:s') : null,
                ];
            }
        }

        return [
            'approval' => $approval ? [
                'id' => $approval->id,
                'status' => $approval->status,
                'currentLevel' => $approval->current_level,
                'maxLevel' => $approval->max_level,
                'requester' => [
                    'id' => $approval->requester_id,
                    'name' => $approval->requester_name,
                ],
                'submittedAt' => $approval->submitted_at ? $approval->submitted_at->format('Y-m-d H:i:s') : null,
                'approvedAt' => $approval->approved_at ? $approval->approved_at->format('Y-m-d H:i:s') : null,
                'rejectedAt' => $approval->rejected_at ? $approval->rejected_at->format('Y-m-d H:i:s') : null,
            ] : null,
            'document' => [
                'typeCode' => $schema->documentType->code,
                'typeName' => $schema->documentType->name,
                'sapObjectType' => $schema->documentType->sap_object_type,
                'docEntry' => $approval->sap_doc_entry ?? ($headerContext['DocEntry'] ?? null),
                'docNum' => $approval->sap_doc_num ?? ($headerContext['DocNum'] ?? null),
                'currency' => $headerContext['DocCur'] ?? ($approval->currency ?? 'IDR'),
            ],
            'layout' => $schema->layout_config ?? [
                'tabs' => [
                    ['id' => 'general', 'label' => 'Informasi Utama'],
                ],
            ],
            'header' => $renderedHeader,
            'lines' => [
                'columns' => $renderedColumns,
                'data' => $renderedRows,
                'totalRows' => count($renderedRows),
            ],
            'summary' => $renderedSummary,
            'approvalHistory' => $renderedHistory,
        ];
    }

    protected function renderField(DocumentField $field, array $context, array $fullDoc): array
    {
        $resolver = $this->resolverFactory->make($field->source_type);
        $resolved = $resolver->resolve($field, $context, $fullDoc);

        return [
            'field' => $field->field_code,
            'label' => $field->label,
            'type' => $field->field_type,
            'value' => $resolved['value'],
            'displayValue' => $resolved['displayValue'],
            'readonly' => $field->is_readonly,
            'required' => $field->is_required,
            'ui' => $field->ui_props ?? [],
        ];
    }
}
