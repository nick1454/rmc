<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fg_item_id
 * @property int $rm_item_id
 * @property float $quantity
 * @property string $unit
 * @property int|null $created_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Item $fgItem
 * @property-read Item $rmItem
 */
class RecipeItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'fg_item_id',
        'rm_item_id',
        'quantity',
        'unit',
        'created_by',
        'deleted_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'fg_item_id' => 'integer',
            'rm_item_id' => 'integer',
            'quantity' => 'decimal:4',
            'created_by' => 'integer',
            'deleted_by' => 'integer',
        ];
    }

    /**
     * The Finished Good item this recipe belongs to.
     */
    public function fgItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'fg_item_id');
    }

    /**
     * The Raw Material ingredient item.
     */
    public function rmItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'rm_item_id');
    }

    /**
     * User who created this recipe item.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who deleted this recipe item.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
