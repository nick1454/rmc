<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutwardPaymentItem extends Model
{
    use HasFactory;

    protected $table = 'outward_payment_items';

    protected $fillable = [
        'outward_payment_id',
        'stock_outward_id',
        'invoice_amount',
        'paid_amount',
        'discount_amount',
        'commission_agent_id',
        'commission_agent_name',
        'commission_amount',
        'remarks',
    ];

    protected $casts = [
        'invoice_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    /**
     * Parent outward payment receipt.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(OutwardPayment::class, 'outward_payment_id');
    }

    /**
     * Related stock outward invoice / delivery challan.
     */
    public function outward(): BelongsTo
    {
        return $this->belongsTo(StockOutward::class, 'stock_outward_id');
    }

    /**
     * Commission Agent party relation.
     */
    public function commissionAgent(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'commission_agent_id');
    }
}
