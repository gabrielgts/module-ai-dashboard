<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Service\Ml;

use Phpml\Regression\LeastSquares;

/**
 * Computes the OLS slope of a time-series trend array.
 *
 * A positive slope means the metric is trending up; negative means declining.
 * Uses a simple 1-feature LeastSquares regression with day-index as X.
 * Called only during cron rebuilds — never in web request paths.
 */
class TrendSlopeAnalyzer
{
    /**
     * Returns the OLS slope of the values in $trendRows.
     *
     * @param array<int, array<string, mixed>> $trendRows Rows already in date-ascending order
     *                                                    (e.g. [['date'=>'...','revenue'=>123.0], ...])
     * @param string $valueKey Key within each row that holds the numeric metric
     * @return float Slope coefficient; 0.0 when fewer than 2 rows are provided
     */
    public function slope(array $trendRows, string $valueKey = 'revenue'): float
    {
        $rows = array_values($trendRows);

        if (count($rows) < 2) {
            return 0.0;
        }

        $samples = [];
        $targets = [];
        foreach ($rows as $i => $row) {
            $samples[] = [(float) $i];
            $targets[] = (float) ($row[$valueKey] ?? 0.0);
        }

        $lr = new LeastSquares();
        $lr->train($samples, $targets);
        $coeffs = $lr->getCoefficients();

        return round((float) ($coeffs[0] ?? 0.0), 4);
    }
}
