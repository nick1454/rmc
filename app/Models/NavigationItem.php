<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NavigationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'path',
        'icon',
        'badge',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Parent category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NavigationCategory::class, 'category_id');
    }

    /**
     * Roles permitted to access this navigation item.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_navigation_item')
            ->withTimestamps();
    }
}
