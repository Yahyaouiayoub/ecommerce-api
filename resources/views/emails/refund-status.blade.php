<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #171717;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 560px;
            margin: 0 auto;
            padding: 32px 24px;
        }
        .header {
            border-bottom: 1px solid #e5e5e5;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .header h1 {
            font-size: 20px;
            font-weight: 600;
            color: #171717;
            margin: 0 0 4px 0;
        }
        .header p {
            font-size: 13px;
            color: #737373;
            margin: 0;
        }
        .body-text {
            font-size: 14px;
            color: #404040;
            margin-bottom: 24px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #dbeafe; color: #1e40af; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .details {
            background: #fafafa;
            border: 1px solid #e5e5e5;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .details dt {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #a3a3a3;
            margin-bottom: 2px;
        }
        .details dd {
            font-size: 14px;
            color: #171717;
            margin: 0 0 10px 0;
        }
        .details dd:last-child { margin-bottom: 0; }
        .cta {
            text-align: center;
            margin-bottom: 24px;
        }
        .cta a {
            display: inline-block;
            padding: 10px 24px;
            background: #171717;
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border-radius: 4px;
        }
        .footer {
            border-top: 1px solid #e5e5e5;
            padding-top: 16px;
            font-size: 12px;
            color: #a3a3a3;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $companyName }}</h1>
            <p>Refund {{ $refund->refund_number }}</p>
        </div>

        <p class="body-text">Dear {{ $refund->requester_name }},</p>

        <p class="body-text">
            Your refund request <strong>{{ $refund->refund_number }}</strong> for order
            <strong>{{ $refund->order->order_number ?? '#' . $refund->order_id }}</strong>
            has been <strong>{{ strtolower($refund->status_label) }}</strong>.
        </p>

        <div style="text-align:center; margin-bottom: 24px;">
            <span class="status-badge status-{{ $refund->status }}">
                {{ $refund->status_label }}
            </span>
        </div>

        <dl class="details">
            <dt>Refund</dt>
            <dd>{{ $refund->refund_number }}</dd>

            <dt>Order</dt>
            <dd>{{ $refund->order->order_number ?? '#' . $refund->order_id }}</dd>

            <dt>Amount</dt>
            <dd>{{ $refund->refund_amount_formatted }}</dd>

            <dt>Reason</dt>
            <dd>{{ $refund->reason ?? 'Not specified' }}</dd>

            <dt>Status</dt>
            <dd>{{ $refund->status_label }}</dd>
        </dl>

        <div class="cta">
            <a href="{{ $refundUrl }}">View Refund Details</a>
        </div>

        <p class="body-text">
            If you have any questions about this refund, please don't hesitate to contact us.
        </p>

        <div class="footer">
            <p>{{ $companyName }}</p>
            <p>Thank you for your patience!</p>
        </div>
    </div>
</body>
</html>
