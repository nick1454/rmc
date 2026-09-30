<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockOutward extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'stock_outwards';

    protected $fillable = [
        'entry_no',
        'entry_type',
        'party_id',
        'customer',
        'agent_id',
        'agent_name',
        'invoice_date',
        'challan_no',
        'challan_date',
        'vehicle_no',
        'driver_name',
        'driver_phone',
        'destination',
        'plant_name',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'commission_amount',
        'freight_charges',
        'total_amount',
        'paid_amount',
        'payment_status',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
        'challan_date' => 'date:Y-m-d',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'freight_charges' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    protected $appends = [
        'payment_discount_amount',
    ];

    /**
     * Total settlement discounts granted across payment vouchers for this outward consignment.
     */
    public function getPaymentDiscountAmountAttribute(): float
    {
        if (array_key_exists('payment_discount_amount', $this->attributes)) {
            return (float) $this->attributes['payment_discount_amount'];
        }

        return (float) $this->paymentItems()
            ->whereHas('payment', fn ($q) => $q->whereNull('deleted_at'))
            ->sum('discount_amount');
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }
            if (empty($model->entry_no)) {
                $model->entry_no = self::generateNextEntryNo();
            }
        });

        static::updating(function (self $model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function (self $model) {
            if (auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }

    /**
     * Generate consecutive sequential outward entry number (e.g. OUT-0001).
     */
    public static function generateNextEntryNo(): string
    {
        $last = self::withTrashed()
            ->where('entry_no', 'like', 'OUT-%')
            ->orderByDesc('id')
            ->first();

        $nextNum = $last ? ($last->id + 1) : 1;

        return 'OUT-'.str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Customer / Recipient Party.
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /**
     * Sales Agent / Broker Party.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'agent_id');
    }

    /**
     * Line items dispatched in this outward consignment.
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockOutwardItem::class, 'stock_outward_id');
    }

    /**
     * Outward payment allocations received against this outward invoice.
     */
    public function paymentItems(): HasMany
    {
        return $this->hasMany(OutwardPaymentItem::class, 'stock_outward_id');
    }

    /**
     * Recalculate and update the paid amount and payment status based on recorded payment vouchers.
     */
    public function recalculatePaymentStatus(): void
    {
        $paymentItems = $this->paymentItems()
            ->whereHas('payment', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->get(['paid_amount', 'discount_amount']);

        $totalPaid = (float) $paymentItems->sum('paid_amount');
        $totalDiscount = (float) $paymentItems->sum('discount_amount');
        $totalSettled = $totalPaid + $totalDiscount;
        $totalAmount = (float) $this->total_amount;

        $status = 'unpaid';
        if ($totalSettled >= $totalAmount && $totalAmount > 0) {
            $status = 'paid';
        } elseif ($totalSettled > 0) {
            $status = 'partially_paid';
        }

        $this->updateQuietly([
            'paid_amount' => $totalPaid,
            'payment_status' => $status,
        ]);
    }

    /**
     * User who created the outward entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated the outward entry.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * User who deleted the outward entry.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
