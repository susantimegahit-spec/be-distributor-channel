<?php

namespace App\Modules\DocumentApproval\Adapters;

use App\Models\PurchaseRequest;
use Exception;

class PurchaseRequestAdapter extends BaseSapDocumentAdapter
{
    public function getDocument(int $docEntry): array
    {
        // 1. Look up in local purchase_requests table or SAP API
        $localPr = PurchaseRequest::with('details')->where('sap_doc_entry', $docEntry)->first();

        if ($localPr) {
            $lines = [];
            foreach ($localPr->details as $idx => $d) {
                $lines[] = [
                    'LineNum' => $idx,
                    'ItemCode' => $d->item_code,
                    'ItemDescription' => $d->item_description,
                    'Quantity' => (float) $d->quantity,
                    'UnitMsr' => $d->uom ?? $d->unit_msr ?? 'PCS',
                    'Price' => (float) ($d->unit_price ?? 0),
                    'LineTotal' => (float) (($d->quantity ?? 1) * ($d->unit_price ?? 0)),
                    'WhsCode' => $d->whs_code ?? 'WHS-BLR',
                    'Department' => $localPr->department ?? 'Purchasing',
                    'CostCenter' => $d->ocr_code ?? 'BLR',
                    'RequiredDate' => $d->pqt_req_date ?? $localPr->doc_due_date,
                    'Remarks' => $d->remarks ?? '',
                ];
            }

            return [
                'header' => [
                    'DocEntry' => $localPr->sap_doc_entry ?? $docEntry,
                    'DocNum' => $localPr->pr_number ?? $localPr->series ?? (string)$docEntry,
                    'DocDate' => $localPr->doc_date ? $localPr->doc_date->format('Y-m-d') : date('Y-m-d'),
                    'DocDueDate' => $localPr->doc_due_date ? $localPr->doc_due_date->format('Y-m-d') : date('Y-m-d'),
                    'Requester' => $localPr->requester ?? 'IND01',
                    'RequesterName' => $localPr->requester_name ?? 'Purchasing Balaraja',
                    'Department' => $localPr->department ?? '9',
                    'Comments' => $localPr->comments ?? 'Purchase Request Material',
                    'DocTotal' => (float) ($localPr->total_amount ?? 0),
                    'DocCur' => 'IDR',
                ],
                'lines' => $lines,
                'summary' => [
                    'SubTotal' => (float) ($localPr->total_amount ?? 0),
                    'DocTotal' => (float) ($localPr->total_amount ?? 0),
                ]
            ];
        }

        // Mock SAP Response fallback for demo / test
        return [
            'header' => [
                'DocEntry' => $docEntry,
                'DocNum' => 'PR-' . (40000 + $docEntry),
                'DocDate' => date('Y-m-d'),
                'DocDueDate' => date('Y-m-d', strtotime('+7 days')),
                'Requester' => 'IND01',
                'RequesterName' => 'Purchasing Department',
                'Department' => 'Purchasing',
                'Comments' => 'Permintaan Pembelian Bahan Baku Produksi',
                'DocTotal' => 75000000,
                'DocCur' => 'IDR',
            ],
            'lines' => [
                [
                    'LineNum' => 0,
                    'ItemCode' => 'RM-SALT-01',
                    'ItemDescription' => 'Garam Halus Industri 50kg',
                    'Quantity' => 500,
                    'UnitMsr' => 'SAK',
                    'Price' => 100000,
                    'LineTotal' => 50000000,
                    'WhsCode' => 'WHS-BLR',
                    'Department' => 'Production',
                    'CostCenter' => 'BLR',
                    'RequiredDate' => date('Y-m-d', strtotime('+5 days')),
                    'Remarks' => 'Urgent untuk batch produksi minggu depan',
                ],
                [
                    'LineNum' => 1,
                    'ItemCode' => 'PM-BAG-02',
                    'ItemDescription' => 'Karung Plastik Sablon Susanti 50kg',
                    'Quantity' => 5000,
                    'UnitMsr' => 'PCS',
                    'Price' => 5000,
                    'LineTotal' => 25000000,
                    'WhsCode' => 'WHS-BLR',
                    'Department' => 'Packaging',
                    'CostCenter' => 'BLR',
                    'RequiredDate' => date('Y-m-d', strtotime('+7 days')),
                    'Remarks' => 'Packaging material standard',
                ]
            ],
            'summary' => [
                'SubTotal' => 75000000,
                'DocTotal' => 75000000,
            ]
        ];
    }
}
