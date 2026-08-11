<?php

namespace App\Modules\PurchasingRequest\Services;

use App\Modules\PurchasingRequest\Repositories\DocumentTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class DocumentTypeService
{
    protected DocumentTypeRepositoryInterface $documentTypeRepository;

    public function __construct(DocumentTypeRepositoryInterface $documentTypeRepository)
    {
        $this->documentTypeRepository = $documentTypeRepository;
    }

    public function getAll(array $filters = []): Collection
    {
        return $this->documentTypeRepository->getAll($filters);
    }
}
