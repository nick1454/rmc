<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $category_id
 * @property string $category
 * @property string|null $size
 * @property string $secondary_unit
 * @property string $primary_unit
 * @property float $conversion_factor
 * @property string $type
 * @property string|null $hsn
 * @property float $gst_per
 * @property int|null $created_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Category|null $categoryItem
 */
class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'category',
        'size',
        'secondary_unit',
        'primary_unit',
        'conversion_factor',
        'type',
        'hsn',
        'gst_per',
        'created_by',
        'deleted_by',
    ];

    protected $appends = [
        'gst_rate',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'conversion_factor' => 'decimal:4',
            'gst_per' => 'decimal:2',
            'created_by' => 'integer',
            'deleted_by' => 'integer',
        ];
    }

    /**
     * Virtual attribute alias for gst_rate.
     */
    public function getGstRateAttribute(): ?float
    {
        return $this->gst_per !== null ? (float) $this->gst_per : null;
    }

    /**
     * The category this item belongs to.
     */
    public function categoryItem(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * User who created this item.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who deleted this item.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Master Recipe items (ingredients) for this Finished Good.
     */
    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class, 'fg_item_id');
    }

    /**
     * Scope for filtering by category.
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for filtering by type.
     */
    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
