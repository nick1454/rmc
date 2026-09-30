<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Production extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'productions';

    protected $fillable = [
        'production_no',
        'production_date',
        'fg_item_id',
        'cbm',
        'plant_name',
        'batch_no',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'production_date' => 'date:Y-m-d',
        'cbm' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }
            if (empty($model->production_no)) {
                $model->production_no = self::generateNextProductionNo();
            }
            if (empty($model->batch_no)) {
                $model->batch_no = self::generateNextBatchNo($model->production_date ? (string) $model->production_date : null);
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
     * Generate consecutive production batch numbers like PRD-0001, PRD-0002, etc.
     */
    public static function generateNextProductionNo(): string
    {
        $lastProduction = self::withTrashed()->orderByDesc('id')->first();
        $nextId = $lastProduction ? ($lastProduction->id + 1) : 1;

        return 'PRD-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate consecutive production batch references like BAT-20260922-001.
     */
    public static function generateNextBatchNo(?string $date = null): string
    {
        $dateObj = $date ? Carbon::parse($date) : now();
        $dateStr = $dateObj->format('Ymd');

        $countToday = self::withTrashed()
            ->whereDate('production_date', $dateObj->toDateString())
            ->count();

        $nextSeq = $countToday + 1;

        return 'BAT-'.$dateStr.'-'.str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Finished Good item produced (RMC Grade).
     */
    public function fgItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'fg_item_id');
    }

    /**
     * Production recipe item breakdown used for this batch.
     */
    public function recipeItems(): HasMany
    {
        return $this->hasMany(ProductionRecipeItem::class, 'production_id');
    }

    /**
     * Material consumptions deducted for this production batch.
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class, 'production_id');
    }

    /**
     * User who created the production entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who updated the production entry.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * User who deleted the production entry.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
