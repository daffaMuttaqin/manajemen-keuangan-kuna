<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomeTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'transaction_date',
        'menu_item_id',
        'quantity',
        'unit_price',
        'discount_percentage',
        'subtotal',
        'discount_amount',
        'total_amount',
        'category',
        'description',
        'account_id',
        'payment_status',
        'record_status',
        'paid_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date'    => 'date',
            'quantity'            => 'decimal:2',
            'unit_price'          => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'subtotal'            => 'decimal:2',
            'discount_amount'     => 'decimal:2',
            'total_amount'        => 'decimal:2',
            'paid_at'             => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Status helpers
    // -------------------------------------------------------------------------

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isUnpaid(): bool
    {
        return $this->payment_status === 'unpaid';
    }

    public function isActive(): bool
    {
        return $this->record_status === 'active';
    }

    public function isCancelled(): bool
    {
        return $this->record_status === 'cancelled';
    }
}
