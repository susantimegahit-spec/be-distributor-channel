<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\PiSetting;
use Illuminate\Support\Facades\Storage;

class ProformaInvoicePdfGenerator
{
    protected const PAGE_WIDTH = 595.28;
    protected const PAGE_HEIGHT = 841.89;
    protected const BLUE = '0.106 0.216 0.494';
    protected const BLACK = '0.08 0.08 0.08';

    protected array $commands = [];

    protected function text(string $value, float $x, float $y, float $size = 10, array $options = []): void
    {
        $font = ($options['bold'] ?? false) ? 'F2' : 'F1';
        $color = $options['color'] ?? self::BLACK;
        $escaped = $this->escapePdfText($value);
        $this->commands[] = sprintf("BT /%s %.2f Tf %s rg %.2f %.2f Td (%s) Tj ET", $font, $size, $color, $x, $y, $escaped);
    }

    protected function rightText(string $value, float $rightX, float $y, float $size = 10, array $options = []): void
    {
        $estimatedWidth = strlen($this->normalizeText($value)) * $size * 0.48;
        $this->text($value, $rightX - $estimatedWidth, $y, $size, $options);
    }

    protected function centerText(string $value, float $centerX, float $y, float $size = 10, array $options = []): void
    {
        $estimatedWidth = strlen($this->normalizeText($value)) * $size * 0.48;
        $this->text($value, $centerX - ($estimatedWidth / 2), $y, $size, $options);
    }

    protected function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.6, string $color = self::BLACK): void
    {
        $this->commands[] = sprintf("%s RG %.2f w %.2f %.2f m %.2f %.2f l S", $color, $width, $x1, $y1, $x2, $y2);
    }

    protected function rect(float $x, float $y, float $width, float $height, array $options = []): void
    {
        $color = $options['color'] ?? self::BLACK;
        $op = ($options['fill'] ?? false) ? 'f' : 'S';
        $rg = ($options['fill'] ?? false) ? 'rg' : 'RG';
        $this->commands[] = sprintf("%s %s %.2f %.2f %.2f %.2f re %s", $color, $rg, $x, $y, $width, $height, $op);
    }

    protected function wrapText(string $value, float $x, float $y, float $maxWidth, float $size = 10, float $lineHeight = 14, array $options = []): float
    {
        $words = preg_split('/\s+/', $this->normalizeText($value));
        $lines = [];
        $currentLine = '';
        $maxChars = max((int)floor($maxWidth / ($size * 0.48)), 12);

        foreach ($words as $word) {
            $nextLine = $currentLine ? "{$currentLine} {$word}" : $word;
            if (strlen($nextLine) > $maxChars && $currentLine) {
                $lines[] = $currentLine;
                $currentLine = $word;
            } else {
                $currentLine = $nextLine;
            }
        }

        if ($currentLine) {
            $lines[] = $currentLine;
        }

        foreach ($lines as $index => $line) {
            $this->text($line, $x, $y - ($index * $lineHeight), $size, $options);
        }

        return $y - (count($lines) * $lineHeight);
    }

    protected function normalizeText(string $value): string
    {
        return preg_replace('/[^\x20-\x7E]/', '', $value);
    }

    protected function escapePdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->normalizeText($value));
    }

    protected function formatNumber($value): string
    {
        return number_format((float)$value, 0, ',', '.');
    }

    protected function formatMoney($value, bool $withRp = true): string
    {
        return ($withRp ? 'Rp ' : '') . $this->formatNumber($value) . ',-';
    }

    protected function formatDate(?string $value): string
    {
        if (!$value) return '';
        $time = strtotime($value);
        if (!$time) return '';
        
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $day = date('d', $time);
        $month = $months[(int)date('m', $time)];
        $year = date('Y', $time);

        return "{$day} {$month} {$year}";
    }

    public function generate(SalesOrder $order): string
    {
        $this->commands = [];

        $lines = $order->details;
        $subtotal = 0;
        foreach ($lines as $line) {
            $subtotal += ($line->quantity * $line->unit_price);
        }

        $discountTotal = (float)($order->discount_total ?? 0);
        if (!$discountTotal && $order->id_discount) {
            $docTotal = (float)$order->doc_total;
            if ($subtotal > $docTotal) {
                $discountTotal = $subtotal - $docTotal;
            }
        }

        $docTotal = (float)($order->doc_total ?: ($subtotal - $discountTotal));

        // Nomor: gunakan SAP Doc Number jika tersedia, fallback ke order_no
        $orderNumber = $order->sap_doc_num ?: ($order->order_no ?: '-');
        $poNumber    = $order->po_number ?: ($order->order_no ?: '-');

        $customerName    = $order->customer_name ?: ($order->distributor?->name ?? '-');
        $customerAddress = $order->address ?: ($order->distributor?->address ?? '');
        $customerCity    = $order->city ?: '';

        $docDate = $this->formatDate($order->doc_date ?: $order->created_at);
        $dueDate = $this->formatDate($order->doc_due_date);

        // Fetch dynamic signature settings
        $piSetting = PiSetting::first();
        $signerName = $piSetting ? $piSetting->signer_name : 'Kushan Wijono';
        $signaturePath = $piSetting ? $piSetting->signature_path : null;

        $hasSignature = false;
        $imgWidth = 0;
        $imgHeight = 0;
        $imgData = '';

        if ($signaturePath && Storage::disk('public')->exists($signaturePath)) {
            $fullPath = Storage::disk('public')->path($signaturePath);
            $size = @getimagesize($fullPath);
            if ($size && $size[2] === IMAGETYPE_JPEG) {
                $imgWidth = $size[0];
                $imgHeight = $size[1];
                $imgData = @file_get_contents($fullPath);
                if ($imgData !== false) {
                    $hasSignature = true;
                }
            }
        }

        // Load custom PI logo
        $hasLogo = false;
        $logoWidth = 0;
        $logoHeight = 0;
        $logoData = '';
        $logoPath = public_path('assets/customer-portal-mark.png');
        if (function_exists('imagecreatefrompng') && file_exists($logoPath)) {
            $image = @imagecreatefrompng($logoPath);
            if ($image) {
                $logoWidth = imagesx($image);
                $logoHeight = imagesy($image);
                
                ob_start();
                imagejpeg($image, null, 90);
                $logoData = ob_get_clean();
                imagedestroy($image);
                
                if ($logoData !== false && strlen($logoData) > 0) {
                    $hasLogo = true;
                }
            }
        }

        // ── HEADER PERUSAHAAN ───────────────────────────────────────
        $this->text('PT. SUSANTI MEGAH', 110, 778, 20, ['bold' => true, 'color' => self::BLUE]);
        $this->text('INDUSTRI GARAM BERIODIUM', 112, 762, 10.5, ['bold' => true, 'color' => self::BLUE]);

        if ($hasLogo) {
            $boxWidth  = 38.0;
            $boxHeight = 38.0;
            $scale          = min($boxWidth / $logoWidth, $boxHeight / $logoHeight);
            $drawLogoWidth  = $logoWidth  * $scale;
            $drawLogoHeight = $logoHeight * $scale;
            $drawLogoX      = 74 - ($drawLogoWidth  / 2);
            $drawLogoY      = 772 - ($drawLogoHeight / 2);
            $this->commands[] = sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /Img2 Do Q", $drawLogoWidth, $drawLogoHeight, $drawLogoX, $drawLogoY);
        } else {
            $this->rect(58, 757, 36, 36, ['color' => self::BLUE, 'fill' => true]);
            $this->text('SM', 66, 772, 13, ['bold' => true, 'color' => '1 1 1']);
        }

        // Alamat di kanan atas
        $this->text('Jl. Dupak Rukun No. 71-73, Surabaya 60182', 340, 778, 7.5);
        $this->text('Telp. (031) 5312526 - 5314071 - 5452765', 340, 766, 7.5);
        $this->text('Email: kapal@susantimegah.com', 340, 754, 7.5);
        $this->text('www.susantimegah.com', 340, 742, 7.5);

        // Garis bawah header
        $this->line(28, 734, 566, 734, 1.5, self::BLUE);

        // Judul dokumen
        $this->centerText('PROFORMA INVOICE', self::PAGE_WIDTH / 2, 718, 14, ['bold' => true, 'color' => self::BLUE]);
        $this->line(28, 710, 566, 710, 0.4);

        // ── NOMOR & TANGGAL ─────────────────────────────────────────
        $labelX  = 86;
        $colonX  = 154;
        $valueX  = 160;

        $this->text('Nomor',   $labelX, 695, 9.5);
        $this->text(':',       $colonX, 695, 9.5);
        $this->text($orderNumber, $valueX, 695, 9.5, ['bold' => true]);

        $this->text('No. PO',  $labelX, 682, 9.5);
        $this->text(':',       $colonX, 682, 9.5);
        $this->text($poNumber !== $orderNumber ? $poNumber : '-', $valueX, 682, 9.5);

        $this->text('Perihal', $labelX, 669, 9.5);
        $this->text(':',       $colonX, 669, 9.5);
        $this->text('Proforma Invoice', $valueX, 669, 9.5);

        $this->text("Surabaya, {$docDate}", 370, 695, 9.5);

        $this->line(28, 657, 566, 657, 0.4);

        // ── KEPADA ──────────────────────────────────────────────────
        $this->text('Kepada Yth.',    $labelX, 643, 9.5, ['bold' => true]);
        $this->text($customerName,    $labelX, 629, 10,  ['bold' => true]);
        if ($customerAddress) $this->wrapText($customerAddress, $labelX, 615, 240, 9, 12);
        if ($customerCity)    $this->text($customerCity, $labelX, 591, 9);

        // ── PEMBUKA ──────────────────────────────────────────────────
        $this->text('Dengan hormat,', $labelX, 560, 9.5);
        $this->wrapText(
            'Dengan ini kami mohon, untuk pesanan Garam Beryodium Cap Kapal untuk segera diselesaikan pembayarannya dengan rincian sebagai berikut:',
            $labelX, 530, 430, 9.5, 13
        );

        // ── HEADER TABEL ─────────────────────────────────────────────
        $tableY = 507;
        $this->line(28, $tableY, 566, $tableY, 0.6);
        $this->text('Keterangan',          90,  $tableY - 12, 9.5, ['bold' => true]);
        $this->text('Harga Satuan',        335, $tableY - 12, 9.5, ['bold' => true]);
        $this->text('Jumlah',              455, $tableY - 12, 9.5, ['bold' => true]);
        $this->line(28, $tableY - 20, 566, $tableY - 20, 0.6);

        $y = $tableY - 35;
        foreach ($lines as $index => $line) {
            $itemName = $line->item_name ?: ($line->item?->name ?? "Item " . ($index + 1));
            $qtyVal = (float)$line->quantity;
            $unit = $line->unit_msr ?: 'Bal';
            $unitPrice = (float)$line->unit_price;
            $lineTotal = $qtyVal * $unitPrice;
            if (isset($line->line_total) && $line->line_total > 0) {
                $lineTotal = (float)$line->line_total;
            }
            $description = "{$itemName} : " . $this->formatNumber($qtyVal) . " {$unit} @ " . $this->formatMoney($unitPrice) . "/{$unit}";

            // Baris 1: Nama item (bold) + harga satuan + jumlah
            $this->text($itemName, 35, $y, 9.5, ['bold' => true]);
            $this->rightText($this->formatMoney($unitPrice, false), 440, $y, 9.5);
            $this->rightText($this->formatNumber($lineTotal) . ',-', 540, $y, 9.5, ['bold' => true]);
            // Baris 2: qty dan satuan
            $this->text($this->formatNumber($qtyVal) . ' ' . $unit, 35, $y - 12, 9, ['color' => '0.35 0.35 0.35']);
            $y -= 28; // 2 baris: 14pt * 2
        }

        // ── SUBTOTAL & DISCOUNT ──────────────────────────────────────
        $this->line(28, $y + 8, 566, $y + 8, 0.5);

        if ($discountTotal > 0) {
            $this->text('Subtotal', 350, $y - 4, 9.5);
            $this->rightText('Rp ' . $this->formatNumber($subtotal) . ',-', 540, $y - 4, 9.5);
            $y -= 16;

            $this->text('Diskon', 350, $y - 4, 9.5, ['color' => '0.8 0 0']);
            $this->rightText('(Rp ' . $this->formatNumber($discountTotal) . ',-)' , 540, $y - 4, 9.5, ['color' => '0.8 0 0']);
            $y -= 16;
        }

        // Garis sebelum total
        $this->line(310, $y + 4, 566, $y + 4, 0.7);
        $this->text('TOTAL', 320, $y - 8, 10, ['bold' => true]);
        $this->rightText('Rp ' . $this->formatNumber($docTotal) . ',-', 540, $y - 8, 10.5, ['bold' => true, 'color' => self::BLUE]);
        $this->line(310, $y - 20, 566, $y - 20, 1.2, self::BLUE);

        // ── INFORMASI PEMBAYARAN ─────────────────────────────────────
        $payY = $y - 38;
        $this->wrapText(
            'Pembayaran dapat ditransfer melalui Bank Central Asia (BCA) Cabang Semut Surabaya A/C No. 256.01.0308.8 atas nama PT. Susanti Megah.',
            36, $payY, 490, 9.5, 13
        );
        $this->text('Setelah pembayaran ditransfer harap dikonfirmasikan kembali kepada kami.', 36, $payY - 32, 9.5);
        if ($dueDate) {
            $this->text('Tanggal Pengiriman:', 36, $payY - 48, 9.5, ['bold' => true]);
            $this->text($dueDate, 155, $payY - 48, 9.5);
        }
        $this->text('Demikianlah, atas perhatian serta kerjasama yang baik kami ucapkan terima kasih.', 36, $payY - 64, 9.5);

        // ── TANDA TANGAN ────────────────────────────────────────────
        $this->text('Hormat kami,', 375, 212, 9.5);
        $this->text('PT. SUSANTI MEGAH', 344, 196, 9.5, ['bold' => true, 'color' => self::BLUE]);

        if ($hasSignature) {
            $boxWidth  = 100.0;
            $boxHeight = 42.0;
            $scale      = min($boxWidth / $imgWidth, $boxHeight / $imgHeight);
            $drawWidth  = $imgWidth  * $scale;
            $drawHeight = $imgHeight * $scale;
            $drawX      = 417.5 - ($drawWidth  / 2);
            $drawY      = 165    - ($drawHeight / 2);
            $this->commands[] = sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /Img1 Do Q", $drawWidth, $drawHeight, $drawX, $drawY);
        }

        $this->line(348, 144, 488, 144, 0.5);
        $this->centerText($signerName, 418, 131, 9.5, ['bold' => true]);
        $this->centerText('PT. Susanti Megah', 418, 119, 8.5);

        // ── FOOTER ──────────────────────────────────────────────────
        $this->line(28, 80, 566, 80, 1.2, self::BLUE);
        $this->text('Branch Factory', 32, 60, 6.8, ['bold' => true, 'color' => self::BLUE]);
        $this->text('Jl. Raya Serang Km. 32-33, Tangerang 15610', 32, 49, 6.5);
        $this->text('Marketing Lounge Garam Cap Kapal', 330, 60, 6.8, ['bold' => true, 'color' => self::BLUE]);
        $this->text('Lt. UG - Golden City Mall, Jl. KH Abdul Wahab Siamin No.2-8, Surabaya 60225', 330, 49, 6.0);
        $this->centerText('http://www.susantimegah.com', self::PAGE_WIDTH / 2, 31, 7, ['bold' => true, 'color' => self::BLUE]);

        $content = implode("\n", $this->commands);

        // Assign object IDs dynamically
        $nextObjId = 7;
        $sigObjId = null;
        $logoObjId = null;

        if ($hasSignature) {
            $sigObjId = $nextObjId++;
        }
        if ($hasLogo) {
            $logoObjId = $nextObjId++;
        }

        $pageResources = '<< /Font << /F1 4 0 R /F2 5 0 R >>';
        if ($hasSignature || $hasLogo) {
            $pageResources .= ' /XObject <<';
            if ($hasSignature) {
                $pageResources .= " /Img1 {$sigObjId} 0 R";
            }
            if ($hasLogo) {
                $pageResources .= " /Img2 {$logoObjId} 0 R";
            }
            $pageResources .= ' >>';
        }
        $pageResources .= ' >>';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources %s /Contents 6 0 R >>', self::PAGE_WIDTH, self::PAGE_HEIGHT, $pageResources),
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content)
        ];

        if ($hasSignature) {
            $objects[] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $imgWidth,
                $imgHeight,
                strlen($imgData),
                $imgData
            );
        }

        if ($hasLogo) {
            $objects[] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $logoWidth,
                $logoHeight,
                strlen($logoData),
                $logoData
            );
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $index + 1, $object);
        }

        $xrefOffset = strlen($pdf);
        $pdf .= sprintf("xref\n0 %d\n0000000000 65535 f \n", count($objects) + 1);
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF", count($objects) + 1, $xrefOffset);

        return $pdf;
    }
}
