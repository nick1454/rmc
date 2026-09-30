<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePaymentItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_payment_items';

    protected $fillable = [
        'purchase_payment_id',
        'stock_inward_id',
        'invoice_amount',
        'paid_amount',
        'remarks',
    ];

    protected $casts = [
        'invoice_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    /**
     * Parent purchase payment.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(PurchasePayment::class, 'purchase_payment_id');
    }

    /**
     * Related stock inward invoice.
     */
    public function inward(): BelongsTo
    {
        return $this->belongsTo(StockInward::class, 'stock_inward_id');
    }
}
