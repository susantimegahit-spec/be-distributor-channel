<?php

namespace App\Modules\DocumentApproval\Services;

use App\Models\DocumentApproval;
use App\Models\DocumentType;
use App\Modules\DocumentApproval\Adapters\DocumentAdapterFactory;
use Exception;

class ApprovalDetailService
{
    protected DocumentAdapterFactory $adapterFactory;
    protected DocumentRenderer $renderer;

    public function __construct(
        DocumentAdapterFactory $adapterFactory,
        DocumentRenderer $renderer
    ) {
        $this->adapterFactory = $adapterFactory;
        $this->renderer = $renderer;
    }

    /**
     * Get rendered dynamic approval detail by ID.
     */
    public function getDetail(int $approvalId): array
    {
        $approval = DocumentApproval::with(['documentType.activeSchema.fields'])->findOrFail($approvalId);
        $docType = $approval->documentType;

        if (!$docType) {
            throw new Exception("Document Type not configured for approval ID: {$approvalId}");
        }

        $schema = $docType->activeSchema;
        if (!$schema) {
            throw new Exception("Active schema not found for Document Type: {$docType->code}");
        }

        $adapter = $this->adapterFactory->make($docType->code);
        $sapDoc = $adapter->getDocument((int)$approval->sap_doc_entry);

        return $this->renderer->render($schema, $sapDoc, $approval);
    }

    /**
     * Preview dynamic document by type code and DocEntry (without prior approval record).
     */
    public function previewByDocEntry(string $docTypeCode, int $docEntry): array
    {
        $docType = DocumentType::with(['activeSchema.fields'])->where('code', strtoupper($docTypeCode))->firstOrFail();
        $schema = $docType->activeSchema;

        if (!$schema) {
            throw new Exception("Active schema not found for Document Type: {$docType->code}");
        }

        $adapter = $this->adapterFactory->make($docType->code);
        $sapDoc = $adapter->getDocument($docEntry);

        return $this->renderer->render($schema, $sapDoc, null);
    }
}
