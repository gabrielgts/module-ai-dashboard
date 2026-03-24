<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Api\Data;

interface CustomerMetricsInterface
{
    public function getNewCustomersToday(): int;
    public function setNewCustomersToday(int $value): void;

    public function getNewCustomersWeek(): int;
    public function setNewCustomersWeek(int $value): void;

    public function getNewCustomersMonth(): int;
    public function setNewCustomersMonth(int $value): void;

    public function getTotalRegisteredCustomers(): int;
    public function setTotalRegisteredCustomers(int $value): void;

    /** Percentage of customers who placed more than one order */
    public function getRepeatCustomerRate(): float;
    public function setRepeatCustomerRate(float $value): void;

    /**
     * Top 5 customers ranked by lifetime value.
     *
     * @return array<int, array{customer_id: int, name: string, email: string, total_orders: int, lifetime_value: float}>
     */
    public function getTopCustomersByLtv(): array;
    public function setTopCustomersByLtv(array $value): void;

    /**
     * New customer registrations per day for the last 30 days.
     *
     * @return array<int, array{date: string, count: int}>
     */
    public function getAcquisitionTrend(): array;
    public function setAcquisitionTrend(array $value): void;

    /**
     * OLS slope of the acquisition trend (positive = growing, negative = declining).
     * Computed by TrendSlopeAnalyzer during cron rebuild.
     */
    public function getAcquisitionTrendSlope(): float;
    public function setAcquisitionTrendSlope(float $value): void;

    /**
     * RFM-based customer tier segmentation (k-Means, k=3).
     *
     * @return array{
     *     vip:    array{count: int, avg_ltv: float, customers: array},
     *     active: array{count: int, avg_ltv: float, customers: array},
     *     at_risk: array{count: int, avg_ltv: float, customers: array}
     * }
     */
    public function getSegments(): array;
    public function setSegments(array $value): void;
}
