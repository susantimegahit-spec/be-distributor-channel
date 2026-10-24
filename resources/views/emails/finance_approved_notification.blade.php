<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order Disetujui oleh Finance</title>
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
            background-color: #2e7d32;
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
            line-height: 1.6;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
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
            <h2>Order Disetujui</h2>
        </div>
        <div class="content">
            <p>Halo,</p>
            <p>Kami ingin menginformasikan bahwa Order Anda telah berhasil disetujui oleh tim Finance. Berikut adalah rincian order tersebut:</p>
            
            <table class="info-table">
                <tr>
                    <td class="label">No. Order</td>
                    <td>{{ $salesOrder->order_no }}</td>
                </tr>
                <tr>
                    <td class="label">No. PO</td>
                    <td>{{ $salesOrder->po_number }}</td>
                </tr>
                <tr>
                    <td class="label">Total Nilai</td>
                    <td>Rp {{ number_format($salesOrder->doc_total, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="label">Status</td>
                    <td><strong>ORDER APPROVED</strong></td>
                </tr>
            </table>

            <p>Dokumen pendukung / lampiran yang diunggah saat pembuatan order ini telah kami sertakan sebagai lampiran email ini.</p>
            <p>Terima kasih atas kerja sama Anda.</p>
        </div>
        <div class="footer">
            <p>Email ini dikirim secara otomatis oleh sistem PT SUSANTI. Harap tidak membalas email ini.</p>
        </div>
    </div>
</body>
</html>
