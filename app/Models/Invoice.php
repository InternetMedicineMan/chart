<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'number',
        'user_id',
        'customer_name',
        'customer_email',
        'status',
        'issued_at',
        'due_at',
        'currency',
        'subtotal',
        'tax_total',
        'total',
        'notes',
        'provider',
        'provider_type',
        'provider_id',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'due_at' => 'date',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (filled($invoice->number)) {
                return;
            }

            $invoice->number = DB::transaction(function () {
                $latestId = self::query()->lockForUpdate()->max('id') ?? 0;

                return sprintf('INV-%06d', $latestId + 1);
            });
        });
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SENT => 'Sent',
            self::STATUS_PAID => 'Paid',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function recalculateTotals(): void
    {
        $this->forceFill([
            'subtotal' => $this->items()->sum('subtotal'),
            'tax_total' => $this->items()->sum('tax_amount'),
            'total' => $this->items()->sum('total'),
        ])->saveQuietly();
    }
}
