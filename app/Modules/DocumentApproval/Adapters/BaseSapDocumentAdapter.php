<?php

namespace App\Modules\DocumentApproval\Adapters;

use App\Modules\DocumentApproval\Contracts\DocumentAdapterInterface;
use Exception;

abstract class BaseSapDocumentAdapter implements DocumentAdapterInterface
{
    abstract public function getDocument(int $docEntry): array;

    public function getHeader(array $document): array
    {
        return $document['header'] ?? $document;
    }

    public function getLines(array $document): array
    {
        return $document['lines'] ?? $document['details'] ?? [];
    }

    public function getSummary(array $document): array
    {
        return $document['summary'] ?? $document['header'] ?? [];
    }

    public function validate(int $docEntry): void
    {
        if ($docEntry <= 0) {
            throw new Exception("Invalid SAP DocEntry: {$docEntry}");
        }
    }
}
