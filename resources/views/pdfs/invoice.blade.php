<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 32px 40px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.5;
            color: #171717;
            background: #fff;
        }

        /* ===== HEADER ===== */
        .header {
            border-bottom: 1px solid #e5e5e5;
            padding-bottom: 24px;
            margin-bottom: 24px;
            overflow: hidden;
        }
        .header-left {
            float: left;
        }
        .header-left .company-name {
            font-size: 16px;
            font-weight: 600;
            color: #262626;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }
        .header-left p {
            font-size: 9px;
            color: #a3a3a3;
            line-height: 1.6;
        }
        .header-right {
            float: right;
            text-align: right;
        }
        .header-right h1 {
            font-size: 26px;
            font-weight: 300;
            letter-spacing: 0.15em;
            color: #171717;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .header-right .invoice-number {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 12px;
            color: #525252;
        }
        .clearfix { clear: both; }

        /* ===== STATUS BADGE ===== */
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border: 1px solid #e5e5e5;
            color: #525252;
        }
        .status-badge.paid {
            background: #171717;
            color: #fff;
            border-color: #171717;
        }
        .status-badge.cancelled,
        .status-badge.refunded {
            text-decoration: line-through;
            color: #a3a3a3;
            border-color: #e5e5e5;
        }

        /* ===== SECTION ===== */
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #a3a3a3;
            margin-bottom: 8px;
        }

        /* ===== METADATA ===== */
        .metadata-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .metadata-table td {
            vertical-align: top;
            padding: 2px 16px 2px 0;
            font-size: 10px;
        }
        .metadata-table .label {
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #a3a3a3;
            padding-bottom: 2px;
        }
        .metadata-table .mono {
            font-family: 'DejaVu Sans Mono', monospace;
        }

        /* ===== BILL TO + AMOUNT ===== */
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .info-grid td {
            vertical-align: top;
            width: 50%;
            padding-bottom: 8px;
        }
        .info-grid .right {
            text-align: right;
        }
        .info-grid .name {
            font-weight: 600;
            color: #171717;
            font-size: 11px;
            margin-bottom: 2px;
        }
        .info-grid .detail {
            color: #737373;
            font-size: 10px;
            line-height: 1.6;
        }
        .info-grid .amount-total {
            font-size: 18px;
            font-weight: 600;
            color: #171717;
        }
        .info-grid .due-date {
            font-size: 9px;
            color: #a3a3a3;
        }

        /* ===== PRODUCTS TABLE ===== */
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.items th {
            border-bottom: 1px solid #e5e5e5;
            padding: 6px 8px;
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #a3a3a3;
            text-align: left;
        }
        table.items th.text-right {
            text-align: right;
        }
        table.items th.text-center {
            text-align: center;
        }
        table.items td {
            border-bottom: 1px solid #f0f0f0;
            padding: 7px 8px;
            font-size: 10px;
            color: #171717;
        }
        table.items td.text-right {
            text-align: right;
        }
        table.items td.text-center {
            text-align: center;
        }
        table.items .product-name {
            font-weight: 500;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* ===== TOTALS ===== */
        .totals {
            width: 260px;
            margin-left: auto;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .totals td {
            padding: 4px 8px;
            font-size: 10px;
        }
        .totals .label {
            text-align: left;
            color: #737373;
        }
        .totals .value {
            text-align: right;
            color: #171717;
        }
        .totals .separator td {
            border-top: 1px solid #e5e5e5;
            padding: 0;
        }
        .totals .total td {
            padding-top: 6px;
            font-size: 13px;
            font-weight: 600;
        }
        .totals .balance td {
            padding-top: 4px;
            font-size: 12px;
        }
        .totals .paid td .value {
            color: #737373;
        }

        /* ===== PAYMENTS ===== */
        .payment-list {
            margin-bottom: 20px;
        }
        .payment-item {
            border-bottom: 1px solid #f5f5f5;
            padding: 6px 0;
            overflow: hidden;
        }
        .payment-item:last-child {
            border-bottom: none;
        }
        .payment-item .payment-left {
            float: left;
        }
        .payment-item .payment-left .dot {
            display: inline-block;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #d4d4d4;
            margin-right: 8px;
            vertical-align: middle;
        }
        .payment-item .payment-left .label {
            font-size: 10px;
            color: #171717;
            font-weight: 500;
        }
        .payment-item .payment-left .date {
            font-size: 8px;
            color: #a3a3a3;
            margin-left: 13px;
        }
        .payment-item .payment-right {
            float: right;
            font-size: 10px;
            font-weight: 600;
            color: #171717;
        }
        .clearfix { clear: both; }

        /* ===== NOTES ===== */
        .notes {
            margin-bottom: 20px;
        }
        .notes p {
            font-size: 10px;
            color: #737373;
            line-height: 1.6;
        }

        /* ===== FOOTER ===== */
        .footer {
            border-top: 1px solid #e5e5e5;
            padding-top: 12px;
            margin-top: 24px;
            text-align: center;
        }
        .footer p {
            font-size: 8px;
            color: #a3a3a3;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $settings->company_name }}</div>
            <p>{{ $settings->company_address }}<br>
            {{ $settings->company_city }}, {{ $settings->company_country }}</p>
        </div>
        <div class="header-right">
            <h1>Invoice</h1>
            <div class="invoice-number">{{ $invoice->invoice_number }}</div>
            @if($invoice->status)
            <div style="margin-top:6px;">
                <span class="status-badge {{ $invoice->status }}">{{ $invoice->status_label }}</span>
            </div>
            @endif
        </div>
        <div class="clearfix"></div>
    </div>

    <!-- METADATA -->
    <table class="metadata-table">
        <tr>
            <td>
                <div class="label">Invoice #</div>
                <div class="mono">{{ $invoice->invoice_number }}</div>
            </td>
            <td>
                <div class="label">Order #</div>
                <div class="mono">{{ $order->order_number }}</div>
            </td>
            <td>
                <div class="label">Date</div>
                <div>{{ $invoice->issued_at?->format('F d, Y') ?? $invoice->created_at->format('F d, Y') }}</div>
            </td>
            <td>
                <div class="label">Status</div>
                <div>{{ $invoice->status_label }}</div>
            </td>
        </tr>
    </table>

    <!-- BILL TO + AMOUNT -->
    <table class="info-grid">
        <tr>
            <td>
                <div class="section-title">Bill To</div>
                <div class="name">{{ $invoice->billing_name ?? $order->user?->full_name ?? $order->guest_name ?? 'N/A' }}</div>
                @if($invoice->billing_email || $order->user?->email || $order->guest_email)
                <div class="detail">{{ $invoice->billing_email ?? $order->user?->email ?? $order->guest_email }}</div>
                @endif
                @if($invoice->billing_phone)
                <div class="detail">{{ $invoice->billing_phone }}</div>
                @endif
                @if($invoice->billing_address)
                <div class="detail" style="margin-top:4px;">{!! nl2br(e($invoice->billing_address)) !!}</div>
                @elseif($order->address)
                <div class="detail" style="margin-top:4px;">
                    {{ $order->address->address_line1 }}@if($order->address->address_line2), {{ $order->address->address_line2 }}@endif<br>
                    {{ $order->address->city }}@if($order->address->state), {{ $order->address->state }}@endif {{ $order->address->postal_code }}<br>
                    {{ $order->address->country }}
                </div>
                @endif
            </td>
            <td class="right">
                <div class="amount-total">{{ number_format($invoice->total_amount, 2) }} MAD</div>
                @if($invoice->due_date)
                <div class="due-date" style="margin-top:2px;">Due {{ $invoice->due_date->format('M d, Y') }}</div>
                @endif
            </td>
        </tr>
    </table>

    <!-- PRODUCTS -->
    <div class="section">
        <div class="section-title">Products</div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width:50%;">Product</th>
                    <th class="text-center" style="width:60px;">Qty</th>
                    <th class="text-right" style="width:100px;">Unit Price</th>
                    <th class="text-right" style="width:100px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td><span class="product-name">{{ $item->product->name ?? 'Unknown Product' }}</span></td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-right">{{ number_format($item->price, 2) }} MAD</td>
                    <td class="text-right">{{ number_format($item->price * $item->quantity, 2) }} MAD</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- TOTALS -->
    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="value">{{ number_format($subtotal, 2) }} MAD</td>
        </tr>
        @if($shipping > 0)
        <tr>
            <td class="label">Shipping</td>
            <td class="value">{{ number_format($shipping, 2) }} MAD</td>
        </tr>
        @endif
        @if($tax > 0)
        <tr>
            <td class="label">Tax</td>
            <td class="value">{{ number_format($tax, 2) }} MAD</td>
        </tr>
        @endif
        <tr class="separator"><td colspan="2" style="height:0;"></td></tr>
        <tr class="total">
            <td class="label">Total</td>
            <td class="value">{{ number_format($invoice->total_amount, 2) }} MAD</td>
        </tr>
        @if($invoice->paid_amount > 0)
        <tr class="paid">
            <td class="label">Paid</td>
            <td class="value">− {{ number_format($invoice->paid_amount, 2) }} MAD</td>
        </tr>
        <tr class="separator"><td colspan="2" style="height:0;"></td></tr>
        <tr class="balance">
            <td class="label">Balance Due</td>
            <td class="value">{{ number_format($invoice->remaining_amount, 2) }} MAD</td>
        </tr>
        @endif
    </table>

    <!-- PAYMENT HISTORY -->
    @if($payments->count() > 0)
    <div class="section">
        <div class="section-title">Payment History</div>
        <div class="payment-list">
            @foreach($payments->sortByDesc('paid_at') as $payment)
            <div class="payment-item">
                <div class="payment-left">
                    <span class="dot"></span>
                    <span class="label">{{ $payment->payment_type_label }}</span>
                    <div class="date">{{ $payment->paid_at?->format('M d, Y') ?? $payment->created_at->format('M d, Y') }}</div>
                </div>
                <div class="payment-right">{{ $payment->amount_formatted }}</div>
                <div class="clearfix"></div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- NOTES -->
    @if($invoice->notes)
    <div class="notes">
        <div class="section-title">Notes</div>
        <p>{{ $invoice->notes }}</p>
    </div>
    @endif

    <!-- FOOTER -->
    <div class="footer">
        <p>Thank you for your business</p>
        <p>
            {{ $settings->company_name }}
            @if($settings->company_email) · {{ $settings->company_email }}@endif
            @if($settings->company_phone) · {{ $settings->company_phone }}@endif
        </p>
        <p>
            {{ $settings->company_address }}, {{ $settings->company_city }}, {{ $settings->company_country }}
        </p>
        <p style="margin-top:3px; font-size:7px;">
            Generated {{ $invoice->created_at->format('F d, Y') }}
        </p>
    </div>
</body>
</html>
