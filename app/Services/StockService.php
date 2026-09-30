<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Production;
use App\Models\ProductionConsumption;
use App\Models\StockInwardItem;
use App\Models\StockOutwardItem;
use Illuminate\Support\Collection;

class StockService
{
    /**
     * Get stock details for a specific RM/FG item.
     */
    public function getItemStock(int $itemId): array
    {
        $item = Item::find($itemId);
        if (! $item) {
            return [
                'item_id' => $itemId,
                'item_name' => 'Unknown',
                'category' => '',
                'size' => '',
                'primary_unit' => 'ton',
                'secondary_unit' => 'kgs',
                'conversion_factor' => 1.0,
                'total_inward_primary' => 0.0,
                'total_inward_secondary' => 0.0,
                'total_consumed_primary' => 0.0,
                'total_consumed_secondary' => 0.0,
                'total_outward_primary' => 0.0,
                'total_outward_secondary' => 0.0,
                'available_primary' => 0.0,
                'available_secondary' => 0.0,
            ];
        }

        $conversionFactor = (float) $item->conversion_factor ?: 1.0;
        $primaryUnit = $item->primary_unit ?: ($item->type === 'fg' ? 'cum' : 'ton');
        $secondaryUnit = $item->secondary_unit ?: ($item->type === 'fg' ? 'cum' : 'kgs');

        // Total outward dispatched/transferred via Stock Outward
        $totalOutwardPrimary = (float) StockOutwardItem::where('item_id', $itemId)
            ->whereHas('stockOutward')
            ->sum('qty');
        $totalOutwardSecondary = (float) StockOutwardItem::where('item_id', $itemId)
            ->whereHas('stockOutward')
            ->sum('secondary_qty');

        if ($totalOutwardSecondary == 0.0 && $totalOutwardPrimary > 0.0) {
            $totalOutwardSecondary = $conversionFactor > 0 ? ($totalOutwardPrimary * $conversionFactor) : $totalOutwardPrimary;
        }

        if ($item->type === 'fg') {
            // Finished Goods stock: Produced via Production batches
            $totalProducedPrimary = (float) Production::where('fg_item_id', $itemId)->sum('cbm');
            $totalProducedSecondary = $conversionFactor > 0 ? ($totalProducedPrimary * $conversionFactor) : $totalProducedPrimary;

            $availablePrimary = max(0.0, $totalProducedPrimary - $totalOutwardPrimary);
            $availableSecondary = max(0.0, $totalProducedSecondary - $totalOutwardSecondary);

            $name = trim($item->category.($item->size ? ' - '.$item->size : ''));

            return [
                'item_id' => $item->id,
                'item_name' => $name,
                'category' => $item->category,
                'size' => $item->size,
                'type' => 'fg',
                'primary_unit' => $primaryUnit,
                'secondary_unit' => $secondaryUnit,
                'conversion_factor' => $conversionFactor,
                'total_inward_primary' => round($totalProducedPrimary, 4),
                'total_inward_secondary' => round($totalProducedSecondary, 4),
                'total_consumed_primary' => 0.0,
                'total_consumed_secondary' => 0.0,
                'total_outward_primary' => round($totalOutwardPrimary, 4),
                'total_outward_secondary' => round($totalOutwardSecondary, 4),
                'available_primary' => round($availablePrimary, 4),
                'available_secondary' => round($availableSecondary, 4),
            ];
        }

        // RM Item: Total Inward via Stock Inwards
        $totalInwardPrimary = (float) StockInwardItem::where('item_id', $itemId)->sum('qty_primary');
        $totalInwardSecondary = (float) StockInwardItem::where('item_id', $itemId)->sum('qty_secondary');

        // Total consumed via Production
        $totalConsumedSecondary = (float) ProductionConsumption::where('item_id', $itemId)
            ->whereHas('production')
            ->sum('consumed_qty');

        $totalConsumedPrimary = $conversionFactor > 0 ? ($totalConsumedSecondary / $conversionFactor) : $totalConsumedSecondary;

        $availablePrimary = max(0.0, $totalInwardPrimary - $totalConsumedPrimary - $totalOutwardPrimary);
        $availableSecondary = max(0.0, $totalInwardSecondary - $totalConsumedSecondary - $totalOutwardSecondary);

        $name = trim($item->category.($item->size ? ' - '.$item->size : ''));

        return [
            'item_id' => $item->id,
            'item_name' => $name,
            'category' => $item->category,
            'size' => $item->size,
            'type' => 'rm',
            'primary_unit' => $primaryUnit,
            'secondary_unit' => $secondaryUnit,
            'conversion_factor' => $conversionFactor,
            'total_inward_primary' => round($totalInwardPrimary, 4),
            'total_inward_secondary' => round($totalInwardSecondary, 4),
            'total_consumed_primary' => round($totalConsumedPrimary, 4),
            'total_consumed_secondary' => round($totalConsumedSecondary, 4),
            'total_outward_primary' => round($totalOutwardPrimary, 4),
            'total_outward_secondary' => round($totalOutwardSecondary, 4),
            'available_primary' => round($availablePrimary, 4),
            'available_secondary' => round($availableSecondary, 4),
        ];
    }

    /**
     * Get stock balances for all Raw Material (RM) items.
     */
    public function getAllRmStock(): Collection
    {
        $rmItems = Item::where('type', 'rm')
            ->orderBy('category')
            ->orderBy('size')
            ->get();

        return $rmItems->map(fn (Item $item) => $this->getItemStock($item->id));
    }

    /**
     * Check if an item category is exempt from inventory stock verification (e.g. Water).
     */
    public function isStockCheckExempt(string $category): bool
    {
        return strcasecmp(trim($category), 'water') === 0;
    }

    /**
     * Validate if all required recipe quantities are available in stock.
     * Items exempt from stock checks (e.g. Water) are skipped and will not block production.
     *
     * @param  array<array{rm_item_id: int, total_qty: float, unit?: string}>  $requiredItems
     * @return array{is_valid: bool, shortages: array, stock_snapshot: array}
     */
    public function validateStockAvailability(array $requiredItems): array
    {
        $shortages = [];
        $stockSnapshot = [];

        foreach ($requiredItems as $itemReq) {
            $itemId = (int) $itemReq['rm_item_id'];
            $requiredQty = (float) $itemReq['total_qty'];

            $stock = $this->getItemStock($itemId);
            $isExempt = $this->isStockCheckExempt($stock['category']);
            $stock['is_stock_exempt'] = $isExempt;
            $stockSnapshot[$itemId] = $stock;

            if ($isExempt) {
                continue;
            }

            $availableQty = $stock['available_secondary'];

            if ($requiredQty > $availableQty) {
                $shortageQty = $requiredQty - $availableQty;
                $factor = $stock['conversion_factor'] ?: 1.0;

                $shortages[] = [
                    'item_id' => $itemId,
                    'item_name' => $stock['item_name'],
                    'category' => $stock['category'],
                    'size' => $stock['size'],
                    'required_qty' => round($requiredQty, 4),
                    'available_qty' => round($availableQty, 4),
                    'shortage_qty' => round($shortageQty, 4),
                    'unit' => $stock['secondary_unit'],
                    'primary_required_qty' => round($requiredQty / $factor, 4),
                    'primary_available_qty' => round($availableQty / $factor, 4),
                    'primary_shortage_qty' => round($shortageQty / $factor, 4),
                    'primary_unit' => $stock['primary_unit'],
                ];
            }
        }

        return [
            'is_valid' => count($shortages) === 0,
            'shortages' => $shortages,
            'stock_snapshot' => $stockSnapshot,
        ];
    }

    /**
     * Record consumption ledger rows for a production batch.
     *
     * @param  array<array{rm_item_id: int, total_qty: float, unit?: string}>  $recipeItems
     */
    public function recordConsumptions(Production $production, array $recipeItems, ?int $userId = null): void
    {
        foreach ($recipeItems as $item) {
            $itemId = (int) $item['rm_item_id'];
            $consumedQty = (float) $item['total_qty'];
            $unit = $item['unit'] ?? 'kgs';

            $masterItem = Item::find($itemId);
            $primaryUnit = $masterItem?->primary_unit ?: 'ton';

            $consumedPrimaryQty = $this->convertUnitQty($consumedQty, $unit, $primaryUnit, $masterItem);

            ProductionConsumption::create([
                'production_id' => $production->id,
                'item_id' => $itemId,
                'consumed_qty' => $consumedQty,
                'unit' => $unit,
                'consumed_primary_qty' => $consumedPrimaryQty,
                'primary_unit' => $primaryUnit,
                'consumed_date' => $production->production_date,
                'created_by' => $userId,
            ]);
        }
    }

    /**
     * Robust unit conversion supporting ton, kgs, cum, ltr, bags, etc.
     */
    public function convertUnitQty(float $qty, string $fromUnit, string $toUnit, ?Item $item = null): float
    {
        $from = strtolower(trim($fromUnit));
        $to = strtolower(trim($toUnit));

        if ($from === $to || $qty == 0) {
            return $qty;
        }

        $conversionFactor = $item ? (float) $item->conversion_factor : 0;

        if ($item && $conversionFactor > 0) {
            $prim = strtolower(trim($item->primary_unit ?: ''));
            $sec = strtolower(trim($item->secondary_unit ?: ''));

            if ($from === $prim && $to === $sec) {
                return $qty * $conversionFactor;
            }
            if ($from === $sec && $to === $prim) {
                return $qty / $conversionFactor;
            }
        }

        // Standard mass conversions
        if ($from === 'ton' && ($to === 'kgs' || $to === 'kg')) {
            return $qty * 1000.0;
        }
        if (($from === 'kgs' || $from === 'kg') && $to === 'ton') {
            return $qty / 1000.0;
        }
        if ($from === 'bag' && ($to === 'kgs' || $to === 'kg')) {
            return $qty * 50.0;
        }
        if (($from === 'kgs' || $from === 'kg') && $to === 'bag') {
            return $qty / 50.0;
        }

        // Standard volume conversions
        if (($from === 'cum' || $from === 'cbm' || $from === 'm3') && ($to === 'ltr' || $to === 'liter' || $to === 'liters')) {
            return $qty * 1000.0;
        }
        if (($from === 'ltr' || $from === 'liter' || $from === 'liters') && ($from === 'cum' || $to === 'cbm' || $to === 'm3')) {
            return $qty / 1000.0;
        }

        return $qty;
    }

    /**
     * Periodic stock report summarizing Opening, Inward, Consumption, Outward, and Closing stock balances.
     *
     * @return array{kpis: array, items: array, filters: array}
     */
    public function getStockSummaryReport(
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $type = null,
        $category = null,
        ?string $search = null
    ): array {
        $query = Item::query();

        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }

        if ($category && $category !== 'all') {
            if (is_numeric($category)) {
                $query->where('category_id', $category);
            } else {
                $query->where('category', $category);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('category', 'like', "%{$search}%")
                    ->orWhere('size', 'like', "%{$search}%")
                    ->orWhere('hsn', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('type')
            ->orderBy('category')
            ->orderBy('size')
            ->get();

        $kpis = [
            'total_rm_items' => 0,
            'total_fg_items' => 0,
            'total_inward_ton' => 0.0,
            'total_consumed_ton' => 0.0,
            'total_rm_outward_ton' => 0.0,
            'total_fg_produced_cbm' => 0.0,
            'total_fg_dispatched_cbm' => 0.0,
            'total_fg_closing_cbm' => 0.0,
        ];

        $reportItems = [];

        foreach ($items as $item) {
            $factor = (float) $item->conversion_factor ?: 1.0;
            $primUnit = $item->primary_unit ?: ($item->type === 'fg' ? 'cum' : 'ton');
            $secUnit = $item->secondary_unit ?: ($item->type === 'fg' ? 'cum' : 'kgs');
            $itemName = trim($item->category.($item->size ? ' - '.$item->size : ''));

            if ($item->type === 'fg') {
                $kpis['total_fg_items']++;

                // 1. FG Opening (before fromDate)
                $openingProduced = 0.0;
                $openingOutwardPrimary = 0.0;
                $openingOutwardSecondary = 0.0;

                if ($fromDate) {
                    $openingProduced = (float) Production::where('fg_item_id', $item->id)
                        ->whereDate('production_date', '<', $fromDate)
                        ->sum('cbm');

                    $openingOutwardPrimary = (float) StockOutwardItem::where('item_id', $item->id)
                        ->whereHas('stockOutward', function ($q) use ($fromDate) {
                            $q->whereDate('invoice_date', '<', $fromDate);
                        })
                        ->sum('qty');

                    $openingOutwardSecondary = (float) StockOutwardItem::where('item_id', $item->id)
                        ->whereHas('stockOutward', function ($q) use ($fromDate) {
                            $q->whereDate('invoice_date', '<', $fromDate);
                        })
                        ->sum('secondary_qty');
                }

                $openingPrimary = max(0.0, $openingProduced - $openingOutwardPrimary);
                $openingSecondary = $factor > 0 ? ($openingPrimary * $factor) : $openingPrimary;

                // 2. FG Period Movement
                $prodQuery = Production::where('fg_item_id', $item->id);
                if ($fromDate) {
                    $prodQuery->whereDate('production_date', '>=', $fromDate);
                }
                if ($toDate) {
                    $prodQuery->whereDate('production_date', '<=', $toDate);
                }
                $periodProducedPrimary = (float) $prodQuery->sum('cbm');
                $periodProducedSecondary = $factor > 0 ? ($periodProducedPrimary * $factor) : $periodProducedPrimary;

                // 3. FG Period Outward (Dispatched)
                $outQuery = StockOutwardItem::where('item_id', $item->id)
                    ->whereHas('stockOutward', function ($q) use ($fromDate, $toDate) {
                        if ($fromDate) {
                            $q->whereDate('invoice_date', '>=', $fromDate);
                        }
                        if ($toDate) {
                            $q->whereDate('invoice_date', '<=', $toDate);
                        }
                    });
                $periodOutwardPrimary = (float) $outQuery->sum('qty');
                $periodOutwardSecondary = (float) $outQuery->sum('secondary_qty');
                if ($periodOutwardSecondary == 0.0 && $periodOutwardPrimary > 0.0) {
                    $periodOutwardSecondary = $factor > 0 ? ($periodOutwardPrimary * $factor) : $periodOutwardPrimary;
                }

                // 4. FG Closing Balance
                $closingPrimary = max(0.0, $openingPrimary + $periodProducedPrimary - $periodOutwardPrimary);
                $closingSecondary = $factor > 0 ? ($closingPrimary * $factor) : $closingPrimary;

                $kpis['total_fg_produced_cbm'] += $periodProducedPrimary;
                $kpis['total_fg_dispatched_cbm'] += $periodOutwardPrimary;
                $kpis['total_fg_closing_cbm'] += $closingPrimary;

                $reportItems[] = [
                    'item_id' => $item->id,
                    'code' => $item->hsn ?: "ITM-{$item->id}",
                    'item_name' => $itemName,
                    'category' => $item->category,
                    'size' => $item->size,
                    'type' => 'fg',
                    'primary_unit' => $primUnit,
                    'secondary_unit' => $secUnit,
                    'conversion_factor' => $factor,
                    'opening_primary' => round($openingPrimary, 4),
                    'opening_secondary' => round($openingSecondary, 4),
                    'inward_primary' => round($periodProducedPrimary, 4),
                    'inward_secondary' => round($periodProducedSecondary, 4),
                    'consumed_primary' => 0.0,
                    'consumed_secondary' => 0.0,
                    'outward_primary' => round($periodOutwardPrimary, 4),
                    'outward_secondary' => round($periodOutwardSecondary, 4),
                    'closing_primary' => round($closingPrimary, 4),
                    'closing_secondary' => round($closingSecondary, 4),
                    'status' => $closingPrimary > 0 ? 'in_stock' : 'out_of_stock',
                    'is_stock_exempt' => false,
                ];
            } else {
                $kpis['total_rm_items']++;

                $isExempt = $this->isStockCheckExempt($item->category);

                // 1. RM Opening
                $openingInwardPrim = 0.0;
                $openingInwardSec = 0.0;
                $openingConsumedSec = 0.0;
                $openingOutwardPrim = 0.0;
                $openingOutwardSec = 0.0;

                if ($fromDate) {
                    $openingInwardPrim = (float) StockInwardItem::where('item_id', $item->id)
                        ->whereHas('stockInward', function ($q) use ($fromDate) {
                            $q->whereDate('inward_date', '<', $fromDate);
                        })
                        ->sum('qty_primary');

                    $openingInwardSec = (float) StockInwardItem::where('item_id', $item->id)
                        ->whereHas('stockInward', function ($q) use ($fromDate) {
                            $q->whereDate('inward_date', '<', $fromDate);
                        })
                        ->sum('qty_secondary');

                    $openingConsumedSec = (float) ProductionConsumption::where('item_id', $item->id)
                        ->whereHas('production', function ($q) use ($fromDate) {
                            $q->whereDate('production_date', '<', $fromDate);
                        })
                        ->sum('consumed_qty');

                    $openingOutwardPrim = (float) StockOutwardItem::where('item_id', $item->id)
                        ->whereHas('stockOutward', function ($q) use ($fromDate) {
                            $q->whereDate('invoice_date', '<', $fromDate);
                        })
                        ->sum('qty');

                    $openingOutwardSec = (float) StockOutwardItem::where('item_id', $item->id)
                        ->whereHas('stockOutward', function ($q) use ($fromDate) {
                            $q->whereDate('invoice_date', '<', $fromDate);
                        })
                        ->sum('secondary_qty');
                }

                $openingConsumedPrim = $factor > 0 ? ($openingConsumedSec / $factor) : $openingConsumedSec;
                $openingPrim = max(0.0, $openingInwardPrim - $openingConsumedPrim - $openingOutwardPrim);
                $openingSec = max(0.0, $openingInwardSec - $openingConsumedSec - $openingOutwardSec);

                // 2. RM Period Inward
                $inwQuery = StockInwardItem::where('item_id', $item->id)
                    ->whereHas('stockInward', function ($q) use ($fromDate, $toDate) {
                        if ($fromDate) {
                            $q->whereDate('inward_date', '>=', $fromDate);
                        }
                        if ($toDate) {
                            $q->whereDate('inward_date', '<=', $toDate);
                        }
                    });

                $periodInwardPrim = (float) $inwQuery->sum('qty_primary');
                $periodInwardSec = (float) $inwQuery->sum('qty_secondary');

                // 3. RM Period Consumption
                $consQuery = ProductionConsumption::where('item_id', $item->id)
                    ->whereHas('production', function ($q) use ($fromDate, $toDate) {
                        if ($fromDate) {
                            $q->whereDate('production_date', '>=', $fromDate);
                        }
                        if ($toDate) {
                            $q->whereDate('production_date', '<=', $toDate);
                        }
                    });
                $periodConsumedSec = (float) $consQuery->sum('consumed_qty');
                $periodConsumedPrim = $factor > 0 ? ($periodConsumedSec / $factor) : $periodConsumedSec;

                // 4. RM Period Outward (returns / site transfers)
                $rmOutQuery = StockOutwardItem::where('item_id', $item->id)
                    ->whereHas('stockOutward', function ($q) use ($fromDate, $toDate) {
                        if ($fromDate) {
                            $q->whereDate('invoice_date', '>=', $fromDate);
                        }
                        if ($toDate) {
                            $q->whereDate('invoice_date', '<=', $toDate);
                        }
                    });
                $periodOutwardPrim = (float) $rmOutQuery->sum('qty');
                $periodOutwardSec = (float) $rmOutQuery->sum('secondary_qty');

                // 5. RM Closing Balance
                $closingPrim = max(0.0, $openingPrim + $periodInwardPrim - $periodConsumedPrim - $periodOutwardPrim);
                $closingSec = max(0.0, $openingSec + $periodInwardSec - $periodConsumedSec - $periodOutwardSec);

                $kpis['total_inward_ton'] += $periodInwardPrim;
                $kpis['total_consumed_ton'] += $periodConsumedPrim;
                $kpis['total_rm_outward_ton'] += $periodOutwardPrim;

                $reportItems[] = [
                    'item_id' => $item->id,
                    'code' => $item->hsn ?: "ITM-{$item->id}",
                    'item_name' => $itemName,
                    'category' => $item->category,
                    'size' => $item->size,
                    'type' => 'rm',
                    'primary_unit' => $primUnit,
                    'secondary_unit' => $secUnit,
                    'conversion_factor' => $factor,
                    'opening_primary' => round($openingPrim, 4),
                    'opening_secondary' => round($openingSec, 4),
                    'inward_primary' => round($periodInwardPrim, 4),
                    'inward_secondary' => round($periodInwardSec, 4),
                    'consumed_primary' => round($periodConsumedPrim, 4),
                    'consumed_secondary' => round($periodConsumedSec, 4),
                    'outward_primary' => round($periodOutwardPrim, 4),
                    'outward_secondary' => round($periodOutwardSec, 4),
                    'closing_primary' => round($closingPrim, 4),
                    'closing_secondary' => round($closingSec, 4),
                    'status' => $closingPrim > 0 || $isExempt ? 'in_stock' : 'out_of_stock',
                    'is_stock_exempt' => $isExempt,
                ];
            }
        }

        $kpis['total_inward_ton'] = round($kpis['total_inward_ton'], 2);
        $kpis['total_consumed_ton'] = round($kpis['total_consumed_ton'], 2);
        $kpis['total_rm_outward_ton'] = round($kpis['total_rm_outward_ton'], 2);
        $kpis['total_fg_produced_cbm'] = round($kpis['total_fg_produced_cbm'], 2);
        $kpis['total_fg_dispatched_cbm'] = round($kpis['total_fg_dispatched_cbm'], 2);
        $kpis['total_fg_closing_cbm'] = round($kpis['total_fg_closing_cbm'], 2);

        return [
            'kpis' => $kpis,
            'items' => $reportItems,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'type' => $type,
                'category' => $category,
                'search' => $search,
            ],
        ];
    }

    /**
     * Item Ledger: Chronological transaction ledger of all movements for a specific item.
     *
     * @return array{item: array, opening_stock: array, entries: array, closing_stock: array, summary: array}
     */
    public function getItemLedgerReport(int $itemId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $item = Item::findOrFail($itemId);
        $factor = (float) $item->conversion_factor ?: 1.0;
        $primUnit = $item->primary_unit ?: ($item->type === 'fg' ? 'cum' : 'ton');
        $secUnit = $item->secondary_unit ?: ($item->type === 'fg' ? 'cum' : 'kgs');
        $itemName = trim($item->category.($item->size ? ' - '.$item->size : ''));

        $entries = [];

        if ($item->type === 'fg') {
            $openingPrimary = 0.0;
            $openingOutwardPrimary = 0.0;

            if ($fromDate) {
                $openingPrimary = (float) Production::where('fg_item_id', $itemId)
                    ->whereDate('production_date', '<', $fromDate)
                    ->sum('cbm');

                $openingOutwardPrimary = (float) StockOutwardItem::where('item_id', $itemId)
                    ->whereHas('stockOutward', function ($q) use ($fromDate) {
                        $q->whereDate('invoice_date', '<', $fromDate);
                    })
                    ->sum('qty');
            }

            $openingPrimary = max(0.0, $openingPrimary - $openingOutwardPrimary);
            $openingSecondary = $factor > 0 ? ($openingPrimary * $factor) : $openingPrimary;

            // Production entries (+)
            $prodQuery = Production::where('fg_item_id', $itemId)
                ->where(function ($q) use ($fromDate, $toDate) {
                    if ($fromDate) {
                        $q->whereDate('production_date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->whereDate('production_date', '<=', $toDate);
                    }
                });

            $productions = $prodQuery->get();

            foreach ($productions as $prod) {
                $qtyPrim = (float) $prod->cbm;
                $qtySec = $factor > 0 ? ($qtyPrim * $factor) : $qtyPrim;

                $entries[] = [
                    'date' => $prod->production_date ? $prod->production_date->format('Y-m-d') : '',
                    'timestamp' => $prod->created_at ? $prod->created_at->timestamp : 0,
                    'module' => 'production',
                    'action' => 'in',
                    'action_label' => 'Batch Production (+)',
                    'reference_no' => $prod->production_no ?? '',
                    'batch_no' => $prod->batch_no ?? '',
                    'party_name' => "Plant Production ({$prod->plant_name})",
                    'vehicle_no' => '',
                    'destination' => $prod->plant_name ?: 'Central Plant',
                    'qty_in_primary' => round($qtyPrim, 4),
                    'qty_in_secondary' => round($qtySec, 4),
                    'qty_out_primary' => 0.0,
                    'qty_out_secondary' => 0.0,
                    'unit_primary' => $primUnit,
                    'unit_secondary' => $secUnit,
                    'remarks' => $prod->remarks ?: '',
                ];
            }

            // FG Outward entries (-)
            $fgOutQuery = StockOutwardItem::where('item_id', $itemId)
                ->with(['stockOutward.party'])
                ->whereHas('stockOutward', function ($q) use ($fromDate, $toDate) {
                    if ($fromDate) {
                        $q->whereDate('invoice_date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->whereDate('invoice_date', '<=', $toDate);
                    }
                });

            $fgOutwards = $fgOutQuery->get();

            foreach ($fgOutwards as $outItem) {
                $outward = $outItem->stockOutward;
                $custName = $outward?->party?->name ?: ($outward?->customer ?: 'Customer Dispatch');
                $qtyPrim = (float) $outItem->qty;
                $qtySec = (float) $outItem->secondary_qty;
                if ($qtySec == 0.0 && $qtyPrim > 0.0) {
                    $qtySec = $factor > 0 ? ($qtyPrim * $factor) : $qtyPrim;
                }

                $entries[] = [
                    'date' => $outward?->invoice_date ? $outward->invoice_date->format('Y-m-d') : '',
                    'timestamp' => $outward?->created_at ? $outward->created_at->timestamp : 0,
                    'module' => 'outward',
                    'action' => 'out',
                    'action_label' => 'Dispatch Outward (-)',
                    'reference_no' => $outward?->entry_no ?? '',
                    'batch_no' => $outward?->challan_no ? "Challan: {$outward->challan_no}" : ($outItem->lotno ? "Lot: {$outItem->lotno}" : ''),
                    'party_name' => $custName,
                    'vehicle_no' => $outward?->vehicle_no ?: '',
                    'destination' => $outward?->destination ?: 'Customer Site',
                    'qty_in_primary' => 0.0,
                    'qty_in_secondary' => 0.0,
                    'qty_out_primary' => round($qtyPrim, 4),
                    'qty_out_secondary' => round($qtySec, 4),
                    'unit_primary' => $primUnit,
                    'unit_secondary' => $secUnit,
                    'remarks' => $outItem->remarks ?: ($outward?->remarks ?: ''),
                ];
            }
        } else {
            $openingPrimary = 0.0;
            $openingSecondary = 0.0;

            if ($fromDate) {
                $openingInwardPrim = (float) StockInwardItem::where('item_id', $itemId)
                    ->whereHas('stockInward', function ($q) use ($fromDate) {
                        $q->whereDate('inward_date', '<', $fromDate);
                    })
                    ->sum('qty_primary');

                $openingInwardSec = (float) StockInwardItem::where('item_id', $itemId)
                    ->whereHas('stockInward', function ($q) use ($fromDate) {
                        $q->whereDate('inward_date', '<', $fromDate);
                    })
                    ->sum('qty_secondary');

                $openingConsumedSec = (float) ProductionConsumption::where('item_id', $itemId)
                    ->whereHas('production', function ($q) use ($fromDate) {
                        $q->whereDate('production_date', '<', $fromDate);
                    })
                    ->sum('consumed_qty');

                $openingOutwardPrim = (float) StockOutwardItem::where('item_id', $itemId)
                    ->whereHas('stockOutward', function ($q) use ($fromDate) {
                        $q->whereDate('invoice_date', '<', $fromDate);
                    })
                    ->sum('qty');

                $openingOutwardSec = (float) StockOutwardItem::where('item_id', $itemId)
                    ->whereHas('stockOutward', function ($q) use ($fromDate) {
                        $q->whereDate('invoice_date', '<', $fromDate);
                    })
                    ->sum('secondary_qty');

                $openingConsumedPrim = $factor > 0 ? ($openingConsumedSec / $factor) : $openingConsumedSec;

                $openingPrimary = max(0.0, $openingInwardPrim - $openingConsumedPrim - $openingOutwardPrim);
                $openingSecondary = max(0.0, $openingInwardSec - $openingConsumedSec - $openingOutwardSec);
            }

            // Inward receipts (+)
            $inwQuery = StockInwardItem::where('item_id', $itemId)
                ->with(['stockInward.supplier'])
                ->whereHas('stockInward', function ($q) use ($fromDate, $toDate) {
                    if ($fromDate) {
                        $q->whereDate('inward_date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->whereDate('inward_date', '<=', $toDate);
                    }
                });

            $inwards = $inwQuery->get();

            foreach ($inwards as $inwItem) {
                $inw = $inwItem->stockInward;
                $supplierName = $inw?->supplier?->name ?: ($inw?->supplier_name ?: 'Vendor Supply');
                $refNo = $inw?->inward_no ?? '';
                $batchRef = $inw?->challan_no ? "Challan: {$inw->challan_no}" : '';

                $entries[] = [
                    'date' => $inw?->inward_date ? $inw->inward_date->format('Y-m-d') : '',
                    'timestamp' => $inw?->created_at ? $inw->created_at->timestamp : 0,
                    'module' => 'inward',
                    'action' => 'in',
                    'action_label' => 'Inward Receipt (+)',
                    'reference_no' => $refNo,
                    'batch_no' => $batchRef,
                    'party_name' => $supplierName,
                    'vehicle_no' => $inw?->vehicle_no ?: '',
                    'destination' => 'Raw Material Yard / Silo',
                    'qty_in_primary' => round((float) $inwItem->qty_primary, 4),
                    'qty_in_secondary' => round((float) $inwItem->qty_secondary, 4),
                    'qty_out_primary' => 0.0,
                    'qty_out_secondary' => 0.0,
                    'unit_primary' => $primUnit,
                    'unit_secondary' => $secUnit,
                    'remarks' => $inwItem->remarks ?: ($inw?->remarks ?: ''),
                ];
            }

            // Production consumptions (-)
            $consQuery = ProductionConsumption::where('item_id', $itemId)
                ->with(['production.fgItem'])
                ->whereHas('production', function ($q) use ($fromDate, $toDate) {
                    if ($fromDate) {
                        $q->whereDate('production_date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->whereDate('production_date', '<=', $toDate);
                    }
                });

            $consumptions = $consQuery->get();

            foreach ($consumptions as $cons) {
                $prod = $cons->production;
                $qtySec = (float) $cons->consumed_qty;
                $qtyPrim = $factor > 0 ? ($qtySec / $factor) : $qtySec;

                $fgLabel = $prod?->fgItem ? trim($prod->fgItem->category.' '.$prod->fgItem->size) : 'Concrete';

                $entries[] = [
                    'date' => $cons->consumed_date ? $cons->consumed_date->format('Y-m-d') : ($prod?->production_date ? $prod->production_date->format('Y-m-d') : ''),
                    'timestamp' => $cons->created_at ? $cons->created_at->timestamp : 0,
                    'module' => 'production',
                    'action' => 'out',
                    'action_label' => 'Batching Consumption (-)',
                    'reference_no' => $prod?->production_no ?? '',
                    'batch_no' => $prod?->batch_no ?? '',
                    'party_name' => "Used for {$fgLabel} ({$prod?->cbm} CBM)",
                    'vehicle_no' => '',
                    'destination' => $prod?->plant_name ?: 'Batching Plant',
                    'qty_in_primary' => 0.0,
                    'qty_in_secondary' => 0.0,
                    'qty_out_primary' => round($qtyPrim, 4),
                    'qty_out_secondary' => round($qtySec, 4),
                    'unit_primary' => $primUnit,
                    'unit_secondary' => $secUnit,
                    'remarks' => $prod?->remarks ?: '',
                ];
            }

            // RM Outward entries / returns / transfers (-)
            $rmOutQuery = StockOutwardItem::where('item_id', $itemId)
                ->with(['stockOutward.party'])
                ->whereHas('stockOutward', function ($q) use ($fromDate, $toDate) {
                    if ($fromDate) {
                        $q->whereDate('invoice_date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->whereDate('invoice_date', '<=', $toDate);
                    }
                });

            $rmOutwards = $rmOutQuery->get();

            foreach ($rmOutwards as $outItem) {
                $outward = $outItem->stockOutward;
                $partyName = $outward?->party?->name ?: ($outward?->customer ?: 'Material Transfer / Return');
                $qtyPrim = (float) $outItem->qty;
                $qtySec = (float) $outItem->secondary_qty;
                if ($qtySec == 0.0 && $qtyPrim > 0.0) {
                    $qtySec = $factor > 0 ? ($qtyPrim * $factor) : $qtyPrim;
                }

                $entries[] = [
                    'date' => $outward?->invoice_date ? $outward->invoice_date->format('Y-m-d') : '',
                    'timestamp' => $outward?->created_at ? $outward->created_at->timestamp : 0,
                    'module' => 'outward',
                    'action' => 'out',
                    'action_label' => 'Material Outward / Transfer (-)',
                    'reference_no' => $outward?->entry_no ?? '',
                    'batch_no' => $outward?->challan_no ? "Challan: {$outward->challan_no}" : ($outItem->lotno ? "Lot: {$outItem->lotno}" : ''),
                    'party_name' => $partyName,
                    'vehicle_no' => $outward?->vehicle_no ?: '',
                    'destination' => $outward?->destination ?: 'Depot / Vendor Yard',
                    'qty_in_primary' => 0.0,
                    'qty_in_secondary' => 0.0,
                    'qty_out_primary' => round($qtyPrim, 4),
                    'qty_out_secondary' => round($qtySec, 4),
                    'unit_primary' => $primUnit,
                    'unit_secondary' => $secUnit,
                    'remarks' => $outItem->remarks ?: ($outward?->remarks ?: ''),
                ];
            }
        }

        // Sort entries chronologically: date asc, then timestamp asc
        usort($entries, function ($a, $b) {
            $cmp = strcmp($a['date'], $b['date']);
            if ($cmp === 0) {
                return $a['timestamp'] <=> $b['timestamp'];
            }

            return $cmp;
        });

        // Compute running balance
        $runningPrimary = $openingPrimary;
        $runningSecondary = $openingSecondary;
        $totalInPrimary = 0.0;
        $totalInSecondary = 0.0;
        $totalOutPrimary = 0.0;
        $totalOutSecondary = 0.0;

        foreach ($entries as &$entry) {
            $totalInPrimary += $entry['qty_in_primary'];
            $totalInSecondary += $entry['qty_in_secondary'];
            $totalOutPrimary += $entry['qty_out_primary'];
            $totalOutSecondary += $entry['qty_out_secondary'];

            $runningPrimary = max(0.0, $runningPrimary + $entry['qty_in_primary'] - $entry['qty_out_primary']);
            $runningSecondary = max(0.0, $runningSecondary + $entry['qty_in_secondary'] - $entry['qty_out_secondary']);

            $entry['balance_primary'] = round($runningPrimary, 4);
            $entry['balance_secondary'] = round($runningSecondary, 4);
        }
        unset($entry);

        return [
            'item' => [
                'id' => $item->id,
                'code' => $item->hsn ?: "ITM-{$item->id}",
                'name' => $itemName,
                'category' => $item->category,
                'size' => $item->size,
                'type' => $item->type,
                'primary_unit' => $primUnit,
                'secondary_unit' => $secUnit,
                'conversion_factor' => $factor,
            ],
            'opening_stock' => [
                'primary' => round($openingPrimary, 4),
                'secondary' => round($openingSecondary, 4),
                'unit_primary' => $primUnit,
                'secondary_unit' => $secUnit,
            ],
            'entries' => $entries,
            'closing_stock' => [
                'primary' => round($runningPrimary, 4),
                'secondary' => round($runningSecondary, 4),
                'unit_primary' => $primUnit,
                'secondary_unit' => $secUnit,
            ],
            'summary' => [
                'total_in_primary' => round($totalInPrimary, 4),
                'total_in_secondary' => round($totalInSecondary, 4),
                'total_out_primary' => round($totalOutPrimary, 4),
                'total_out_secondary' => round($totalOutSecondary, 4),
                'net_change_primary' => round($totalInPrimary - $totalOutPrimary, 4),
                'net_change_secondary' => round($totalInSecondary - $totalOutSecondary, 4),
            ],
        ];
    }

    /**
     * Alias for periodic balances.
     */
    public function getPeriodicStockBalances(
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $type = null,
        $categoryId = null
    ): array {
        return $this->getStockSummaryReport(
            fromDate: $fromDate,
            toDate: $toDate,
            type: $type,
            category: $categoryId
        );
    }

    /**
     * Alias for daily ledger.
     */
    public function getDailyLedger(int $itemId, ?string $fromDate = null, ?string $toDate = null): array
    {
        return $this->getItemLedgerReport(
            itemId: $itemId,
            fromDate: $fromDate,
            toDate: $toDate
        );
    }

    /**
     * Get list of batches/lots with available stock balances for a given item.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getItemBatchesWithStock(int $itemId): array
    {
        $item = Item::find($itemId);
        if (! $item) {
            return [];
        }

        $batches = [];
        $primaryUnit = $item->primary_unit ?: ($item->type === 'fg' ? 'cbm' : 'ton');
        $secondaryUnit = $item->secondary_unit ?: 'kgs';
        $factor = (float) $item->conversion_factor ?: 1.0;

        if ($item->type === 'fg') {
            $productions = Production::where('fg_item_id', $itemId)
                ->orderByDesc('production_date')
                ->orderByDesc('id')
                ->get();

            $grouped = $productions->groupBy(function ($p) {
                return trim($p->batch_no ?? '') !== '' ? trim($p->batch_no) : 'NO_BATCH';
            });

            foreach ($grouped as $batchNo => $prods) {
                $totalProducedCbm = (float) $prods->sum('cbm');
                $first = $prods->first();

                $batches[] = [
                    'batch_no' => $batchNo === 'NO_BATCH' ? '' : $batchNo,
                    'batch_label' => $batchNo === 'NO_BATCH' ? 'General Production (No Batch #)' : $batchNo,
                    'reference_no' => $first?->production_no ?? '',
                    'date' => $first?->production_date ? $first->production_date->format('Y-m-d') : '',
                    'source_location' => $first?->plant_name ?? 'Central Plant',
                    'initial_qty' => round($totalProducedCbm, 4),
                    'dispatched_qty' => 0.0,
                    'available_qty' => round($totalProducedCbm, 4),
                    'unit' => $primaryUnit,
                    'secondary_qty' => round($factor > 0 ? ($totalProducedCbm * $factor) : $totalProducedCbm, 4),
                    'secondary_unit' => $secondaryUnit,
                    'type' => 'fg',
                ];
            }
        }

        return $batches;
    }
}
