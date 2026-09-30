<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionRecipeItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_recipe_items';

    protected $fillable = [
        'production_id',
        'rm_item_id',
        'recipe_qty_per_cbm',
        'actual_qty_per_cbm',
        'unit',
        'total_qty',
        'created_by',
        'deleted_by',
    ];

    protected $casts = [
        'recipe_qty_per_cbm' => 'decimal:4',
        'actual_qty_per_cbm' => 'decimal:4',
        'total_qty' => 'decimal:4',
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
     * Raw Material item.
     */
    public function rmItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'rm_item_id');
    }
}
