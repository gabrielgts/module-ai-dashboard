<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Service\Ml;

/**
 * Segments customers into VIP / Active / At-Risk tiers using k-Means (k=3) on RFM data.
 *
 * Uses a self-contained k-means implementation instead of php-ai/php-ml's KMeans class,
 * which relies on Phpml\Clustering\KMeans\Point implementing ArrayAccess without PHP 8.x
 * compatible return-type declarations — a fatal error under Magento's strict error handler.
 *
 * Input:  array keyed by customer_id → ['recency' => days, 'frequency' => int, 'monetary' => float]
 * Output: ['vip' => [...], 'active' => [...], 'at_risk' => [...]]
 *         Each tier: count, avg_ltv, customers list
 *
 * Features are min-max normalised before clustering so that LTV does not dominate.
 * Recency is inverted (fewer days since last order = higher score) so that all three
 * features point in the same "good customer" direction.
 *
 * Called only during full cron rebuild — never in web request paths.
 */
class CustomerSegmentationAnalyzer
{
    private const K              = 3;
    private const MIN_CUSTOMERS  = 3;
    private const MAX_ITERATIONS = 100;

    /**
     * Segment customers into VIP, Active, and At-Risk tiers using k-Means.
     *
     * @param array $rfmRows Keyed by customer_id with recency/frequency/monetary/name/email
     * @return array{vip: array, active: array, at_risk: array}
     */
    public function segment(array $rfmRows): array
    {
        if (count($rfmRows) < self::MIN_CUSTOMERS) {
            return $this->emptyResult();
        }

        $normalized  = $this->normalizeFeatures($rfmRows);
        $assignments = $this->runKMeans($normalized);

        return $this->buildResult($assignments, $rfmRows);
    }

    // ── Feature normalisation ─────────────────────────────────────────────────

    /**
     * Min-max normalise each RFM dimension to [0, 1].
     *
     * Recency is inverted: 0 days ago → 1.0, oldest → 0.0.
     *
     * @param array $rfmRows Keyed by customer_id with recency/frequency/monetary fields
     * @return array
     */
    private function normalizeFeatures(array $rfmRows): array
    {
        $recencies   = array_column($rfmRows, 'recency');
        $frequencies = array_column($rfmRows, 'frequency');
        $monetaries  = array_column($rfmRows, 'monetary');

        $minR = (float) min($recencies);
        $maxR = (float) max($recencies);
        $minF = (float) min($frequencies);
        $maxF = (float) max($frequencies);
        $minM = (float) min($monetaries);
        $maxM = (float) max($monetaries);

        $rangeR = $maxR - $minR;
        $rangeF = $maxF - $minF;
        $rangeM = $maxM - $minM;

        $normalized = [];
        foreach ($rfmRows as $customerId => $row) {
            // Invert recency so "ordered today" maps to 1.0, "ordered long ago" to 0.0
            $r = $rangeR > 0.0 ? 1.0 - (((float) $row['recency'] - $minR) / $rangeR) : 0.5;
            $f = $rangeF > 0.0 ? ((float) $row['frequency'] - $minF) / $rangeF : 0.5;
            $m = $rangeM > 0.0 ? ((float) $row['monetary'] - $minM) / $rangeM : 0.5;

            $normalized[$customerId] = [$r, $f, $m];
        }

        return $normalized;
    }

    // ── k-Means ───────────────────────────────────────────────────────────────

    /**
     * Run k-means with k-means++ initialisation, converging in at most MAX_ITERATIONS steps.
     *
     * @param array $points Normalised feature vectors keyed by customer_id
     * @return array Customer_id to cluster index (0 to K-1)
     */
    private function runKMeans(array $points): array
    {
        $ids    = array_keys($points);
        $coords = array_values($points);

        $centroids   = $this->initCentroidsKMeansPP($coords);
        $assignments = array_fill(0, count($coords), 0);

        for ($iter = 0; $iter < self::MAX_ITERATIONS; $iter++) {
            $changed = false;

            foreach ($coords as $i => $point) {
                $best  = 0;
                $bestD = PHP_FLOAT_MAX;
                foreach ($centroids as $k => $centroid) {
                    $d = $this->squaredDistance($point, $centroid);
                    if ($d < $bestD) {
                        $bestD = $d;
                        $best  = $k;
                    }
                }
                if ($assignments[$i] !== $best) {
                    $assignments[$i] = $best;
                    $changed = true;
                }
            }

            if (!$changed) {
                break;
            }

            $centroids = $this->recomputeCentroids($coords, $assignments);
        }

        $result = [];
        foreach ($ids as $i => $customerId) {
            $result[$customerId] = $assignments[$i];
        }

        return $result;
    }

    /**
     * Select initial centroids using k-means++ to spread them for faster convergence.
     *
     * @param array $points
     * @return array
     */
    private function initCentroidsKMeansPP(array $points): array
    {
        $centroids = [$points[array_rand($points)]];

        while (count($centroids) < self::K) {
            $distances = [];
            foreach ($points as $point) {
                $minD = PHP_FLOAT_MAX;
                foreach ($centroids as $centroid) {
                    $minD = min($minD, $this->squaredDistance($point, $centroid));
                }
                $distances[] = $minD;
            }

            // Weighted random pick proportional to squared distance from nearest centroid
            $sum  = (float) array_sum($distances);
            $pick = (random_int(0, PHP_INT_MAX) / PHP_INT_MAX) * $sum;
            foreach ($distances as $i => $d) {
                $pick -= $d;
                if ($pick <= 0.0) {
                    $centroids[] = $points[$i];
                    break;
                }
            }
        }

        return $centroids;
    }

    /**
     * Recompute each centroid as the mean of its assigned points.
     *
     * Empty clusters are re-seeded with a random point to avoid degeneracy.
     *
     * @param array $points
     * @param array $assignments
     * @return array
     */
    private function recomputeCentroids(array $points, array $assignments): array
    {
        $dims   = count($points[0]);
        $sums   = array_fill(0, self::K, array_fill(0, $dims, 0.0));
        $counts = array_fill(0, self::K, 0);

        foreach ($points as $i => $point) {
            $k = $assignments[$i];
            $counts[$k]++;
            foreach ($point as $d => $val) {
                $sums[$k][$d] += $val;
            }
        }

        $centroids = [];
        for ($k = 0; $k < self::K; $k++) {
            if ($counts[$k] === 0) {
                $centroids[$k] = $points[array_rand($points)];
            } else {
                $centroid = [];
                foreach ($sums[$k] as $d => $sum) {
                    $centroid[$d] = $sum / $counts[$k];
                }
                $centroids[$k] = $centroid;
            }
        }

        return $centroids;
    }

    /**
     * Compute squared Euclidean distance between two points.
     *
     * @param array $a
     * @param array $b
     * @return float
     */
    private function squaredDistance(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $i => $val) {
            $diff = $val - $b[$i];
            $sum += $diff * $diff;
        }
        return $sum;
    }

    // ── Result assembly ───────────────────────────────────────────────────────

    /**
     * Group customers by cluster and assign tier labels by avg LTV descending.
     *
     * Sorts clusters by avg LTV: VIP (highest) → Active → At-Risk.
     *
     * @param array $assignments Customer_id to cluster index
     * @param array $rfmRows Keyed by customer_id with recency/frequency/monetary/name/email
     * @return array{vip: array, active: array, at_risk: array}
     */
    private function buildResult(array $assignments, array $rfmRows): array
    {
        $byCluster = array_fill(0, self::K, []);
        foreach ($assignments as $customerId => $clusterIdx) {
            $byCluster[$clusterIdx][] = $customerId;
        }

        $tierData = [];
        foreach ($byCluster as $customerIds) {
            $totalLtv  = 0.0;
            $customers = [];
            foreach ($customerIds as $customerId) {
                $rfm       = $rfmRows[$customerId] ?? [];
                $totalLtv += (float) ($rfm['monetary'] ?? 0.0);
                $customers[] = [
                    'customer_id' => $customerId,
                    'recency'     => (int)    ($rfm['recency']   ?? 0),
                    'frequency'   => (int)    ($rfm['frequency'] ?? 0),
                    'monetary'    => (float)  ($rfm['monetary']  ?? 0.0),
                    'name'        => (string) ($rfm['name']      ?? ''),
                    'email'       => (string) ($rfm['email']     ?? ''),
                ];
            }
            $avgLtv    = count($customerIds) > 0 ? $totalLtv / count($customerIds) : 0.0;
            $tierData[] = ['avg_ltv' => $avgLtv, 'customers' => $customers];
        }

        usort($tierData, static fn (array $a, array $b): int => $b['avg_ltv'] <=> $a['avg_ltv']);

        $result = [];
        foreach (['vip', 'active', 'at_risk'] as $i => $tier) {
            $data          = $tierData[$i] ?? ['avg_ltv' => 0.0, 'customers' => []];
            $result[$tier] = [
                'count'     => count($data['customers']),
                'avg_ltv'   => round($data['avg_ltv'], 2),
                'customers' => $data['customers'],
            ];
        }

        return $result;
    }

    /**
     * Return an empty segmentation result with zero counts for all tiers.
     *
     * @return array{vip: array, active: array, at_risk: array}
     */
    private function emptyResult(): array
    {
        $empty = ['count' => 0, 'avg_ltv' => 0.0, 'customers' => []];
        return ['vip' => $empty, 'active' => $empty, 'at_risk' => $empty];
    }
}
