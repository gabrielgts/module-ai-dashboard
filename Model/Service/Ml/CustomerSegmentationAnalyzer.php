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
     * @param array<int|string, array{recency: int, frequency: int, monetary: float, name: string, email: string}> $rfmRows
     * @return array{
     *     vip:     array{count: int, avg_ltv: float, customers: list<array>},
     *     active:  array{count: int, avg_ltv: float, customers: list<array>},
     *     at_risk: array{count: int, avg_ltv: float, customers: list<array>}
     * }
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
     * Recency is inverted: 0 days ago → 1.0, oldest → 0.0.
     *
     * @param array<int|string, array{recency: int, frequency: int, monetary: float}> $rfmRows
     * @return array<int|string, array{float, float, float}>
     */
    private function normalizeFeatures(array $rfmRows): array
    {
        $recencies   = array_column($rfmRows, 'recency');
        $frequencies = array_column($rfmRows, 'frequency');
        $monetaries  = array_column($rfmRows, 'monetary');

        $minR = (float) min($recencies);   $maxR = (float) max($recencies);
        $minF = (float) min($frequencies); $maxF = (float) max($frequencies);
        $minM = (float) min($monetaries);  $maxM = (float) max($monetaries);

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
     * Runs k-means with k-means++ initialisation and converges in at most MAX_ITERATIONS steps.
     *
     * @param array<int|string, array{float, float, float}> $points  Normalised feature vectors keyed by customer_id
     * @return array<int|string, int>  customer_id → cluster index (0 … K-1)
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
     * k-means++ centroid seed selection — spreads initial centroids for faster convergence.
     *
     * @param array<int, array{float, float, float}> $points
     * @return array<int, array{float, float, float}>
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
            $pick = (mt_rand() / mt_getrandmax()) * $sum;
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
     * Empty clusters are re-seeded with a random point to avoid degeneracy.
     *
     * @param array<int, array{float, float, float}> $points
     * @param array<int, int>                        $assignments
     * @return array<int, array{float, float, float}>
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

    /** @param array{float, float, float} $a @param array{float, float, float} $b */
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
     * Groups customers by cluster, then sorts clusters by avg LTV descending
     * to assign stable tier labels: VIP (highest LTV) → Active → At-Risk.
     *
     * @param array<int|string, int>  $assignments  customer_id → cluster index
     * @param array<int|string, array{recency: int, frequency: int, monetary: float, name: string, email: string}> $rfmRows
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

    /** @return array{vip: array, active: array, at_risk: array} */
    private function emptyResult(): array
    {
        $empty = ['count' => 0, 'avg_ltv' => 0.0, 'customers' => []];
        return ['vip' => $empty, 'active' => $empty, 'at_risk' => $empty];
    }
}
