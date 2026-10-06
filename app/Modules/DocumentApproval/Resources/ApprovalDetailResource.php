<?php

namespace App\Modules\DocumentApproval\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
