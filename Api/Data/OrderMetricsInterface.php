<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Api\Data;

interface OrderMetricsInterface
{
    /**
     * Get today's order count.
     *
     * @return int
     */
    public function getTodayOrdersCount(): int;

    /**
     * Set today's order count.
     *
     * @param int $value
     * @return void
     */
    public function setTodayOrdersCount(int $value): void;

    /**
     * Get today's revenue.
     *
     * @return float
     */
    public function getTodayRevenue(): float;

    /**
     * Set today's revenue.
     *
     * @param float $value
     * @return void
     */
    public function setTodayRevenue(float $value): void;

    /**
     * Get this week's order count.
     *
     * @return int
     */
    public function getWeekOrdersCount(): int;

    /**
     * Set this week's order count.
     *
     * @param int $value
     * @return void
     */
    public function setWeekOrdersCount(int $value): void;

    /**
     * Get this week's revenue.
     *
     * @return float
     */
    public function getWeekRevenue(): float;

    /**
     * Set this week's revenue.
     *
     * @param float $value
     * @return void
     */
    public function setWeekRevenue(float $value): void;

    /**
     * Get this month's order count.
     *
     * @return int
     */
    public function getMonthOrdersCount(): int;

    /**
     * Set this month's order count.
     *
     * @param int $value
     * @return void
     */
    public function setMonthOrdersCount(int $value): void;

    /**
     * Get this month's revenue.
     *
     * @return float
     */
    public function getMonthRevenue(): float;

    /**
     * Set this month's revenue.
     *
     * @param float $value
     * @return void
     */
    public function setMonthRevenue(float $value): void;

    /**
     * Get revenue change vs. prior calendar month, e.g. 12.5 = +12.5%.
     *
     * @return float
     */
    public function getRevenueGrowthPercent(): float;

    /**
     * Set revenue growth percent.
     *
     * @param float $value
     * @return void
     */
    public function setRevenueGrowthPercent(float $value): void;

    /**
     * Get average order value for the current month.
     *
     * @return float
     */
    public function getAverageOrderValue(): float;

    /**
     * Set average order value.
     *
     * @param float $value
     * @return void
     */
    public function setAverageOrderValue(float $value): void;

    /**
     * Get order counts grouped by status.
     *
     * @return array<string, int>
     */
    public function getOrdersByStatus(): array;

    /**
     * Set order counts grouped by status.
     *
     * @param array $value
     * @return void
     */
    public function setOrdersByStatus(array $value): void;

    /**
     * Get daily revenue for the last N days.
     *
     * @return array<int, array{date: string, revenue: float, orders: int}>
     */
    public function getRevenueTrend(): array;

    /**
     * Set the daily revenue trend.
     *
     * @param array $value
     * @return void
     */
    public function setRevenueTrend(array $value): void;

    /**
     * Get the last N orders for the dashboard table.
     *
     * @return array<int, array{
     *     increment_id: string, customer_name: string,
     *     grand_total: float, status: string, created_at: string, items_count: int
     * }>
     */
    public function getRecentOrders(): array;

    /**
     * Set recent orders.
     *
     * @param array $value
     * @return void
     */
    public function setRecentOrders(array $value): void;

    /**
     * Get coupon usage summary for the current month with top coupons list.
     *
     * @return array<string, mixed>
     */
    public function getCouponMetrics(): array;

    /**
     * Set coupon metrics.
     *
     * @param array $value
     * @return void
     */
    public function setCouponMetrics(array $value): void;

    /**
     * Get OLS slope of the 30-day revenue trend.
     *
     * Positive means growing; negative means declining.
     *
     * @return float
     */
    public function getRevenueTrendSlope(): float;

    /**
     * Set revenue trend slope.
     *
     * @param float $value
     * @return void
     */
    public function setRevenueTrendSlope(float $value): void;
}
