<?php

namespace App\Modules\Claim\Services;

use App\Modules\Item\Repositories\ItemRepositoryInterface;

class ClaimService
{
    protected ItemRepositoryInterface $itemRepository;

    /**
     * ClaimService constructor.
     *
     * @param  ItemRepositoryInterface  $itemRepository
     */
    public function __construct(ItemRepositoryInterface $itemRepository)
    {
        $this->itemRepository = $itemRepository;
    }

    /**
     * Generate Excel template in XML SpreadsheetML format with 2 sheets.
     *
     * @return string
     */
    public function generateTemplateExcel(): string
    {
        $items = $this->itemRepository->getAll();

        $xml = '<?xml version="1.0"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        
        $xml .= ' <Styles>' . "\n";
        $xml .= '  <Style ss:ID="Default" ss:Name="Normal">' . "\n";
        $xml .= '   <Alignment ss:Vertical="Bottom"/>' . "\n";
        $xml .= '   <Font ss:FontName="Segoe UI" ss:Size="11" ss:Color="#000000"/>' . "\n";
        $xml .= '  </Style>' . "\n";
        
        // Title Style (Dark Blue, Bold, 16pt)
        $xml .= '  <Style ss:ID="Title">' . "\n";
        $xml .= '   <Font ss:FontName="Segoe UI" ss:Size="16" ss:Bold="1" ss:Color="#1E3A8A"/>' . "\n";
        $xml .= '   <Alignment ss:Vertical="Center"/>' . "\n";
        $xml .= '  </Style>' . "\n";
        
        // Header Style (Teal background, white text, bold, centered)
        $xml .= '  <Style ss:ID="Header">' . "\n";
        $xml .= '   <Font ss:FontName="Segoe UI" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>' . "\n";
        $xml .= '   <Interior ss:Color="#0D9488" ss:Pattern="Solid"/>' . "\n";
        $xml .= '   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . "\n";
        $xml .= '   <Borders>' . "\n";
        $xml .= '    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '   </Borders>' . "\n";
        $xml .= '  </Style>' . "\n";
        
        // Bordered Style for data rows
        $xml .= '  <Style ss:ID="Bordered">' . "\n";
        $xml .= '   <Borders>' . "\n";
        $xml .= '    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/>' . "\n";
        $xml .= '    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/>' . "\n";
        $xml .= '    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/>' . "\n";
        $xml .= '    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E7EB"/>' . "\n";
        $xml .= '   </Borders>' . "\n";
        $xml .= '  </Style>' . "\n";
        
        // Number alignment/formatting
        $xml .= '  <Style ss:ID="Numeric" ss:Parent="Bordered">' . "\n";
        $xml .= '   <Alignment ss:Horizontal="Right"/>' . "\n";
        $xml .= '   <NumberFormat ss:Format="#,##0.00"/>' . "\n";
        $xml .= '  </Style>' . "\n";
        $xml .= '  <Style ss:ID="Integer" ss:Parent="Bordered">' . "\n";
        $xml .= '   <Alignment ss:Horizontal="Right"/>' . "\n";
        $xml .= '   <NumberFormat ss:Format="#,##0"/>' . "\n";
        $xml .= '  </Style>' . "\n";
        $xml .= ' </Styles>' . "\n";

        // SHEET 1: template upload klaim
        $xml .= ' <Worksheet ss:Name="template upload klaim">' . "\n";
        $xml .= '  <Table ss:DefaultRowHeight="18">' . "\n";
        // Define Column Widths
        $xml .= '   <Column ss:Width="120"/>' . "\n"; // Kode Distributor
        $xml .= '   <Column ss:Width="180"/>' . "\n"; // Nama Distributor
        $xml .= '   <Column ss:Width="100"/>' . "\n"; // Item
        $xml .= '   <Column ss:Width="200"/>' . "\n"; // Nama Item
        $xml .= '   <Column ss:Width="110"/>' . "\n"; // Harga Jual @Kg
        $xml .= '   <Column ss:Width="100"/>' . "\n"; // Qty @Kg
        $xml .= '   <Column ss:Width="110"/>' . "\n"; // Type Customer
        $xml .= '   <Column ss:Width="130"/>' . "\n"; // Transaction Date
        
        // Title Row
        $xml .= '   <Row ss:Height="40">' . "\n";
        $xml .= '    <Cell ss:MergeAcross="7" ss:StyleID="Title"><Data ss:Type="String">  TEMPLATE UPLOAD KLAIM DISTRIBUTOR</Data></Cell>' . "\n";
        $xml .= '   </Row>' . "\n";
        
        // Empty Spacer Row
        $xml .= '   <Row ss:Height="10"></Row>' . "\n";

        // Header Row
        $xml .= '   <Row ss:Height="26" ss:StyleID="Header">' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">Kode Distributor</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">Nama Distributor</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">Item</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">Nama Item</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">Harga Jual @Kg</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">Qty @Kg</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">Type Customer</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">Transaction Date</Data></Cell>' . "\n";
        $xml .= '   </Row>' . "\n";

        // Add 1 example row (Bordered)
        $xml .= '   <Row ss:Height="20">' . "\n";
        $xml .= '    <Cell ss:StyleID="Bordered"><Data ss:Type="String">C110003074</Data></Cell>' . "\n";
        $xml .= '    <Cell ss:StyleID="Bordered"><Data ss:Type="String">LESAFFRE SARI</Data></Cell>' . "\n";
        $xml .= '    <Cell ss:StyleID="Bordered"><Data ss:Type="String">E65</Data></Cell>' . "\n";
        $xml .= '    <Cell ss:StyleID="Bordered"><Data ss:Type="String">TOP 250 M @ 10 KG / BAL</Data></Cell>' . "\n";
        $xml .= '    <Cell ss:StyleID="Numeric"><Data ss:Type="Number">124500.00</Data></Cell>' . "\n";
        $xml .= '    <Cell ss:StyleID="Integer"><Data ss:Type="Number">150</Data></Cell>' . "\n";
        $xml .= '    <Cell ss:StyleID="Bordered"><Data ss:Type="String">Distributor</Data></Cell>' . "\n";
        $xml .= '    <Cell ss:StyleID="Bordered"><Data ss:Type="String">' . date('Y-m-d') . '</Data></Cell>' . "\n";
        $xml .= '   </Row>' . "\n";

        $xml .= '  </Table>' . "\n";
        $xml .= ' </Worksheet>' . "\n";

        // SHEET 2: master data item
        $xml .= ' <Worksheet ss:Name="master data item">' . "\n";
        $xml .= '  <Table ss:DefaultRowHeight="18">' . "\n";
        // Column Widths
        $xml .= '   <Column ss:Width="100"/>' . "\n"; // Item Code
        $xml .= '   <Column ss:Width="250"/>' . "\n"; // Item Name
        $xml .= '   <Column ss:Width="80"/>' . "\n";  // Uom Entry
        $xml .= '   <Column ss:Width="100"/>' . "\n"; // Unit Msr
        $xml .= '   <Column ss:Width="80"/>' . "\n";  // Status
        
        // Title Row
        $xml .= '   <Row ss:Height="40">' . "\n";
        $xml .= '    <Cell ss:MergeAcross="4" ss:StyleID="Title"><Data ss:Type="String">  MASTER DATA ITEM</Data></Cell>' . "\n";
        $xml .= '   </Row>' . "\n";
        
        // Spacer Row
        $xml .= '   <Row ss:Height="10"></Row>' . "\n";

        // Header Row
        $xml .= '   <Row ss:Height="26" ss:StyleID="Header">' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">Item Code</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">Item Name</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">Uom Entry</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">Unit Msr</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">Status</Data></Cell>' . "\n";
        $xml .= '   </Row>' . "\n";

        // Write Dynamic Item Rows
        foreach ($items as $item) {
            $statusText = $item->status === 1 ? 'Aktif' : 'Non-Aktif';
            
            // Clean XML special characters
            $itemCode = htmlspecialchars($item->item_code, ENT_XML1, 'UTF-8');
            $itemName = htmlspecialchars($item->item_name, ENT_XML1, 'UTF-8');
            $salUnitMsr = htmlspecialchars($item->sal_unit_msr ?? 'Kg', ENT_XML1, 'UTF-8');
            
            $xml .= '   <Row ss:Height="20">' . "\n";
            $xml .= '    <Cell ss:StyleID="Bordered"><Data ss:Type="String">' . $itemCode . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="Bordered"><Data ss:Type="String">' . $itemName . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="Integer"><Data ss:Type="Number">' . (int)$item->suom_entry . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="Bordered"><Data ss:Type="String">' . $salUnitMsr . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="Bordered"><Data ss:Type="String">' . $statusText . '</Data></Cell>' . "\n";
            $xml .= '   </Row>' . "\n";
        }

        $xml .= '  </Table>' . "\n";
        $xml .= ' </Worksheet>' . "\n";
        
        $xml .= '</Workbook>';

        return $xml;
    }
}
