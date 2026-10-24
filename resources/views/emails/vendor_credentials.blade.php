<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Partnership Approved & Portal Credentials</title>
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
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
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
            color: #bbf7d0;
        }
        .badge {
            display: inline-block;
            background-color: #22c55e;
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
            background-color: #f0fdf4;
            border: 1px solid #86efac;
            color: #166534;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
        }
        .credentials-card {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 20px;
            margin: 24px 0;
        }
        .credentials-card h3 {
            margin: 0 0 14px 0;
            font-size: 16px;
            color: #0f172a;
            display: flex;
            align-items: center;
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
            width: 38%;
            color: #64748b;
            font-weight: 600;
        }
        .info-value {
            width: 62%;
            color: #0f172a;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            word-break: break-all;
        }
        .password-highlight {
            font-weight: 700;
            color: #1e40af;
            background-color: #dbeafe;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .btn-container {
            text-align: center;
            margin: 32px 0 20px 0;
        }
        .btn {
            background-color: #16a34a;
            color: #ffffff !important;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            padding: 14px 32px;
            border-radius: 8px;
            display: inline-block;
            box-shadow: 0 2px 6px rgba(22, 163, 74, 0.3);
        }
        .notice {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 14px;
            border-radius: 4px;
            margin: 24px 0 10px 0;
            font-size: 13px;
            color: #92400e;
            line-height: 1.5;
        }
        .footer {
            background-color: #f9fafb;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="badge">Partnership Approved</span>
            <h1>PT SUSANTI MEGAH</h1>
            <p>SMESTA Vendor & Logistics Partner Portal</p>
        </div>

        <div class="content">
            <p>Dear <strong>{{ $vendor->pic_name }}</strong>,</p>
            <p>
                Congratulations! Your vendor partnership registration on behalf of <strong>{{ $vendor->company_name }}</strong> has been officially <strong>APPROVED</strong> by the Legal Team of PT Susanti Megah.
            </p>

            <div class="vendor-badge">
                <strong>Vendor Partner Code:</strong> <span style="font-family: monospace; font-weight: bold;">{{ $vendor->vendor_code }}</span><br>
                <strong>Partner Type:</strong> {{ $vendor->vendor_type }}<br>
                <strong>Approval Date:</strong> {{ now()->format('F d, Y, H:i') }}
            </div>

            <p>
                Your Vendor Portal account has been successfully activated. Please use the following credentials to access the portal:
            </p>

            <div class="credentials-card">
                <h3>🔑 Vendor Portal Login Credentials</h3>
                <div class="info-row">
                    <div class="info-label">Portal URL</div>
                    <div class="info-value">
                        <a href="{{ $loginUrl }}" style="color: #2563eb; text-decoration: underline;">{{ $loginUrl }}</a>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email Address</div>
                    <div class="info-value">{{ $vendorUser->email }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Temporary Password</div>
                    <div class="info-value"><span class="password-highlight">{{ $plainPassword }}</span></div>
                </div>
            </div>

            <div class="btn-container">
                <a href="{{ $loginUrl }}" class="btn" target="_blank">
                    Log In to Vendor Portal &rarr;
                </a>
            </div>

            <div class="notice">
                <strong>⚠️ Security Notice:</strong>
                The password provided above is temporary. For your account security, you will be prompted to create a new password upon your first successful login to the Vendor Portal. Please keep these credentials secure and do not share them with unauthorized personnel.
            </div>

            <p style="margin-top: 24px; font-size: 14px; color: #4b5563;">
                If you have any questions or require assistance accessing your portal account, please reach out to the official PT Susanti Megah operational support team.
            </p>
        </div>

        <div class="footer">
            <p><strong>PT Susanti Megah</strong></p>
            <p>Jl. Dupak Rukun No. 71-73, Asemrowo, Surabaya, Jawa Timur 60182</p>
            <p style="margin-top: 10px;">
                This is an automated notification from the <strong>SMESTA (Enterprise Management System)</strong> of PT Susanti Megah.<br>
                &copy; {{ date('Y') }} PT Susanti Megah. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
