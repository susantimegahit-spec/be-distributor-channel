<?php

namespace App\Modules\PurchasingRequest\Repositories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Collection;

class DocumentTypeRepository implements DocumentTypeRepositoryInterface
{
    public function getAll(array $filters = []): Collection
    {
        $query = DocumentType::query();

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', $search)
                  ->orWhere('name', 'like', $search);
            });
        }

        return $query->get();
    }
}
