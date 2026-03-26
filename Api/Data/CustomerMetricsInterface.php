<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Api\Data;

interface CustomerMetricsInterface
{
    /**
     * Get new customer registrations today.
     *
     * @return int
     */
    public function getNewCustomersToday(): int;

    /**
     * Set new customers today count.
     *
     * @param int $value
     * @return void
     */
    public function setNewCustomersToday(int $value): void;

    /**
     * Get new customer registrations this week.
     *
     * @return int
     */
    public function getNewCustomersWeek(): int;

    /**
     * Set new customers this week count.
     *
     * @param int $value
     * @return void
     */
    public function setNewCustomersWeek(int $value): void;

    /**
     * Get new customer registrations this month.
     *
     * @return int
     */
    public function getNewCustomersMonth(): int;

    /**
     * Set new customers this month count.
     *
     * @param int $value
     * @return void
     */
    public function setNewCustomersMonth(int $value): void;

    /**
     * Get total number of active registered customers.
     *
     * @return int
     */
    public function getTotalRegisteredCustomers(): int;

    /**
     * Set total registered customers count.
     *
     * @param int $value
     * @return void
     */
    public function setTotalRegisteredCustomers(int $value): void;

    /**
     * Get percentage of customers who placed more than one order.
     *
     * @return float
     */
    public function getRepeatCustomerRate(): float;

    /**
     * Set repeat customer rate.
     *
     * @param float $value
     * @return void
     */
    public function setRepeatCustomerRate(float $value): void;

    /**
     * Get top customers ranked by lifetime value.
     *
     * @return array<int, array{
     *     customer_id: int, name: string, email: string,
     *     total_orders: int, lifetime_value: float
     * }>
     */
    public function getTopCustomersByLtv(): array;

    /**
     * Set top customers by LTV.
     *
     * @param array $value
     * @return void
     */
    public function setTopCustomersByLtv(array $value): void;

    /**
     * Get new customer registrations per day for the last N days.
     *
     * @return array<int, array{date: string, count: int}>
     */
    public function getAcquisitionTrend(): array;

    /**
     * Set acquisition trend data.
     *
     * @param array $value
     * @return void
     */
    public function setAcquisitionTrend(array $value): void;

    /**
     * Get OLS slope of the acquisition trend.
     *
     * Positive means growing; negative means declining.
     *
     * @return float
     */
    public function getAcquisitionTrendSlope(): float;

    /**
     * Set acquisition trend slope.
     *
     * @param float $value
     * @return void
     */
    public function setAcquisitionTrendSlope(float $value): void;

    /**
     * Get RFM-based customer tier segmentation (k-Means, k=3).
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSegments(): array;

    /**
     * Set customer segmentation data.
     *
     * @param array $value
     * @return void
     */
    public function setSegments(array $value): void;
}
