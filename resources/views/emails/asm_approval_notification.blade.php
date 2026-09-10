<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Persetujuan Sales Order</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f6f9fc;
            margin: 0;
            padding: 20px;
            color: #333333;
        }
        .container {
            max-width: 600px;
            background-color: #ffffff;
            margin: 0 auto;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e1e8ed;
        }
        .header {
            background-color: #2b5c8f;
            padding: 30px;
            text-align: center;
            color: #ffffff;
        }
        .header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .info-table td {
            padding: 8px 0;
            border-bottom: 1px solid #f0f4f8;
            font-size: 14px;
        }
        .info-table td.label {
            font-weight: bold;
            color: #666666;
            width: 35%;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #f0f4f8;
            padding: 10px;
            font-size: 13px;
            text-align: left;
            font-weight: 600;
        }
        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e1e8ed;
            font-size: 13px;
        }
        .actions {
            text-align: center;
            margin-top: 30px;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            margin: 0 10px;
        }
        .btn-approve {
            background-color: #2e7d32;
            color: #ffffff !important;
        }
        .btn-reject {
            background-color: #c62828;
            color: #ffffff !important;
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #888888;
            border-top: 1px solid #e1e8ed;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Persetujuan Sales Order</h2>
        </div>
        <div class="content">
            <p>Halo Area Sales Manager,</p>
            <p>Terdapat pengajuan Sales Order baru yang memerlukan persetujuan Anda:</p>

            <table class="info-table">
                <tr>
                    <td class="label">Nomor Order</td>
                    <td>{{ $salesOrder->order_no }}</td>
                </tr>
                <tr>
                    <td class="label">Customer</td>
                    <td>{{ $salesOrder->customer_name }} ({{ $salesOrder->card_code }})</td>
                </tr>
                <tr>
                    <td class="label">Tanggal Dokumen</td>
                    <td>{{ $salesOrder->doc_date->format('d F Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Total Order</td>
                    <td style="font-weight: bold; color: #2b5c8f;">Rp {{ number_format($salesOrder->doc_total, 2, ',', '.') }}</td>
                </tr>
            </table>

            <h3 style="font-size: 15px; border-bottom: 2px solid #2b5c8f; padding-bottom: 5px; margin-bottom: 15px;">Daftar Item</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Kuantitas</th>
                        <th>Harga Satuan</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($salesOrder->details as $detail)
                        <tr>
                            <td>{{ $detail->item_code }}</td>
                            <td>{{ number_format($detail->quantity, 2) }} {{ $detail->unit_msr }}</td>
                            <td>Rp {{ number_format($detail->unit_price, 2, ',', '.') }}</td>
                            <td>Rp {{ number_format($detail->line_total, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="actions">
                <a href="{{ $approveUrl }}" class="btn btn-approve">Setujui Order</a>
                <a href="{{ $rejectUrl }}" class="btn btn-reject">Tolak Order</a>
            </div>
            
            <p style="font-size: 12px; color: #666666; text-align: center; margin-top: 20px;">
                *Link persetujuan di atas berlaku selama 3 hari. Anda dapat melakukan persetujuan instan tanpa perlu masuk ke dalam aplikasi web.
            </p>
        </div>
        <div class="footer">
            Sistem Distributor Channel - PT Susanti Megah
        </div>
    </div>
</body>
</html>
