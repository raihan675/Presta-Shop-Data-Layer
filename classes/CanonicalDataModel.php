<?php
/**
 * CanonicalDataModel: Enforces strict canonical GA4 ecommerce schemas across all events.
 * Guarantees consistent parameter names, numeric types, and field boundaries.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class CanonicalDataModel
{
    /**
     * Formats and validates a canonical item according to GA4 ecommerce specifications.
     *
     * @param array $raw
     * @return array
     */
    public static function formatItem(array $raw)
    {
        $itemId = isset($raw['item_id']) ? trim((string)$raw['item_id']) : '';
        $rawVariantId = isset($raw['variant_id']) ? trim((string)$raw['variant_id']) : '';

        // Strict variant_id validation: only genuine combinations (> 0) receive variant_id
        $variantId = ($rawVariantId !== '' && $rawVariantId !== '0' && (int)$rawVariantId > 0) 
            ? $rawVariantId 
            : '';

        $price = isset($raw['price']) ? (float)$raw['price'] : 0.0;
        $quantity = isset($raw['quantity']) ? (int)$raw['quantity'] : 1;
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $item = [
            'item_id' => $itemId,
            'variant_id' => $variantId,
            'sku_id' => isset($raw['sku_id']) ? trim((string)$raw['sku_id']) : '',
            'item_name' => isset($raw['item_name']) ? trim((string)$raw['item_name']) : 'Product',
            'item_brand' => isset($raw['item_brand']) ? trim((string)$raw['item_brand']) : '',
            'item_category' => isset($raw['item_category']) ? trim((string)$raw['item_category']) : '',
            'item_variant' => isset($raw['item_variant']) ? trim((string)$raw['item_variant']) : '',
            'price' => $price,
            'quantity' => $quantity,
        ];

        if (isset($raw['index']) && is_numeric($raw['index'])) {
            $item['index'] = (int)$raw['index'];
        }

        if (!empty($raw['item_list_id'])) {
            $item['item_list_id'] = trim((string)$raw['item_list_id']);
        }

        if (!empty($raw['item_list_name'])) {
            $item['item_list_name'] = trim((string)$raw['item_list_name']);
        }

        if (!empty($raw['coupon'])) {
            $item['coupon'] = trim((string)$raw['coupon']);
        }

        if (isset($raw['discount']) && is_numeric($raw['discount'])) {
            $item['discount'] = (float)$raw['discount'];
        }

        return $item;
    }

    /**
     * Calculates the exact monetary value of an array of canonical items.
     *
     * @param array $items
     * @param int $precision
     * @return float
     */
    public static function calculateItemsValue(array $items, $precision = 2)
    {
        $total = 0.0;
        foreach ($items as $item) {
            $price = isset($item['price']) ? (float)$item['price'] : 0.0;
            $qty = isset($item['quantity']) ? (int)$item['quantity'] : 1;
            $total += $price * $qty;
        }

        return round($total, (int)$precision, PHP_ROUND_HALF_UP);
    }
}
