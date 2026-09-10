<?php

namespace App\Modules\PurchasingRequest\Repositories;

use Illuminate\Database\Eloquent\Collection;

interface DocumentTypeRepositoryInterface
{
    public function getAll(array $filters = []): Collection;
}
