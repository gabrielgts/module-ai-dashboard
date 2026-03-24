<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Service\Ml;

use Phpml\Math\Statistic\Mean;
use Phpml\Math\Statistic\StandardDeviation;

/**
 * Flags low-stock products whose depletion rate is statistically anomalous.
 *
 * Depletion rate = qty_sold_30d / current_qty.
 * Products more than STDDEV_THRESHOLD standard deviations above the mean
 * depletion rate receive anomaly=true and are labelled "FAST DRAIN" in the UI.
 *
 * Uses only data already collected by ProductMetricsCollector (no extra queries).
 * Called only during cron rebuild — never in web request paths.
 */
class StockAnomalyAnalyzer
{
    private const STDDEV_THRESHOLD = 2.0;

    /**
     * @param array<int, array{product_id: int, name: string, sku: string, qty: float, threshold: int}> $lowStockItems
     * @param array<int, array{product_id: int, qty_sold: float, revenue: float, ...}>                  $topSellers
     * @return array<int, array{product_id: int, name: string, sku: string, qty: float, threshold: int, depletion_rate: float, anomaly: bool}>
     */
    public function flag(array $lowStockItems, array $topSellers): array
    {
        if (empty($lowStockItems)) {
            return [];
        }

        // Build a qty_sold lookup from top-sellers (keyed by product_id)
        $qtySoldIndex = [];
        foreach ($topSellers as $seller) {
            $qtySoldIndex[(int) $seller['product_id']] = (float) $seller['qty_sold'];
        }

        // Compute a depletion rate for every low-stock item
        $rates = [];
        foreach ($lowStockItems as $i => $item) {
            $currentQty = (float) $item['qty'];
            $qtySold    = $qtySoldIndex[(int) $item['product_id']] ?? 0.0;
            // Only meaningful when we actually have stock left to drain
            $rates[$i]  = $currentQty > 0 ? $qtySold / $currentQty : 0.0;
        }

        $result = [];

        if (count($rates) < 2) {
            // Cannot compute a meaningful stddev with a single item
            foreach ($lowStockItems as $i => $item) {
                $result[] = array_merge($item, ['depletion_rate' => round($rates[$i], 4), 'anomaly' => false]);
            }
            return $result;
        }

        $rateValues = array_values($rates);
        $mean       = Mean::arithmetic($rateValues);
        $stddev     = StandardDeviation::population($rateValues);
        $threshold  = $mean + (self::STDDEV_THRESHOLD * $stddev);

        foreach ($lowStockItems as $i => $item) {
            $rate   = $rates[$i];
            $result[] = array_merge($item, [
                'depletion_rate' => round($rate, 4),
                'anomaly'        => $stddev > 0.0 && $rate > $threshold,
            ]);
        }

        return $result;
    }
}
