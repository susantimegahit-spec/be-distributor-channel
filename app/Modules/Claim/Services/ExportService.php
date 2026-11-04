<?php

namespace App\Modules\Claim\Services;

use App\Modules\Claim\Repositories\ResultRepositoryInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class ExportService
{
    /**
     * @var ResultRepositoryInterface
     */
    protected ResultRepositoryInterface $resultRepository;

    /**
     * ExportService constructor.
     *
     * @param ResultRepositoryInterface $resultRepository
     */
    public function __construct(ResultRepositoryInterface $resultRepository)
    {
        $this->resultRepository = $resultRepository;
    }

    /**
     * Generate Excel export stream for calculation results.
     *
     * @param array $filters
     * @return StreamedResponse
     */
    public function exportResults(array $filters): StreamedResponse
    {
        // Get results up to a high limit for export
        $results = $this->resultRepository->paginateResults($filters, 100000);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Hasil Perhitungan');

        // Headers
        $headers = [
            'Customer Code',
            'Customer Name',
            'Depo',
            'Kode Customer',
            'Nama Customer',
            'Alamat',
            'Kota / Kabupaten',
            'Kecamatan',
            'Salesman Distributor',
            'Customer Type',
            'Item Code',
            'Item Name',
            'Jenis Barang',
            'Transaction Date',
            'Jenis Transaksi',
            'Channel',
            'Invoice',
            'Qty Kg',
            'Selling Price per Kg',
            'Harga Program per Kg',
            'Diskon per Kg',
            'Total Diskon',
            'DPP',
            'Diskon 1',
            'Diskon 2',
            'Diskon 3',
            'Diskon 4',
            'Diskon 5',
            'PPN',
            'NET',
            'Status',
            'Status Description'
        ];

        foreach ($headers as $colIndex => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
            $sheet->getStyle("{$colLetter}1")->getFont()->setBold(true);
        }

        // Data Rows
        $rowNum = 2;
        foreach ($results->items() as $item) {
            $dateStr = $item->transaction_date instanceof Carbon
                ? $item->transaction_date->format('Y-m-d')
                : (is_string($item->transaction_date) ? substr($item->transaction_date, 0, 10) : $item->transaction_date);

            $sheet->setCellValue("A{$rowNum}", $item->customer_code);
            $sheet->setCellValue("B{$rowNum}", $item->customer_name);
            $sheet->setCellValue("C{$rowNum}", $item->depo);
            $sheet->setCellValue("D{$rowNum}", $item->distributor_customer_code);
            $sheet->setCellValue("E{$rowNum}", $item->distributor_customer_name);
            $sheet->setCellValue("F{$rowNum}", $item->address);
            $sheet->setCellValue("G{$rowNum}", $item->city);
            $sheet->setCellValue("H{$rowNum}", $item->district);
            $sheet->setCellValue("I{$rowNum}", $item->salesman_name);
            $sheet->setCellValue("J{$rowNum}", $item->customer_type);
            $sheet->setCellValue("K{$rowNum}", $item->item_code);
            $sheet->setCellValue("L{$rowNum}", $item->item_name);
            $sheet->setCellValue("M{$rowNum}", $item->item_category);
            $sheet->setCellValue("N{$rowNum}", $dateStr);
            $sheet->setCellValue("O{$rowNum}", $item->transaction_type);
            $sheet->setCellValue("P{$rowNum}", $item->channel);
            $sheet->setCellValue("Q{$rowNum}", $item->invoice_no);
            $sheet->setCellValue("R{$rowNum}", (float)$item->qty_kg);
            $sheet->setCellValue("S{$rowNum}", (float)$item->sell_price_per_kg);
            $sheet->setCellValue("T{$rowNum}", (float)$item->harga_program_per_kg);
            $sheet->setCellValue("U{$rowNum}", (float)$item->diskon_per_kg);
            $sheet->setCellValue("V{$rowNum}", (float)$item->total_diskon);
            $sheet->setCellValue("W{$rowNum}", $item->dpp !== null ? (float)$item->dpp : null);
            $sheet->setCellValue("X{$rowNum}", $item->diskon_1 !== null ? (float)$item->diskon_1 : null);
            $sheet->setCellValue("Y{$rowNum}", $item->diskon_2 !== null ? (float)$item->diskon_2 : null);
            $sheet->setCellValue("Z{$rowNum}", $item->diskon_3 !== null ? (float)$item->diskon_3 : null);
            $sheet->setCellValue("AA{$rowNum}", $item->diskon_4 !== null ? (float)$item->diskon_4 : null);
            $sheet->setCellValue("AB{$rowNum}", $item->diskon_5 !== null ? (float)$item->diskon_5 : null);
            $sheet->setCellValue("AC{$rowNum}", $item->ppn !== null ? (float)$item->ppn : null);
            $sheet->setCellValue("AD{$rowNum}", $item->net !== null ? (float)$item->net : null);
            $sheet->setCellValue("AE{$rowNum}", $item->status);
            $sheet->setCellValue("AF{$rowNum}", $item->desc_status);

            $rowNum++;
        }

        // Set static column widths to prevent Gd requirements during font auto-sizing
        $columnWidths = [
            'A' => 15, // Customer Code
            'B' => 25, // Customer Name
            'C' => 15, // Depo
            'D' => 15, // Kode Customer
            'E' => 25, // Nama Customer
            'F' => 30, // Alamat
            'G' => 18, // Kota / Kabupaten
            'H' => 18, // Kecamatan
            'I' => 20, // Salesman Distributor
            'J' => 15, // Customer Type
            'K' => 15, // Item Code
            'L' => 25, // Item Name
            'M' => 15, // Jenis Barang
            'N' => 15, // Transaction Date
            'O' => 15, // Jenis Transaksi
            'P' => 15, // Channel
            'Q' => 18, // Invoice
            'R' => 12, // Qty Kg
            'S' => 18, // Selling Price
            'T' => 20, // Harga Program per Kg
            'U' => 15, // Diskon per Kg
            'V' => 18, // Total Diskon
            'W' => 18, // DPP
            'X' => 15, // Diskon 1
            'Y' => 15, // Diskon 2
            'Z' => 15, // Diskon 3
            'AA' => 15, // Diskon 4
            'AB' => 15, // Diskon 5
            'AC' => 15, // PPN
            'AD' => 18, // NET
            'AE' => 18, // Status
            'AF' => 18, // Status Description
        ];

        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $writer = new Xlsx($spreadsheet);

        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="hasil_kalkulasi_klaim.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
