<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockInwardItem extends Model
{
    use HasFactory;

    protected $table = 'stock_inward_items';

    protected $fillable = [
        'stock_inward_id',
        'item_id',
        'qty_primary',
        'unit_primary',
        'qty_secondary',
        'unit_secondary',
        'conversion_factor',
        'rate_per_unit',
        'taxable_amount',
        'gst_rate',
        'gst_amount',
        'total_amount',
        'remarks',
    ];

    protected $casts = [
        'qty_primary' => 'decimal:4',
        'qty_secondary' => 'decimal:4',
        'conversion_factor' => 'decimal:4',
        'rate_per_unit' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Parent stock inward relation.
     */
    public function stockInward(): BelongsTo
    {
        return $this->belongsTo(StockInward::class, 'stock_inward_id');
    }

    /**
     * Master item relation.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
