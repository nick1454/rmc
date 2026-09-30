<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionConsumption extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_consumptions';

    protected $fillable = [
        'production_id',
        'item_id',
        'consumed_qty',
        'unit',
        'consumed_primary_qty',
        'primary_unit',
        'consumed_date',
        'created_by',
        'deleted_by',
    ];

    protected $casts = [
        'consumed_qty' => 'decimal:4',
        'consumed_primary_qty' => 'decimal:4',
        'consumed_date' => 'date:Y-m-d',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
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
     * Parent production batch.
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'production_id');
    }

    /**
     * Raw Material item consumed.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
