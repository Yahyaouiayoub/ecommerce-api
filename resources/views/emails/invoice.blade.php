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
        .details dd:last-child {
            margin-bottom: 0;
        }
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
            <p>Invoice {{ $invoice->invoice_number }}</p>
        </div>

        <p class="body-text">Dear {{ $invoice->billing_name ?? 'Customer' }},</p>

        <p class="body-text">
            Please find attached your invoice ({{ $invoice->invoice_number }}) for your recent order.
            The invoice is also available in your account dashboard for your convenience.
        </p>

        <dl class="details">
            <dt>Invoice</dt>
            <dd>{{ $invoice->invoice_number }}</dd>

            <dt>Order</dt>
            <dd>{{ $invoice->order->order_number ?? 'N/A' }}</dd>

            <dt>Total</dt>
            <dd>{{ number_format($invoice->total_amount, 2) }} MAD</dd>

            <dt>Status</dt>
            <dd>{{ $invoice->status_label }}</dd>

            @if($invoice->due_date)
            <dt>Due Date</dt>
            <dd>{{ $invoice->due_date->format('F d, Y') }}</dd>
            @endif
        </dl>

        <div class="cta">
            <a href="{{ url('/invoices/' . $invoice->id) }}">View Invoice Online</a>
        </div>

        <p class="body-text">
            If you have any questions about this invoice, please don't hesitate to contact us.
        </p>

        <div class="footer">
            <p>{{ $companyName }}</p>
            <p>Thank you for your business!</p>
        </div>
    </div>
</body>
</html>
