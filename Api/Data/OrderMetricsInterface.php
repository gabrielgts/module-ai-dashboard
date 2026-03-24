<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Api\Data;

interface OrderMetricsInterface
{
    public function getTodayOrdersCount(): int;
    public function setTodayOrdersCount(int $value): void;

    public function getTodayRevenue(): float;
    public function setTodayRevenue(float $value): void;

    public function getWeekOrdersCount(): int;
    public function setWeekOrdersCount(int $value): void;

    public function getWeekRevenue(): float;
    public function setWeekRevenue(float $value): void;

    public function getMonthOrdersCount(): int;
    public function setMonthOrdersCount(int $value): void;

    public function getMonthRevenue(): float;
    public function setMonthRevenue(float $value): void;

    /** Revenue change vs. prior calendar month. e.g. 12.5 = +12.5% */
    public function getRevenueGrowthPercent(): float;
    public function setRevenueGrowthPercent(float $value): void;

    public function getAverageOrderValue(): float;
    public function setAverageOrderValue(float $value): void;

    /** @return array<string, int> e.g. ['pending' => 5, 'complete' => 87] */
    public function getOrdersByStatus(): array;
    public function setOrdersByStatus(array $value): void;

    /**
     * Daily revenue for the last N days.
     *
     * @return array<int, array{date: string, revenue: float, orders: int}>
     */
    public function getRevenueTrend(): array;
    public function setRevenueTrend(array $value): void;

    /**
     * Last N orders.
     *
     * @return array<int, array{increment_id: string, customer_name: string, grand_total: float, status: string, created_at: string, items_count: int}>
     */
    public function getRecentOrders(): array;
    public function setRecentOrders(array $value): void;

    /**
     * Coupon usage summary for the current month with top coupons list.
     *
     * @return array{used_today: int, used_month: int, total_discount_month: float, top_coupons: array<int, array{code: string, uses: int, total_discount: float}>}
     */
    public function getCouponMetrics(): array;
    public function setCouponMetrics(array $value): void;

    /**
     * OLS slope of the 30-day revenue trend (positive = growing, negative = declining).
     * Computed by TrendSlopeAnalyzer during cron rebuild.
     */
    public function getRevenueTrendSlope(): float;
    public function setRevenueTrendSlope(float $value): void;
}
