<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { color: #111827; font-family: DejaVu Sans, Arial, sans-serif; font-size: 13px; line-height: 1.45; margin: 0; }
        h1, h2, p { margin: 0; }
        .header { align-items: flex-start; display: flex; justify-content: space-between; margin-bottom: 44px; }
        .brand { font-size: 22px; font-weight: 700; }
        .muted { color: #6b7280; }
        .invoice-title { font-size: 34px; font-weight: 700; text-align: right; }
        .grid { display: grid; gap: 24px; grid-template-columns: 1fr 1fr; margin-bottom: 32px; }
        .label { color: #6b7280; font-size: 11px; font-weight: 700; letter-spacing: .04em; margin-bottom: 6px; text-transform: uppercase; }
        .box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; }
        table { border-collapse: collapse; margin-top: 8px; width: 100%; }
        th { background: #f9fafb; color: #374151; font-size: 11px; letter-spacing: .04em; text-align: left; text-transform: uppercase; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 10px; vertical-align: top; }
        .right { text-align: right; }
        .totals { margin-left: auto; margin-top: 24px; width: 280px; }
        .totals-row { display: flex; justify-content: space-between; padding: 8px 0; }
        .grand-total { border-top: 2px solid #111827; font-size: 18px; font-weight: 700; margin-top: 6px; padding-top: 12px; }
        .notes { margin-top: 32px; }
        .status { border: 1px solid #d1d5db; border-radius: 999px; display: inline-block; font-size: 11px; font-weight: 700; padding: 4px 10px; text-transform: uppercase; }
    </style>
</head>
<body>
    @php
        $money = fn (int $amount) => strtoupper($invoice->currency).' '.number_format($amount / 100, 2);
    @endphp

    <div class="header">
        <div>
            <div class="brand">{{ config('app.name') }}</div>
            <p class="muted">{{ config('app.url') }}</p>
        </div>
        <div>
            <div class="invoice-title">Invoice</div>
            <p>{{ $invoice->number }}</p>
            <p><span class="status">{{ $invoice->status }}</span></p>
        </div>
    </div>

    <div class="grid">
        <div class="box">
            <div class="label">Bill To</div>
            <p><strong>{{ $invoice->customer_name }}</strong></p>
            @if ($invoice->customer_email)
                <p class="muted">{{ $invoice->customer_email }}</p>
            @endif
        </div>

        <div class="box">
            <div class="label">Invoice Details</div>
            <p>Issued: {{ $invoice->issued_at?->format('M j, Y') }}</p>
            @if ($invoice->due_at)
                <p>Due: {{ $invoice->due_at->format('M j, Y') }}</p>
            @endif
            @if ($invoice->provider)
                <p class="muted">Source: {{ ucfirst($invoice->provider) }} {{ $invoice->provider_type }} {{ $invoice->provider_id }}</p>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Qty</th>
                <th class="right">Unit Price</th>
                <th class="right">Tax</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="right">{{ $money($item->unit_price) }}</td>
                    <td class="right">{{ number_format((float) $item->tax_rate, 2) }}%</td>
                    <td class="right">{{ $money($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row">
            <span>Subtotal</span>
            <span>{{ $money($invoice->subtotal) }}</span>
        </div>
        <div class="totals-row">
            <span>Tax</span>
            <span>{{ $money($invoice->tax_total) }}</span>
        </div>
        <div class="totals-row grand-total">
            <span>Total</span>
            <span>{{ $money($invoice->total) }}</span>
        </div>
    </div>

    @if ($invoice->notes)
        <div class="notes">
            <div class="label">Notes</div>
            <p>{{ $invoice->notes }}</p>
        </div>
    @endif
</body>
</html>
