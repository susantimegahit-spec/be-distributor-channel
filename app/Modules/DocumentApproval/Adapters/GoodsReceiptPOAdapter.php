<?php

namespace App\Modules\DocumentApproval\Adapters;

class GoodsReceiptPOAdapter extends BaseSapDocumentAdapter
{
    public function getDocument(int $docEntry): array
    {
        return [
            'header' => [
                'DocEntry' => $docEntry,
                'DocNum' => 'GRPO-' . (20000 + $docEntry),
                'CardCode' => 'V-0012',
                'CardName' => 'PT Aneka Kimia Raya Sejahtera',
                'DocDate' => date('Y-m-d'),
                'DocDueDate' => date('Y-m-d'),
                'Comments' => 'Penerimaan Parsial PO-50001 (Surat Jalan SJ-8891)',
                'DocCur' => 'IDR',
                'DocTotal' => 69375000,
                'SubTotal' => 62500000,
                'VatSum' => 6875000,
            ],
            'lines' => [
                [
                    'LineNum' => 0,
                    'ItemCode' => 'RM-SALT-RAW',
                    'ItemDescription' => 'Garam Kasar (Raw Solar Salt)',
                    'Quantity' => 50,
                    'UnitMsr' => 'TON',
                    'Price' => 1250000,
                    'LineTotal' => 62500000,
                    'WhsCode' => 'WHS-BLR',
                    'TaxCode' => 'PPN11',
                    'Remarks' => 'Tahap 1 - 50 Ton diterima dalam kondisi baik',
                ]
            ],
            'summary' => [
                'SubTotal' => 62500000,
                'VatSum' => 6875000,
                'DocTotal' => 69375000,
            ]
        ];
    }
}
