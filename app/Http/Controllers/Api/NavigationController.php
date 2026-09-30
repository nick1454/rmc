<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NavigationCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationController extends Controller
{
    /**
     * Get permitted navigation categories and items for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user?->role;

        // If no role assigned or user is admin, load all active navigation items
        $isAdmin = ! $role || $role->slug === 'admin';

        $categories = NavigationCategory::where('is_active', true)
            ->with(['items' => function ($query) use ($isAdmin, $role) {
                $query->where('is_active', true)
                    ->when(! $isAdmin, function ($q) use ($role) {
                        $q->whereHas('roles', function ($rq) use ($role) {
                            $rq->where('roles.id', $role?->id);
                        });
                    })
                    ->orderBy('order');
            }])
            ->orderBy('order')
            ->get()
            ->filter(fn ($cat) => $cat->items->isNotEmpty())
            ->values()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'items' => $category->items->map(fn ($item) => [
                        'id' => $item->id,
                        'title' => $item->title,
                        'path' => $item->path,
                        'icon' => $item->icon,
                        'badge' => $item->badge,
                    ]),
                ];
            });

        return response()->json([
            'status' => 'success',
            'role' => [
                'id' => $role?->id,
                'slug' => $role?->slug ?? 'admin',
                'name' => $role?->name ?? 'Administrator',
            ],
            'categories' => $categories,
        ]);
    }
}
