<?php

namespace Database\Seeders;

use App\Models\DocumentField;
use App\Models\DocumentSchema;
use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentSchemaSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------------
        // 1. SCHEMA UNTUK PURCHASE ORDER (PO)
        // -------------------------------------------------------------
        $poType = DocumentType::where('code', 'PO')->first();
        if ($poType) {
            $poSchema = DocumentSchema::updateOrCreate(
                ['document_type_id' => $poType->id, 'version' => 1],
                [
                    'name' => 'Default Purchase Order Schema v1.0',
                    'layout_config' => [
                        'tabs' => [
                            ['id' => 'general', 'label' => 'Informasi Utama'],
                            ['id' => 'logistics', 'label' => 'Logistik & Alamat'],
                        ],
                    ],
                    'is_active' => true,
                ]
            );

            // Clear old fields if re-seeding
            DocumentField::where('document_schema_id', $poSchema->id)->delete();

            $poFields = [
                // Header Fields
                [
                    'section' => 'header',
                    'field_code' => 'DocNum',
                    'label' => 'No. PO SAP',
                    'field_type' => 'text',
                    'source_type' => 'direct',
                    'source' => 'OPOR.DocNum',
                    'ui_props' => ['tab' => 'general', 'col_span' => 4],
                    'sequence' => 1,
                ],
                [
                    'section' => 'header',
                    'field_code' => 'DocDate',
                    'label' => 'Tanggal Dokumen',
                    'field_type' => 'date',
                    'source_type' => 'direct',
                    'source' => 'OPOR.DocDate',
                    'formatter_config' => ['format' => 'd M Y'],
                    'ui_props' => ['tab' => 'general', 'col_span' => 4],
                    'sequence' => 2,
                ],
                [
                    'section' => 'header',
                    'field_code' => 'DocDueDate',
                    'label' => 'Target Pengiriman',
                    'field_type' => 'date',
                    'source_type' => 'direct',
                    'source' => 'OPOR.DocDueDate',
                    'formatter_config' => ['format' => 'd M Y'],
                    'ui_props' => ['tab' => 'general', 'col_span' => 4],
                    'sequence' => 3,
                ],
                [
                    'section' => 'header',
                    'field_code' => 'CardCode',
                    'label' => 'Supplier / Vendor',
                    'field_type' => 'lookup',
                    'source_type' => 'lookup',
                    'source' => 'OPOR.CardCode',
                    'lookup_config' => [
                        'type' => 'business_partner',
                        'table' => 'OCRD',
                        'key_field' => 'CardCode',
                        'display_field' => 'CardName',
                    ],
                    'ui_props' => ['tab' => 'general', 'col_span' => 6],
                    'sequence' => 4,
                ],
                [
                    'section' => 'header',
                    'field_code' => 'PaymentTerms',
                    'label' => 'Syarat Pembayaran',
                    'field_type' => 'text',
                    'source_type' => 'direct',
                    'source' => 'OPOR.PaymentTerms',
                    'ui_props' => ['tab' => 'general', 'col_span' => 6],
                    'sequence' => 5,
                ],
                [
                    'section' => 'header',
                    'field_code' => 'ShipTo',
                    'label' => 'Alamat Kirim (Ship-To)',
                    'field_type' => 'text',
                    'source_type' => 'direct',
                    'source' => 'OPOR.ShipTo',
                    'ui_props' => ['tab' => 'logistics', 'col_span' => 12],
                    'sequence' => 6,
                ],
                [
                    'section' => 'header',
                    'field_code' => 'Comments',
                    'label' => 'Catatan / Remarks',
                    'field_type' => 'text',
                    'source_type' => 'direct',
                    'source' => 'OPOR.Comments',
                    'ui_props' => ['tab' => 'general', 'col_span' => 12],
                    'sequence' => 7,
                ],

                // Lines / Detail Table Fields
                [
                    'section' => 'line',
                    'field_code' => 'LineNum',
                    'label' => '#',
                    'field_type' => 'number',
                    'source_type' => 'direct',
                    'source' => 'LineNum',
                    'ui_props' => ['width' => '50px', 'align' => 'center'],
                    'sequence' => 1,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'ItemCode',
                    'label' => 'Kode Barang',
                    'field_type' => 'lookup',
                    'source_type' => 'lookup',
                    'source' => 'POR1.ItemCode',
                    'lookup_config' => ['type' => 'item', 'table' => 'OITM'],
                    'ui_props' => ['width' => '140px', 'align' => 'left'],
                    'sequence' => 2,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'ItemDescription',
                    'label' => 'Deskripsi Barang',
                    'field_type' => 'text',
                    'source_type' => 'direct',
                    'source' => 'POR1.ItemDescription',
                    'ui_props' => ['width' => 'auto', 'align' => 'left'],
                    'sequence' => 3,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'Quantity',
                    'label' => 'Kuantitas',
                    'field_type' => 'number',
                    'source_type' => 'direct',
                    'source' => 'POR1.Quantity',
                    'formatter_config' => ['decimals' => 0],
                    'ui_props' => ['width' => '90px', 'align' => 'right'],
                    'sequence' => 4,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'UnitMsr',
                    'label' => 'Satuan',
                    'field_type' => 'text',
                    'source_type' => 'direct',
                    'source' => 'POR1.UnitMsr',
                    'ui_props' => ['width' => '80px', 'align' => 'center'],
                    'sequence' => 5,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'Price',
                    'label' => 'Harga Satuan',
                    'field_type' => 'currency',
                    'source_type' => 'direct',
                    'source' => 'POR1.Price',
                    'formatter_config' => ['currency' => 'Rp', 'decimals' => 0],
                    'ui_props' => ['width' => '140px', 'align' => 'right'],
                    'sequence' => 6,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'LineTotal',
                    'label' => 'Total Harga',
                    'field_type' => 'currency',
                    'source_type' => 'calculated',
                    'calculation_config' => ['expression' => 'Quantity * Price'],
                    'formatter_config' => ['currency' => 'Rp', 'decimals' => 0],
                    'ui_props' => ['width' => '150px', 'align' => 'right'],
                    'sequence' => 7,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'WhsCode',
                    'label' => 'Gudang',
                    'field_type' => 'lookup',
                    'source_type' => 'lookup',
                    'source' => 'POR1.WhsCode',
                    'lookup_config' => ['type' => 'warehouse', 'table' => 'OWHS'],
                    'ui_props' => ['width' => '110px', 'align' => 'center'],
                    'sequence' => 8,
                ],

                // Summary / Totals Fields
                [
                    'section' => 'summary',
                    'field_code' => 'SubTotal',
                    'label' => 'Subtotal',
                    'field_type' => 'currency',
                    'source_type' => 'direct',
                    'source' => 'OPOR.SubTotal',
                    'formatter_config' => ['currency' => 'Rp', 'decimals' => 0],
                    'sequence' => 1,
                ],
                [
                    'section' => 'summary',
                    'field_code' => 'VatSum',
                    'label' => 'PPN (11%)',
                    'field_type' => 'currency',
                    'source_type' => 'direct',
                    'source' => 'OPOR.VatSum',
                    'formatter_config' => ['currency' => 'Rp', 'decimals' => 0],
                    'sequence' => 2,
                ],
                [
                    'section' => 'summary',
                    'field_code' => 'DocTotal',
                    'label' => 'Grand Total PO',
                    'field_type' => 'currency',
                    'source_type' => 'direct',
                    'source' => 'OPOR.DocTotal',
                    'formatter_config' => ['currency' => 'Rp', 'decimals' => 0],
                    'ui_props' => ['is_highlight' => true],
                    'sequence' => 3,
                ],
            ];

            foreach ($poFields as $field) {
                DocumentField::create(array_merge($field, ['document_schema_id' => $poSchema->id]));
            }
        }

        // -------------------------------------------------------------
        // 2. SCHEMA UNTUK PURCHASE REQUEST (PR)
        // -------------------------------------------------------------
        $prType = DocumentType::where('code', 'PR')->first();
        if ($prType) {
            $prSchema = DocumentSchema::updateOrCreate(
                ['document_type_id' => $prType->id, 'version' => 1],
                [
                    'name' => 'Default Purchase Request Schema v1.0',
                    'layout_config' => [
                        'tabs' => [
                            ['id' => 'general', 'label' => 'Informasi Permintaan'],
                        ],
                    ],
                    'is_active' => true,
                ]
            );

            DocumentField::where('document_schema_id', $prSchema->id)->delete();

            $prFields = [
                [
                    'section' => 'header',
                    'field_code' => 'DocNum',
                    'label' => 'No. PR SAP',
                    'field_type' => 'text',
                    'source_type' => 'direct',
                    'source' => 'DocNum',
                    'ui_props' => ['tab' => 'general', 'col_span' => 4],
                    'sequence' => 1,
                ],
                [
                    'section' => 'header',
                    'field_code' => 'DocDate',
                    'label' => 'Tanggal Pengajuan',
                    'field_type' => 'date',
                    'source_type' => 'direct',
                    'source' => 'DocDate',
                    'formatter_config' => ['format' => 'd M Y'],
                    'ui_props' => ['tab' => 'general', 'col_span' => 4],
                    'sequence' => 2,
                ],
                [
                    'section' => 'header',
                    'field_code' => 'RequesterName',
                    'label' => 'Pemohon (Requester)',
                    'field_type' => 'text',
                    'source_type' => 'direct',
                    'source' => 'RequesterName',
                    'ui_props' => ['tab' => 'general', 'col_span' => 4],
                    'sequence' => 3,
                ],
                [
                    'section' => 'header',
                    'field_code' => 'Department',
                    'label' => 'Departemen',
                    'field_type' => 'text',
                    'source_type' => 'direct',
                    'source' => 'Department',
                    'ui_props' => ['tab' => 'general', 'col_span' => 4],
                    'sequence' => 4,
                ],
                [
                    'section' => 'header',
                    'field_code' => 'Comments',
                    'label' => 'Keperluan Permintaan',
                    'field_type' => 'text',
                    'source_type' => 'direct',
                    'source' => 'Comments',
                    'ui_props' => ['tab' => 'general', 'col_span' => 8],
                    'sequence' => 5,
                ],

                // Line items PR
                [
                    'section' => 'line',
                    'field_code' => 'LineNum',
                    'label' => '#',
                    'field_type' => 'number',
                    'source_type' => 'direct',
                    'source' => 'LineNum',
                    'ui_props' => ['width' => '50px', 'align' => 'center'],
                    'sequence' => 1,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'ItemCode',
                    'label' => 'Kode Barang',
                    'field_type' => 'lookup',
                    'source_type' => 'lookup',
                    'source' => 'ItemCode',
                    'lookup_config' => ['type' => 'item'],
                    'ui_props' => ['width' => '140px', 'align' => 'left'],
                    'sequence' => 2,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'ItemDescription',
                    'label' => 'Deskripsi Barang',
                    'field_type' => 'text',
                    'source_type' => 'direct',
                    'source' => 'ItemDescription',
                    'ui_props' => ['width' => 'auto', 'align' => 'left'],
                    'sequence' => 3,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'Quantity',
                    'label' => 'Qty Diminta',
                    'field_type' => 'number',
                    'source_type' => 'direct',
                    'source' => 'Quantity',
                    'formatter_config' => ['decimals' => 0],
                    'ui_props' => ['width' => '100px', 'align' => 'right'],
                    'sequence' => 4,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'UnitMsr',
                    'label' => 'Satuan',
                    'field_type' => 'text',
                    'source_type' => 'direct',
                    'source' => 'UnitMsr',
                    'ui_props' => ['width' => '80px', 'align' => 'center'],
                    'sequence' => 5,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'Price',
                    'label' => 'Estimasi Harga',
                    'field_type' => 'currency',
                    'source_type' => 'direct',
                    'source' => 'Price',
                    'formatter_config' => ['currency' => 'Rp', 'decimals' => 0],
                    'ui_props' => ['width' => '140px', 'align' => 'right'],
                    'sequence' => 6,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'LineTotal',
                    'label' => 'Est. Total',
                    'field_type' => 'currency',
                    'source_type' => 'calculated',
                    'calculation_config' => ['expression' => 'Quantity * Price'],
                    'formatter_config' => ['currency' => 'Rp', 'decimals' => 0],
                    'ui_props' => ['width' => '140px', 'align' => 'right'],
                    'sequence' => 7,
                ],
                [
                    'section' => 'line',
                    'field_code' => 'WhsCode',
                    'label' => 'Gudang Tujuan',
                    'field_type' => 'lookup',
                    'source_type' => 'lookup',
                    'source' => 'WhsCode',
                    'lookup_config' => ['type' => 'warehouse'],
                    'ui_props' => ['width' => '110px', 'align' => 'center'],
                    'sequence' => 8,
                ],

                // Summary
                [
                    'section' => 'summary',
                    'field_code' => 'DocTotal',
                    'label' => 'Total Estimasi Anggaran PR',
                    'field_type' => 'currency',
                    'source_type' => 'direct',
                    'source' => 'DocTotal',
                    'formatter_config' => ['currency' => 'Rp', 'decimals' => 0],
                    'ui_props' => ['is_highlight' => true],
                    'sequence' => 1,
                ]
            ];

            foreach ($prFields as $field) {
                DocumentField::create(array_merge($field, ['document_schema_id' => $prSchema->id]));
            }
        }
    }
}
