<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseTransaction extends Model
{
    use HasFactory;

    /**
     * PRD expense categories for V1.
     * Profit-eligible categories: COGS / Cake Production, Operational, Marketing, Salary, Rent, Employee Salaries.
     * Non-profit-eligible: Asset (decreases cash when paid, excluded from Net Profit).
     */
    public const CATEGORIES = [
        'COGS / Cake Production',
        'Operational',
        'Marketing',
        'Salary',
        'Rent',
        'Employee Salaries',
        'Asset',
        'Other',
    ];

    public const PROFIT_ELIGIBLE_CATEGORIES = [
        'COGS / Cake Production',
        'COGS',
        'Operational',
        'Marketing',
        'Salary',
        'Rent',
        'Employee Salaries',
    ];

    protected $fillable = [
        'transaction_id',
        'transaction_date',
        'transaction_name',
        'expense_category',
        'description',
        'amount',
        'account_id',
        'payment_status',
        'record_status',
        'paid_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount'           => 'decimal:2',
            'paid_at'          => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
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
