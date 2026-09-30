<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockInward extends Model
{
    use HasFactory;

    protected $table = 'stock_inwards';

    protected $fillable = [
        'inward_no',
        'inward_date',
        'supplier_party_id',
        'supplier_name',
        'challan_no',
        'challan_date',
        'supplier_bill_no',
        'supplier_bill_date',
        'vehicle_no',
        'driver_name',
        'driver_phone',
        'received_by_user_id',
        'remarks',
        'subtotal_amount',
        'tax_amount',
        'freight_amount',
        'other_charges',
        'discount_amount',
        'grand_total',
        'paid_amount',
        'payment_status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'inward_date' => 'date:Y-m-d',
        'challan_date' => 'date:Y-m-d',
        'supplier_bill_date' => 'date:Y-m-d',
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'freight_amount' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }
            if (empty($model->inward_no)) {
                $model->inward_no = self::generateNextInwardNo();
            }
            if (empty($model->inward_date)) {
                $model->inward_date = $model->challan_date ?? now()->toDateString();
            }
        });

        static::updating(function (self $model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    /**
     * Generate consecutive inward number (e.g. INW-0001).
     */
    public static function generateNextInwardNo(): string
    {
        $last = self::orderByDesc('id')->first();
        $nextNum = $last ? ($last->id + 1) : 1;

        return 'INW-'.str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Inward items relationship.
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockInwardItem::class, 'stock_inward_id');
    }

    /**
     * Supplier party relation.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_party_id');
    }

    /**
     * Payment item allocations relation.
     */
    public function paymentItems(): HasMany
    {
        return $this->hasMany(PurchasePaymentItem::class, 'stock_inward_id');
    }

    /**
     * Recalculate and update the paid amount and payment status based on recorded payment vouchers.
     */
    public function recalculatePaymentStatus(): void
    {
        // Sum payments from non-deleted purchase payments
        $totalPaid = (float) $this->paymentItems()
            ->whereHas('payment', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->sum('paid_amount');

        $grandTotal = (float) $this->grand_total;

        $status = 'unpaid';
        if ($totalPaid >= $grandTotal && $grandTotal > 0) {
            $status = 'paid';
        } elseif ($totalPaid > 0) {
            $status = 'partially_paid';
        }

        $this->updateQuietly([
            'paid_amount' => $totalPaid,
            'payment_status' => $status,
        ]);
    }

    /**
     * User who received the shipment.
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    /**
     * User who created the inward entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who updated the inward entry.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
