<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Registration Application Update</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 24px;
            color: #1f2937;
        }
        .container {
            max-width: 600px;
            background-color: #ffffff;
            margin: 0 auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }
        .header {
            background: linear-gradient(135deg, #be123c 0%, #9f1239 100%);
            padding: 32px 24px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .header p {
            margin: 8px 0 0 0;
            font-size: 14px;
            color: #fecdd3;
        }
        .badge {
            display: inline-block;
            background-color: #e11d48;
            color: #ffffff;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 9999px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .content {
            padding: 32px 28px;
            line-height: 1.6;
            font-size: 15px;
        }
        .vendor-badge {
            background-color: #fff1f2;
            border: 1px solid #fecdd3;
            color: #9f1239;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
        }
        .reason-card {
            background-color: #fff5f5;
            border-left: 4px solid #e11d48;
            border-radius: 6px;
            padding: 18px 20px;
            margin: 24px 0;
        }
        .reason-card h3 {
            margin: 0 0 8px 0;
            font-size: 15px;
            color: #9f1239;
            font-weight: 700;
        }
        .reason-text {
            margin: 0;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-line;
        }
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px 20px;
            margin: 24px 0;
        }
        .info-card h4 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            width: 40%;
            color: #64748b;
            font-weight: 600;
        }
        .info-value {
            width: 60%;
            color: #0f172a;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            word-break: break-all;
        }
        .footer {
            background-color: #f9fafb;
            padding: 24px;
            text-align: center;
            font-size: 13px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            margin: 4px 0;
        }
        .contact-box {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 14px 16px;
            margin-top: 24px;
            font-size: 13px;
            color: #0369a1;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <span class="badge">Registration Review</span>
            <h1>PT Susanti Megah</h1>
            <p>Vendor Management & Procurement Channel (SMESTA)</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p>Dear <strong>{{ $vendor->company_name }}</strong> Team,</p>

            <p>Thank you for your interest in establishing a business partnership with PT Susanti Megah and submitting your vendor registration application.</p>

            <div class="vendor-badge">
                <strong>Vendor Application Code:</strong> {{ $vendor->vendor_code }}<br>
                <strong>Company Legal Name:</strong> {{ $vendor->company_name }}
            </div>

            <p>After a thorough review and assessment conducted by our Legal & Compliance Team, we regret to inform you that we are <strong>unable to approve your vendor registration application</strong> at this time.</p>

            <!-- Rejection Reason Card -->
            <div class="reason-card">
                <h3>Reason for Rejection:</h3>
                <p class="reason-text">{{ $rejectionReason }}</p>
            </div>

            <!-- Application Summary Card -->
            <div class="info-card">
                <h4>Application Details</h4>
                <div class="info-row">
                    <span class="info-label">Vendor Code:</span>
                    <span class="info-value">{{ $vendor->vendor_code }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Vendor Type:</span>
                    <span class="info-value">{{ $vendor->vendor_type }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">PIC Contact:</span>
                    <span class="info-value">{{ $vendor->pic_name }} ({{ $vendor->pic_phone }})</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Decision Status:</span>
                    <span class="info-value" style="color: #e11d48; font-weight: 700;">REJECTED</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Review Date:</span>
                    <span class="info-value">{{ now()->setTimezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</span>
                </div>
            </div>

            <p>If you believe there was an administrative misunderstanding or if you would like to provide amended legal documentation for further consideration, please reach out to our team.</p>

            <div class="contact-box">
                <strong>Need Further Information?</strong><br>
                Please contact our Procurement & Legal Department via email at <a href="mailto:procurement@susantimegah.com" style="color: #0284c7; text-decoration: underline;">procurement@susantimegah.com</a> or telephone at (031) 8431000 quoting your Vendor Application Code: <strong>{{ $vendor->vendor_code }}</strong>.
            </div>

            <p style="margin-top: 28px;">
                Sincerely,<br>
                <strong>Legal & Procurement Committee</strong><br>
                PT Susanti Megah
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>PT Susanti Megah</strong></p>
            <p>Jl. Dupak Rukun No. 71-73, Asemrowo, Surabaya, Jawa Timur 60182</p>
            <p style="margin-top: 12px; font-size: 11px; color: #9ca3af;">
                This is an automated notification generated by SMESTA Vendor Management System. Please do not reply directly to this email address.
            </p>
        </div>
    </div>
</body>
</html>
