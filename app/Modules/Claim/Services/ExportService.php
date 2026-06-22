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
            'Customer Type',
            'Item Code',
            'Item Name',
            'Transaction Date',
            'Qty Kg',
            'Harga Program per Kg',
            'Diskon per Kg',
            'Total Diskon',
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
            $sheet->setCellValue("C{$rowNum}", $item->customer_type);
            $sheet->setCellValue("D{$rowNum}", $item->item_code);
            $sheet->setCellValue("E{$rowNum}", $item->item_name);
            $sheet->setCellValue("F{$rowNum}", $dateStr);
            $sheet->setCellValue("G{$rowNum}", (float)$item->qty_kg);
            $sheet->setCellValue("H{$rowNum}", (float)$item->harga_program_per_kg);
            $sheet->setCellValue("I{$rowNum}", (float)$item->diskon_per_kg);
            $sheet->setCellValue("J{$rowNum}", (float)$item->total_diskon);
            $sheet->setCellValue("K{$rowNum}", $item->status);
            $sheet->setCellValue("L{$rowNum}", $item->desc_status);

            $rowNum++;
        }

        // Set static column widths to prevent Gd requirements during font auto-sizing
        $columnWidths = [
            'A' => 15, // Customer Code
            'B' => 30, // Customer Name
            'C' => 15, // Customer Type
            'D' => 15, // Item Code
            'E' => 30, // Item Name
            'F' => 18, // Transaction Date
            'G' => 12, // Qty Kg
            'H' => 22, // Harga Program per Kg
            'I' => 15, // Diskon per Kg
            'J' => 18, // Total Diskon
            'K' => 20, // Status
            'L' => 20, // Status Description
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
