<?php

namespace App\Modules\DocumentApproval\Adapters;

class PurchaseOrderAdapter extends BaseSapDocumentAdapter
{
    public function getDocument(int $docEntry): array
    {
        // Standarized PO structure from SAP OPOR & POR1
        return [
            'header' => [
                'DocEntry' => $docEntry,
                'DocNum' => 'PO-' . (50000 + $docEntry),
                'CardCode' => 'V-0012',
                'CardName' => 'PT Aneka Kimia Raya Sejahtera',
                'DocDate' => date('Y-m-d'),
                'DocDueDate' => date('Y-m-d', strtotime('+14 days')),
                'Comments' => 'Pengadaan Bahan Baku Garam Kasar Import Q3',
                'DocCur' => 'IDR',
                'DocTotal' => 138750000,
                'VatSum' => 13750000,
                'DiscPrcnt' => 0,
                'SubTotal' => 125000000,
                'PaymentTerms' => 'Net 30 Days',
                'ShipTo' => 'Pabrik Balaraja, Tangerang',
            ],
            'lines' => [
                [
                    'LineNum' => 0,
                    'ItemCode' => 'RM-SALT-RAW',
                    'ItemDescription' => 'Garam Kasar (Raw Solar Salt)',
                    'Quantity' => 100,
                    'UnitMsr' => 'TON',
                    'Price' => 1250000,
                    'LineTotal' => 125000000,
                    'WhsCode' => 'WHS-BLR',
                    'TaxCode' => 'PPN11',
                    'Remarks' => 'Kadar NaCl min 98%',
                ]
            ],
            'summary' => [
                'SubTotal' => 125000000,
                'DiscPrcnt' => 0,
                'VatSum' => 13750000,
                'DocTotal' => 138750000,
            ]
        ];
    }
}
