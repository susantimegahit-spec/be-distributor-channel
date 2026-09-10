<?php

namespace App\Modules\DocumentApproval\Adapters;

use App\Modules\DocumentApproval\Contracts\DocumentAdapterInterface;
use InvalidArgumentException;

class DocumentAdapterFactory
{
    /**
     * @var array<string, string>
     */
    protected array $adapters = [
        'PR' => PurchaseRequestAdapter::class,
        'PO' => PurchaseOrderAdapter::class,
        'GRPO' => GoodsReceiptPOAdapter::class,
    ];

    public function register(string $docTypeCode, string $adapterClass): void
    {
        $this->adapters[strtoupper($docTypeCode)] = $adapterClass;
    }

    public function make(string $docTypeCode): DocumentAdapterInterface
    {
        $code = strtoupper($docTypeCode);

        if (!isset($this->adapters[$code])) {
            // Default fallback generic adapter
            return app(PurchaseOrderAdapter::class);
        }

        $adapterClass = $this->adapters[$code];
        return app($adapterClass);
    }
}
