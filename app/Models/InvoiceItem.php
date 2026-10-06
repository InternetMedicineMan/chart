<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'subtotal',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'integer',
            'subtotal' => 'integer',
            'total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (InvoiceItem $item) {
            $subtotal = (int) round((float) $item->quantity * $item->unit_price);
            $taxAmount = (int) round($subtotal * ((float) $item->tax_rate / 100));

            $item->subtotal = $subtotal;
            $item->tax_amount = $taxAmount;
            $item->total = $subtotal + $taxAmount;
        });

        static::saved(fn (InvoiceItem $item) => $item->invoice?->recalculateTotals());
        static::deleted(fn (InvoiceItem $item) => $item->invoice?->recalculateTotals());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
