import type { Item } from '../types/item';

export interface UnitDefinition {
    code: string;
    label: string;
    category: 'mass' | 'volume' | 'count' | 'other';
    baseRatio: number; // Ratio relative to base unit (kg for mass, ltr for volume, unit for count)
}

export const STANDARD_UNITS: UnitDefinition[] = [
    // Mass units (base: kgs = 1)
    { code: 'ton', label: 'Metric Ton (ton)', category: 'mass', baseRatio: 1000 },
    { code: 'kgs', label: 'Kilograms (kgs)', category: 'mass', baseRatio: 1 },
    { code: 'kg', label: 'Kilogram (kg)', category: 'mass', baseRatio: 1 },
    { code: 'quintal', label: 'Quintal (100 kg)', category: 'mass', baseRatio: 100 },
    { code: 'gm', label: 'Grams (gm)', category: 'mass', baseRatio: 0.001 },
    { code: 'bag', label: 'Cement Bag (50 kg)', category: 'mass', baseRatio: 50 },

    // Volume units (base: ltr = 1)
    { code: 'cbm', label: 'Cubic Meter (cbm / m³)', category: 'volume', baseRatio: 1000 },
    { code: 'cum', label: 'Cubic Meter (cum)', category: 'volume', baseRatio: 1000 },
    { code: 'm3', label: 'Cubic Meter (m³)', category: 'volume', baseRatio: 1000 },
    { code: 'kl', label: 'Kiloliter (kl)', category: 'volume', baseRatio: 1000 },
    { code: 'ltr', label: 'Liters (ltr)', category: 'volume', baseRatio: 1 },
    { code: 'lt', label: 'Liter (lt)', category: 'volume', baseRatio: 1 },
    { code: 'ml', label: 'Milliliters (ml)', category: 'volume', baseRatio: 0.001 },
    { code: 'cft', label: 'Cubic Feet (cft)', category: 'volume', baseRatio: 28.316846592 },
    { code: 'drum', label: 'Drum (200 Ltr)', category: 'volume', baseRatio: 200 },

    // Count / Packaging
    { code: 'pcs', label: 'Pieces (pcs)', category: 'count', baseRatio: 1 },
    { code: 'nos', label: 'Numbers (nos)', category: 'count', baseRatio: 1 },
    { code: 'box', label: 'Box', category: 'count', baseRatio: 1 },
    { code: 'bundle', label: 'Bundle', category: 'count', baseRatio: 1 },
];

const normalize = (u?: string | null): string => {
    return (u || '').trim().toLowerCase();
};

const findStandardUnit = (unitStr?: string | null): UnitDefinition | undefined => {
    const norm = normalize(unitStr);
    if (!norm) return undefined;
    return STANDARD_UNITS.find((u) => u.code.toLowerCase() === norm);
};

/**
 * Converts quantity between any two units interactively.
 * Supports:
 * 1. Same dimensional standard conversions (e.g. ton <-> kgs, kgs <-> gm, cbm <-> ltr, bag <-> kgs, etc.)
 * 2. Item Master defined custom conversion factors (e.g. Item primary_unit <-> secondary_unit)
 * 3. Cross-dimensional conversions utilizing Item Master factor + standard conversions
 */
export function convertUnits(
    qty: number | string | null | undefined,
    fromUnit: string | null | undefined,
    toUnit: string | null | undefined,
    item?: Item | null
): number | null {
    const num = Number(qty);
    if (isNaN(num) || num === null || num === undefined) {
        return null;
    }

    const fromNorm = normalize(fromUnit);
    const toNorm = normalize(toUnit);

    if (!fromNorm || !toNorm) {
        return null;
    }

    // Identical units
    if (fromNorm === toNorm) {
        return num;
    }

    const itemPrim = normalize(item?.primary_unit);
    const itemSec = normalize(item?.secondary_unit);
    const itemFactor = item?.conversion_factor ? Number(item.conversion_factor) : 0;

    // 1. Direct Item Master custom conversion
    if (itemFactor > 0 && itemPrim && itemSec) {
        if (fromNorm === itemPrim && toNorm === itemSec) {
            return Number((num * itemFactor).toFixed(4));
        }
        if (fromNorm === itemSec && toNorm === itemPrim) {
            return Number((num / itemFactor).toFixed(4));
        }
    }

    const fromStd = findStandardUnit(fromNorm);
    const toStd = findStandardUnit(toNorm);

    // 2. Standard conversions in the same category (e.g. mass to mass, volume to volume)
    if (fromStd && toStd && fromStd.category === toStd.category) {
        const inBase = num * fromStd.baseRatio;
        const result = inBase / toStd.baseRatio;
        return Number(result.toFixed(4));
    }

    // 3. Cross-dimensional via Item Master bridge
    // If fromUnit can convert to item.primary_unit, and item converts to secondary_unit, which converts to toUnit:
    if (itemFactor > 0 && itemPrim && itemSec && fromStd && toStd) {
        const itemPrimStd = findStandardUnit(itemPrim);
        const itemSecStd = findStandardUnit(itemSec);

        // Case A: From is compatible with item.primary_unit, To is compatible with item.secondary_unit
        if (
            itemPrimStd &&
            itemSecStd &&
            fromStd.category === itemPrimStd.category &&
            toStd.category === itemSecStd.category
        ) {
            // fromUnit -> item.primary_unit
            const fromInPrimBase = num * fromStd.baseRatio;
            const inItemPrim = fromInPrimBase / itemPrimStd.baseRatio;
            // item.primary_unit -> item.secondary_unit
            const inItemSec = inItemPrim * itemFactor;
            // item.secondary_unit -> toUnit
            const inSecBase = inItemSec * itemSecStd.baseRatio;
            const result = inSecBase / toStd.baseRatio;
            return Number(result.toFixed(4));
        }

        // Case B: From is compatible with item.secondary_unit, To is compatible with item.primary_unit
        if (
            itemPrimStd &&
            itemSecStd &&
            fromStd.category === itemSecStd.category &&
            toStd.category === itemPrimStd.category
        ) {
            // fromUnit -> item.secondary_unit
            const fromInSecBase = num * fromStd.baseRatio;
            const inItemSec = fromInSecBase / itemSecStd.baseRatio;
            // item.secondary_unit -> item.primary_unit
            const inItemPrim = inItemSec / itemFactor;
            // item.primary_unit -> toUnit
            const inPrimBase = inItemPrim * itemPrimStd.baseRatio;
            const result = inPrimBase / toStd.baseRatio;
            return Number(result.toFixed(4));
        }
    }

    // Fallback: If item custom factor exists and one of the units matches
    if (itemFactor > 0) {
        if (fromNorm === 'ton' && toNorm === 'kgs') return Number((num * 1000).toFixed(4));
        if (fromNorm === 'kgs' && toNorm === 'ton') return Number((num / 1000).toFixed(4));
        if (fromNorm === 'cbm' && toNorm === 'ltr') return Number((num * 1000).toFixed(4));
        if (fromNorm === 'ltr' && toNorm === 'cbm') return Number((num / 1000).toFixed(4));
    }

    return null;
}

/**
 * Returns available unit options for a select dropdown, placing the item's primary & secondary units on top.
 */
export function getUnitOptions(item?: Item | null): Array<{ value: string; label: string }> {
    const list: Array<{ value: string; label: string }> = [];
    const seen = new Set<string>();

    if (item?.primary_unit) {
        const u = item.primary_unit.trim();
        list.push({ value: u, label: `${u} (Item Primary)` });
        seen.add(u.toLowerCase());
    }

    if (item?.secondary_unit) {
        const u = item.secondary_unit.trim();
        if (!seen.has(u.toLowerCase())) {
            list.push({ value: u, label: `${u} (Item Secondary)` });
            seen.add(u.toLowerCase());
        }
    }

    // Add standard units
    const standardCodes = ['ton', 'kgs', 'bag', 'quintal', 'gm', 'ltr', 'cbm', 'cft', 'ml', 'drum', 'pcs', 'nos'];
    for (const code of standardCodes) {
        if (!seen.has(code)) {
            const std = findStandardUnit(code);
            list.push({ value: code, label: std ? std.label : code });
            seen.add(code);
        }
    }

    return list;
}
