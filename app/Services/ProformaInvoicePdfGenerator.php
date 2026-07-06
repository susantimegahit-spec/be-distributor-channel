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
        $orderNumber = $order->order_no ?: '-';
        $poNumber = $order->po_number ?: $orderNumber;
        
        $customerName = $order->customer_name ?: ($order->distributor?->name ?? '-');
        $customerAddress = $order->address ?: ($order->distributor?->address ?? '');
        $customerCity = $order->city ?: '';
        
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

        $this->text('PT. SUSANTI MEGAH', 110, 775, 21, ['bold' => true, 'color' => self::BLUE]);
        $this->text('INDUSTRI GARAM BERIODIUM', 112, 758, 11, ['bold' => true, 'color' => self::BLUE]);

        if ($hasLogo) {
            $boxWidth = 36.0;
            $boxHeight = 36.0;
            $scale = min($boxWidth / $logoWidth, $boxHeight / $logoHeight);
            $drawLogoWidth = $logoWidth * $scale;
            $drawLogoHeight = $logoHeight * $scale;
            $drawLogoX = 79 - ($drawLogoWidth / 2);
            $drawLogoY = 773 - ($drawLogoHeight / 2);
            
            $this->commands[] = sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /Img2 Do Q", $drawLogoWidth, $drawLogoHeight, $drawLogoX, $drawLogoY);
        } else {
            $this->rect(62, 756, 34, 34, ['color' => self::BLUE]);
            $this->text('SM', 70, 770, 12, ['bold' => true, 'color' => '1 1 1']);
        }

        $this->text('Jl. Dupak Rukun No. 71-73, Surabaya 60182', 365, 776, 7.5);
        $this->text('T. (031) 5312526 - 5314071 - 5452765', 365, 764, 7.5);
        $this->text('email. kapal@susantimegah.com', 365, 752, 7.5);

        $this->text("Nomor  : {$orderNumber}", 86, 710, 9.5);
        $this->text('Perihal : Proforma Invoice', 86, 697, 9.5);
        $this->text("Surabaya, {$docDate}", 380, 710, 9.5);

        $this->text('Kepada:', 86, 648, 9.5);
        $this->text($customerName, 86, 634, 10, ['bold' => true]);
        if ($customerAddress) $this->wrapText($customerAddress, 86, 620, 230, 9, 12);
        if ($customerCity) $this->text($customerCity, 86, 596, 9);

        $this->text('Dengan hormat,', 86, 555, 9.5);
        $this->wrapText(
            'Dengan ini kami mohon, untuk pesanan Garam Beryodium Cap Kapal untuk segera diselesaikan pembayarannya dengan rincian sebagai berikut:',
            86,
            520,
            430,
            9.5,
            13
        );

        $y = 470;
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

            $this->text($description, 86, $y, 9.5);
            $this->text('= Rp', 382, $y, 9.5, ['bold' => true]);
            $this->rightText($this->formatNumber($lineTotal) . ',-', 492, $y, 9.5, ['bold' => true]);
            $y -= 14;
        }

        if ($discountTotal > 0) {
            $this->text('Discount', 260, $y, 9.5, ['bold' => true]);
            $this->text('= Rp', 382, $y, 9.5, ['bold' => true]);
            $this->rightText('(' . $this->formatNumber($discountTotal) . '),-', 492, $y, 9.5, ['bold' => true]);
            $y -= 14;
        }

        $this->line(382, $y + 6, 492, $y + 6, 0.7);
        $this->text('Total', 300, $y - 4, 9.5, ['bold' => true]);
        $this->text('=Rp', 382, $y - 4, 9.5, ['bold' => true]);
        $this->rightText($this->formatNumber($docTotal) . ',-', 492, $y - 4, 9.5, ['bold' => true]);

        $this->wrapText(
            'Pembayaran dapat ditransfer melalui Bank Central Asia Cabang Semut Surabaya A/C No. 256.01.0308.8 atas nama PT. Susanti Megah.',
            86,
            345,
            430,
            9.5,
            13
        );
        $this->text('Setelah pembayaran ditransfer harap dikonfirmasikan kembali kepada kami.', 86, 305, 9.5);
        if ($dueDate) $this->text("Tanggal request kirim: {$dueDate}", 86, 288, 9.5);
        $this->text('Demikianlah, atas perhatian serta kerjasama yang baik kami ucapkan terima kasih.', 86, 270, 9.5);

        $this->text('Hormat kami,', 385, 210, 9.5);
        $this->text('PT. SUSANTI MEGAH', 350, 185, 9.5, ['bold' => true, 'color' => self::BLUE]);
        
        // Render digital signature if loaded successfully
        if ($hasSignature) {
            $boxWidth = 90.0;
            $boxHeight = 38.0;
            $scale = min($boxWidth / $imgWidth, $boxHeight / $imgHeight);
            $drawWidth = $imgWidth * $scale;
            $drawHeight = $imgHeight * $scale;
            $drawX = 417.5 - ($drawWidth / 2); // Center horizontally aligned with center of line
            $drawY = 163 - ($drawHeight / 2);  // Center vertically between 185 and 142
            
            $this->commands[] = sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /Img1 Do Q", $drawWidth, $drawHeight, $drawX, $drawY);
        }

        $this->line(360, 142, 475, 142, 0.5);
        $this->centerText($signerName, 417.5, 128, 9.5);

        $this->line(28, 74, 566, 74, 1.2, self::BLUE);
        $this->text('Branch Factory', 32, 54, 6.8, ['bold' => true, 'color' => self::BLUE]);
        $this->text('Jl. Raya Serang Km. 32-33, Tangerang 15610', 32, 43, 6.5);
        $this->text('Marketing Lounge Garam Cap Kapal', 374, 54, 6.8, ['bold' => true, 'color' => self::BLUE]);
        $this->text('Lt. UG - Golden City Mall, Jl. KH Abdul Wahab Siamin No.2-8, Surabaya 60225', 374, 43, 6.2);
        $this->text('http://www.susantimegah.com', 242, 25, 6.5, ['bold' => true, 'color' => self::BLUE]);

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
