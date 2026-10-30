<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Seed SAP Document Types from official SAP Business One mapping.
     */
    public function run(): void
    {
        $types = [
            [
                'code' => 'PR',
                'name' => 'Purchase Request',
                'sap_object_type' => 1470000113,
                'module' => 'Purchasing',
                'header_source' => 'OPRQ',
                'line_source' => 'PRQ1',
                'adapter_class' => 'App\Modules\DocumentApproval\Adapters\PurchaseRequestAdapter',
                'description' => 'Permintaan Pembelian Barang & Jasa Internal',
                'is_active' => true,
            ],
            [
                'code' => 'PO',
                'name' => 'Purchase Order',
                'sap_object_type' => 22,
                'module' => 'Purchasing',
                'header_source' => 'OPOR',
                'line_source' => 'POR1',
                'adapter_class' => 'App\Modules\DocumentApproval\Adapters\PurchaseOrderAdapter',
                'description' => 'Pesanan Pembelian ke Supplier / Vendor',
                'is_active' => true,
            ],
            [
                'code' => 'GRPO',
                'name' => 'Goods Receipt PO',
                'sap_object_type' => 20,
                'module' => 'Purchasing',
                'header_source' => 'OPDN',
                'line_source' => 'PDN1',
                'adapter_class' => 'App\Modules\DocumentApproval\Adapters\GoodsReceiptPOAdapter',
                'description' => 'Penerimaan Barang Hasil Pembelian',
                'is_active' => true,
            ],
            [
                'code' => 'AP_INV',
                'name' => 'A/P Invoice',
                'sap_object_type' => 18,
                'module' => 'Purchasing',
                'header_source' => 'OPCH',
                'line_source' => 'PCH1',
                'adapter_class' => 'App\Modules\DocumentApproval\Adapters\PurchaseOrderAdapter',
                'description' => 'Faktur Pembelian / Hutang Dagang',
                'is_active' => true,
            ],
            [
                'code' => 'SO',
                'name' => 'Sales Order',
                'sap_object_type' => 17,
                'module' => 'Sales',
                'header_source' => 'ORDR',
                'line_source' => 'RDR1',
                'adapter_class' => 'App\Modules\DocumentApproval\Adapters\PurchaseOrderAdapter',
                'description' => 'Pesanan Penjualan dari Pelanggan / Distributor',
                'is_active' => true,
            ],
            [
                'code' => 'DO',
                'name' => 'Delivery Order',
                'sap_object_type' => 15,
                'module' => 'Sales',
                'header_source' => 'ODLN',
                'line_source' => 'DLN1',
                'adapter_class' => 'App\Modules\DocumentApproval\Adapters\PurchaseOrderAdapter',
                'description' => 'Surat Jalan / Pengiriman Barang',
                'is_active' => true,
            ],
            [
                'code' => 'AR_INV',
                'name' => 'A/R Invoice',
                'sap_object_type' => 13,
                'module' => 'Sales',
                'header_source' => 'OINV',
                'line_source' => 'INV1',
                'adapter_class' => 'App\Modules\DocumentApproval\Adapters\PurchaseOrderAdapter',
                'description' => 'Faktur Penjualan / Piutang Dagang',
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
