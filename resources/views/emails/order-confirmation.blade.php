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
        .order-summary {
            background: #fafafa;
            border: 1px solid #e5e5e5;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .order-summary dt {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #a3a3a3;
            margin-bottom: 2px;
        }
        .order-summary dd {
            font-size: 14px;
            color: #171717;
            margin: 0 0 10px 0;
        }
        .order-summary dd:last-child {
            margin-bottom: 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th {
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #a3a3a3;
            padding: 8px 0;
            border-bottom: 1px solid #e5e5e5;
        }
        .items-table td {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: #404040;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .text-right {
            text-align: right;
        }
        .product-name {
            font-weight: 500;
            color: #171717;
        }
        .product-meta {
            font-size: 12px;
            color: #a3a3a3;
        }
        .totals {
            margin-top: 4px;
            padding-top: 12px;
            border-top: 2px solid #e5e5e5;
        }
        .totals tr td {
            padding: 4px 0;
            font-size: 14px;
        }
        .totals .grand-total td {
            font-weight: 700;
            font-size: 16px;
            color: #171717;
            padding-top: 8px;
        }
        .shipping-address {
            background: #fafafa;
            border: 1px solid #e5e5e5;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .shipping-address h3 {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #a3a3a3;
            margin: 0 0 8px 0;
        }
        .shipping-address p {
            font-size: 14px;
            color: #404040;
            margin: 0;
            line-height: 1.5;
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
        .badge {
            display: inline-block;
            background: #f0fdf4;
            color: #166534;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 4px;
            margin: 12px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $companyName }}</h1>
            <p>Order Confirmation #{{ $order->order_number }}</p>
        </div>

        <p class="body-text">Dear <strong>{{ $customerName }}</strong>,</p>

        <p class="body-text">
            Thank you for your order! We're pleased to confirm that your order has been received
            and is being processed. Below you'll find a summary of your purchase.
        </p>

        <p class="body-text" style="text-align:center;">
            <span class="badge">Payment: {{ ucfirst($order->payment_method) }} · Status: {{ $order->status_label }}</span>
        </p>

        <dl class="order-summary">
            <dt>Order Number</dt>
            <dd>{{ $order->order_number }}</dd>

            <dt>Order Date</dt>
            <dd>{{ $order->created_at->format('F d, Y \\a\\t g:i A') }}</dd>

            <dt>Payment Method</dt>
            <dd>{{ ucfirst($order->payment_method) }}</dd>

            <dt>Order Status</dt>
            <dd>{{ $order->status_label }}</dd>
        </dl>

        <h3 style="font-size:13px; font-weight:600; color:#171717; margin:0 0 8px 0;">Items Ordered</h3>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>
                            <div class="product-name">{{ $item->product->name ?? 'Product #'.$item->product_id }}</div>
                            @if ($item->product->sku ?? false)
                                <div class="product-meta">SKU: {{ $item->product->sku }}</div>
                            @endif
                            @if ($item->variant?->name ?? false)
                                <div class="product-meta">{{ $item->variant->name }}</div>
                            @endif
                        </td>
                        <td class="text-right">{{ $item->quantity }}</td>
                        <td class="text-right">{{ number_format((float) $item->price, 2) }} MAD</td>
                        <td class="text-right">{{ number_format((float) $item->price * (int) $item->quantity, 2) }} MAD</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="totals">
                    <td colspan="3" style="text-align:right; font-weight:500;">Subtotal</td>
                    <td class="text-right">{{ number_format($subtotal, 2) }} MAD</td>
                </tr>
                <tr class="grand-total">
                    <td colspan="3" style="text-align:right;">Total</td>
                    <td class="text-right">{{ number_format($total, 2) }} MAD</td>
                </tr>
            </tfoot>
        </table>

        @if ($order->address)
            <div class="shipping-address">
                <h3>Shipping Address</h3>
                <p>
                    {{ $order->address->full_name }}<br>
                    {{ $order->address->address_line1 }}<br>
                    @if ($order->address->address_line2)
                        {{ $order->address->address_line2 }}<br>
                    @endif
                    {{ $order->address->city }}@if ($order->address->state), {{ $order->address->state }}@endif
                    @if ($order->address->postal_code) {{ $order->address->postal_code }}@endif<br>
                    {{ $order->address->country }}
                    @if ($order->address->phone)
                        <br>{{ $order->address->phone }}
                    @endif
                </p>
            </div>
        @endif

        <div class="cta">
            <a href="{{ config('app.frontend_url', config('app.url')) }}/orders/{{ $order->id }}">View Order Details</a>
        </div>

        <p class="body-text">
            If you have any questions about your order, please don't hesitate to
            <a href="mailto:{{ Setting::getValue('company_email', 'contact@lumenstore.com') }}" style="color:#171717;">contact us</a>.
            We're here to help!
        </p>

        <div class="footer">
            <p>{{ $companyName }}</p>
            <p>Thank you for shopping with us!</p>
        </div>
    </div>
</body>
</html>
