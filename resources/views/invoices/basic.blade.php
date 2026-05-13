<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color:#111827; }
        .wrap { max-width: 860px; margin: 0 auto; padding: 24px; }
        .header { display:flex; justify-content:space-between; border-bottom:1px solid #e5e7eb; padding-bottom:16px; margin-bottom:20px; }
        h1 { margin:0; font-size:28px; }
        table { width:100%; border-collapse:collapse; margin-top:18px; }
        th, td { border-bottom:1px solid #e5e7eb; padding:10px; text-align:left; }
        th { background:#f9fafb; }
        .right { text-align:right; }
        .muted { color:#6b7280; }
        .totals { width:320px; margin-left:auto; margin-top:18px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <div>
            <h1>{{ $order->business->name }}</h1>
            <div class="muted">{{ $order->business->address }}</div>
            <div>{{ $order->business->phone }} {{ $order->business->email ? ' | '.$order->business->email : '' }}</div>
        </div>
        <div class="right">
            <h2>Invoice</h2>
            <div><strong>{{ $invoice->invoice_number }}</strong></div>
            <div class="muted">{{ optional($order->created_at)->format('d M Y') }}</div>
        </div>
    </div>

    <p><strong>Bill To:</strong><br>{{ $order->customer_name_snapshot }}<br>{{ $order->customer_phone_snapshot }}<br>{{ $order->delivery_address_snapshot }}</p>

    <table>
        <thead><tr><th>Product</th><th>SKU</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Discount</th><th class="right">Total</th></tr></thead>
        <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>{{ $item->product_name_snapshot }}</td><td>{{ $item->sku_snapshot }}</td><td class="right">{{ $item->quantity }}</td><td class="right">{{ number_format($item->unit_price,2) }}</td><td class="right">{{ number_format($item->discount_amount,2) }}</td><td class="right">{{ number_format($item->line_total,2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ number_format($order->subtotal,2) }}</td></tr>
        <tr><td>Discount</td><td class="right">{{ number_format($order->discount_amount,2) }}</td></tr>
        <tr><td>Delivery Charge</td><td class="right">{{ number_format($order->delivery_charge,2) }}</td></tr>
        <tr><td><strong>Total</strong></td><td class="right"><strong>{{ number_format($order->total_amount,2) }}</strong></td></tr>
        <tr><td>Paid</td><td class="right">{{ number_format($order->paid_amount,2) }}</td></tr>
        <tr><td>Due</td><td class="right">{{ number_format($order->due_amount,2) }}</td></tr>
    </table>

    <p class="muted">Payment: {{ $order->payment_status }} | Order: {{ $order->order_status }} | Delivery: {{ $order->delivery_status }}</p>
    <p>{{ $order->business->invoice_footer }}</p>
</div>
</body>
</html>
