<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockOutwardItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'stock_outward_items';

    protected $fillable = [
        'stock_outward_id',
        'item_id',
        'qty',
        'unit',
        'secondary_qty',
        'secondary_unit',
        'conversion_factor',
        'rate',
        'pump_cost',
        'discount_percent',
        'discount_amount',
        'taxable_amount',
        'gst_rate',
        'gst_amount',
        'total_amount',
        'commission_agent_id',
        'commission_agent_name',
        'commission_rate',
        'commission_amount',
        'lotno',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'secondary_qty' => 'decimal:4',
        'conversion_factor' => 'decimal:4',
        'rate' => 'decimal:2',
        'pump_cost' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
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
     * Parent Outward header.
     */
    public function stockOutward(): BelongsTo
    {
        return $this->belongsTo(StockOutward::class, 'stock_outward_id');
    }

    /**
     * Dispatched Master Item (RM or FG).
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * Line Item Commission Agent.
     */
    public function commissionAgent(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'commission_agent_id');
    }

    /**
     * User who created this line item.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
