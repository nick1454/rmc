<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Party extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'parties';

    protected $fillable = [
        'name',
        'type',
        'code',
        'contact_person',
        'phone',
        'email',
        'gstin',
        'pan_no',
        'address',
        'city',
        'state',
        'pincode',
        'status',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status' => 'string',
        'type' => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }
            if (empty($model->code)) {
                $model->code = self::generateNextCode($model->type ?: 'supplier');
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
     * Generate consecutive party code based on type (e.g. SUP-0001, CUS-0001, PTY-0001).
     */
    public static function generateNextCode(string $type = 'supplier'): string
    {
        $prefix = match (strtolower($type)) {
            'customer' => 'CUS',
            'supplier' => 'SUP',
            'agent' => 'AGT',
            default => 'PTY',
        };

        $last = self::withTrashed()
            ->where('code', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->first();

        $nextNum = $last ? ($last->id + 1) : 1;

        return "{$prefix}-".str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Scope for filtering suppliers/vendors (type is supplier or both).
     */
    public function scopeSuppliers(Builder $query): Builder
    {
        return $query->whereIn('type', ['supplier', 'both']);
    }

    /**
     * Scope for filtering customers (type is customer or both).
     */
    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereIn('type', ['customer', 'both']);
    }

    /**
     * Scope for filtering agents / brokers.
     */
    public function scopeAgents(Builder $query): Builder
    {
        return $query->where('type', 'agent');
    }

    /**
     * Scope for filtering active parties.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * User who created the record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who updated the record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * User who deleted the record.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Inward shipments supplied by this party.
     */
    public function inwards(): HasMany
    {
        return $this->hasMany(StockInward::class, 'supplier_party_id');
    }
}
