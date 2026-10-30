<?php

namespace App\Modules\DocumentApproval\Contracts;

interface DocumentAdapterInterface
{
    /**
     * Get full document data from SAP by DocEntry.
     */
    public function getDocument(int $docEntry): array;

    /**
     * Extract header data from SAP raw document.
     */
    public function getHeader(array $document): array;

    /**
     * Extract line items from SAP raw document.
     */
    public function getLines(array $document): array;

    /**
     * Extract summary / totals from SAP raw document.
     */
    public function getSummary(array $document): array;

    /**
     * Validate if document exists and is ready for approval.
     */
    public function validate(int $docEntry): void;
}
