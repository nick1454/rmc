<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutwardPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'outward_payments';

    protected $fillable = [
        'payment_no',
        'payment_date',
        'party_id',
        'party_name',
        'payment_mode',
        'reference_no',
        'bank_name',
        'discount_amount',
        'commission_agent_id',
        'commission_agent_name',
        'commission_amount',
        'amount',
        'remarks',
        'is_locked',
        'locked_at',
        'locked_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'payment_date' => 'date:Y-m-d',
        'discount_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
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
     * Generate consecutive payment voucher number (e.g. REC-0001).
     */
    public static function generateNextPaymentNo(): string
    {
        $last = self::withTrashed()
            ->where('payment_no', 'like', 'REC-%')
            ->orderByDesc('id')
            ->first();

        $nextNum = $last ? ($last->id + 1) : 1;

        return 'REC-'.str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Payment item allocations against outward invoices.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OutwardPaymentItem::class, 'outward_payment_id');
    }

    /**
     * Customer / Client party relation.
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /**
     * Commission Agent party relation.
     */
    public function commissionAgent(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'commission_agent_id');
    }

    /**
     * User who created the payment receipt.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated the payment receipt.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * User who locked the payment receipt.
     */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * User who deleted the payment receipt.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
