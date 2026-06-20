<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abandoned Cart</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #1a1a2e;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 24px;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        .header {
            text-align: center;
            padding-bottom: 24px;
            border-bottom: 1px solid #e9ecef;
        }
        .header h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 6px 0;
            color: #1a1a2e;
        }
        .header p {
            font-size: 14px;
            color: #6b7280;
            margin: 0;
        }
        .badge {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 999px;
            margin-top: 8px;
        }
        .summary {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 24px 0;
        }
        .summary-item {
            flex: 1;
            text-align: center;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
        }
        .summary-item .value {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a2e;
        }
        .summary-item .label {
            font-size: 12px;
            color: #6b7280;
            margin-top: 2px;
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
            letter-spacing: 0.5px;
            color: #6b7280;
            padding: 8px 12px;
            border-bottom: 1px solid #e9ecef;
        }
        .items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .product-name {
            font-weight: 600;
            color: #1a1a2e;
        }
        .product-sku {
            font-size: 12px;
            color: #9ca3af;
        }
        .text-right {
            text-align: right;
        }
        .total-row td {
            font-weight: 700;
            font-size: 15px;
            padding-top: 14px;
            border-top: 2px solid #e9ecef;
        }
        .cta {
            text-align: center;
            margin: 28px 0 8px;
        }
        .cta a {
            display: inline-block;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
        }
        .footer {
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid #e9ecef;
            margin-top: 24px;
        }
        .footer p {
            font-size: 12px;
            color: #9ca3af;
            margin: 2px 0;
        }
        @media only screen and (max-width: 480px) {
            .container { padding: 12px; }
            .card { padding: 20px; }
            .summary { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>{{ $cartOwner ? 'You left items behind!' : 'Guest Cart Abandoned' }}</h1>
                <p>
                    @if ($cartOwner)
                        Hi <strong>{{ $customerName }}</strong>, we noticed you left some items in your cart.
                    @else
                        A guest visitor left items in their cart without checking out.
                    @endif
                </p>
                <span class="badge">Cart status: abandoned</span>
            </div>

            <div class="summary">
                <div class="summary-item">
                    <div class="value">{{ $itemCount }}</div>
                    <div class="label">Items</div>
                </div>
                <div class="summary-item">
                    <div class="value">{{ number_format($totalValue, 2) }} {{ config('app.currency', 'USD') }}</div>
                    <div class="label">Total Value</div>
                </div>
            </div>

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
                    @forelse ($items as $item)
                        <tr>
                            <td>
                                <div class="product-name">{{ $item->product->name ?? 'Product #'.$item->product_id }}</div>
                                @if ($item->product->sku ?? false)
                                    <div class="product-sku">SKU: {{ $item->product->sku }}</div>
                                @endif
                            </td>
                            <td class="text-right">{{ $item->quantity }}</td>
                            <td class="text-right">{{ number_format($item->product->price ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format(($item->product->price ?? 0) * $item->quantity, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; color: #9ca3af; padding: 24px;">
                                Cart is empty.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">Total</td>
                        <td class="text-right">{{ number_format($totalValue, 2) }} {{ config('app.currency', 'USD') }}</td>
                    </tr>
                </tfoot>
            </table>

            @if ($cartOwner)
                <div class="cta">
                    <a href="{{ config('app.frontend_url', config('app.url')) }}/cart">Return to your cart</a>
                </div>
            @endif

            <div class="footer">
                <p><strong>Cart ID:</strong> {{ $ownerKey }}</p>
                <p>This cart was marked as abandoned on {{ now()->format('F j, Y \\a\\t g:i A') }}.</p>
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
