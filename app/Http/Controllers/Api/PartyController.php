<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Party;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartyController extends Controller
{
    /**
     * Display a listing of parties (Customers & Vendors/Suppliers).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Party::with([
            'creator:id,name',
            'updater:id,name',
        ])
            ->withCount('inwards');

        // Search Filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('gstin', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        // Type Filter (all, supplier, customer, both, agent)
        if ($type = $request->query('type')) {
            if ($type === 'supplier') {
                $query->whereIn('type', ['supplier', 'both']);
            } elseif ($type === 'customer') {
                $query->whereIn('type', ['customer', 'both']);
            } elseif ($type === 'both') {
                $query->where('type', 'both');
            } elseif ($type === 'agent') {
                $query->where('type', 'agent');
            }
        }

        // Status Filter
        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $perPage = (int) $request->query('per_page', 5);
        $parties = $query->orderBy('name')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $parties->items(),
            'meta' => [
                'current_page' => $parties->currentPage(),
                'last_page' => $parties->lastPage(),
                'per_page' => $parties->perPage(),
                'total' => $parties->total(),
            ],
        ]);
    }

    /**
     * Store a newly created party.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['supplier', 'customer', 'both', 'agent'])],
            'code' => ['nullable', 'string', 'max:50', 'unique:parties,code'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'pan_no' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'remarks' => ['nullable', 'string'],
        ]);

        $userId = $request->user()?->id;

        $party = Party::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'code' => $validated['code'] ?? Party::generateNextCode($validated['type']),
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'gstin' => $validated['gstin'] ?? null,
            'pan_no' => $validated['pan_no'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'pincode' => $validated['pincode'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'remarks' => $validated['remarks'] ?? null,
            'created_by' => $userId,
        ]);

        $party->load(['creator:id,name']);

        $label = match ($party->type) {
            'supplier' => 'Supplier',
            'customer' => 'Customer',
            'agent' => 'Agent',
            default => 'Party (Customer/Supplier)',
        };

        return response()->json([
            'status' => 'success',
            'message' => "{$label} '{$party->name}' created successfully",
            'data' => $party,
        ], 201);
    }

    /**
     * Display the specified party.
     */
    public function show(int $id): JsonResponse
    {
        $party = Party::with([
            'creator:id,name',
            'updater:id,name',
            'inwards' => fn ($q) => $q->orderByDesc('transaction_date')->take(10),
        ])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $party,
        ]);
    }

    /**
     * Update the specified party.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $party = Party::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['supplier', 'customer', 'both', 'agent'])],
            'code' => ['required', 'string', 'max:50', Rule::unique('parties', 'code')->ignore($party->id)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'pan_no' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            'remarks' => ['nullable', 'string'],
        ]);

        $userId = $request->user()?->id;

        $party->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'code' => $validated['code'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'gstin' => $validated['gstin'] ?? null,
            'pan_no' => $validated['pan_no'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'pincode' => $validated['pincode'] ?? null,
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
            'updated_by' => $userId,
        ]);

        $party->load(['creator:id,name', 'updater:id,name']);

        return response()->json([
            'status' => 'success',
            'message' => "Party '{$party->name}' updated successfully",
            'data' => $party,
        ]);
    }

    /**
     * Remove the specified party.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $party = Party::findOrFail($id);
        $userId = $request->user()?->id;

        $party->deleted_by = $userId;
        $party->save();
        $party->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Party '{$party->name}' deleted successfully",
        ]);
    }

    /**
     * Metadata helper for dropdowns and code suggestions.
     */
    public function meta(): JsonResponse
    {
        $suppliers = Party::suppliers()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type', 'phone', 'city', 'gstin']);

        $customers = Party::customers()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type', 'phone', 'city', 'gstin']);

        $agents = Party::agents()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type', 'phone', 'city', 'gstin']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'suppliers' => $suppliers,
                'customers' => $customers,
                'agents' => $agents,
                'next_supplier_code' => Party::generateNextCode('supplier'),
                'next_customer_code' => Party::generateNextCode('customer'),
                'next_agent_code' => Party::generateNextCode('agent'),
            ],
        ]);
    }
}
