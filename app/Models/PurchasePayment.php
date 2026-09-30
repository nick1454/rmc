<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchasePayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchase_payments';

    protected $fillable = [
        'payment_no',
        'payment_date',
        'party_id',
        'party_name',
        'payment_mode',
        'reference_no',
        'bank_name',
        'amount',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'payment_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }
            if (empty($model->payment_no)) {
                $model->payment_no = self::generateNextPaymentNo();
            }
            if (empty($model->payment_date)) {
                $model->payment_date = now()->toDateString();
            }
        });

        static::updating(function (self $model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function (self $model) {
            if (auth()->check() && ! $model->isForceDeleting()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }

    /**
     * Generate consecutive payment voucher number (e.g. PAY-0001).
     */
    public static function generateNextPaymentNo(): string
    {
        $last = self::withTrashed()->orderByDesc('id')->first();
        $nextNum = $last ? ($last->id + 1) : 1;

        return 'PAY-'.str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Payment item allocations against inward invoices.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchasePaymentItem::class, 'purchase_payment_id');
    }

    /**
     * Supplier / Vendor party relation.
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /**
     * User who created the payment.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who updated the payment.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * User who deleted the payment.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
