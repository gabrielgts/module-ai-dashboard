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
     * Return the OLS slope of the values in $trendRows.
     *
     * @param array $trendRows Rows in date-ascending order
     * @param string $valueKey Key within each row holding the numeric metric
     * @return float
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
