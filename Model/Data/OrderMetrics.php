<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Data;

use Gtstudio\AiDashboard\Api\Data\OrderMetricsInterface;

class OrderMetrics implements OrderMetricsInterface
{
    /** @var int */
    private int $todayOrdersCount = 0;
    /** @var float */
    private float $todayRevenue = 0.0;
    /** @var int */
    private int $weekOrdersCount = 0;
    /** @var float */
    private float $weekRevenue = 0.0;
    /** @var int */
    private int $monthOrdersCount = 0;
    /** @var float */
    private float $monthRevenue = 0.0;
    /** @var float */
    private float $revenueGrowthPercent = 0.0;
    /** @var float */
    private float $averageOrderValue = 0.0;
    /** @var array<string, int> */
    private array $ordersByStatus = [];
    /** @var array<int, array<string, mixed>> */
    private array $revenueTrend = [];
    /** @var array<int, array<string, mixed>> */
    private array $recentOrders = [];
    /** @var array<string, mixed> */
    private array $couponMetrics = [];
    /** @var float */
    private float $revenueTrendSlope = 0.0;

    /**
     * Get today orders count.
     *
     * @return int
     */
    public function getTodayOrdersCount(): int
    {
        return $this->todayOrdersCount;
    }

    /**
     * Set today orders count.
     *
     * @param int $value
     * @return void
     */
    public function setTodayOrdersCount(int $value): void
    {
        $this->todayOrdersCount = $value;
    }

    /**
     * Get today revenue.
     *
     * @return float
     */
    public function getTodayRevenue(): float
    {
        return $this->todayRevenue;
    }

    /**
     * Set today revenue.
     *
     * @param float $value
     * @return void
     */
    public function setTodayRevenue(float $value): void
    {
        $this->todayRevenue = $value;
    }

    /**
     * Get this week orders count.
     *
     * @return int
     */
    public function getWeekOrdersCount(): int
    {
        return $this->weekOrdersCount;
    }

    /**
     * Set this week orders count.
     *
     * @param int $value
     * @return void
     */
    public function setWeekOrdersCount(int $value): void
    {
        $this->weekOrdersCount = $value;
    }

    /**
     * Get this week revenue.
     *
     * @return float
     */
    public function getWeekRevenue(): float
    {
        return $this->weekRevenue;
    }

    /**
     * Set this week revenue.
     *
     * @param float $value
     * @return void
     */
    public function setWeekRevenue(float $value): void
    {
        $this->weekRevenue = $value;
    }

    /**
     * Get this month orders count.
     *
     * @return int
     */
    public function getMonthOrdersCount(): int
    {
        return $this->monthOrdersCount;
    }

    /**
     * Set this month orders count.
     *
     * @param int $value
     * @return void
     */
    public function setMonthOrdersCount(int $value): void
    {
        $this->monthOrdersCount = $value;
    }

    /**
     * Get this month revenue.
     *
     * @return float
     */
    public function getMonthRevenue(): float
    {
        return $this->monthRevenue;
    }

    /**
     * Set this month revenue.
     *
     * @param float $value
     * @return void
     */
    public function setMonthRevenue(float $value): void
    {
        $this->monthRevenue = $value;
    }

    /**
     * Get revenue growth percent.
     *
     * @return float
     */
    public function getRevenueGrowthPercent(): float
    {
        return $this->revenueGrowthPercent;
    }

    /**
     * Set revenue growth percent.
     *
     * @param float $value
     * @return void
     */
    public function setRevenueGrowthPercent(float $value): void
    {
        $this->revenueGrowthPercent = $value;
    }

    /**
     * Get average order value.
     *
     * @return float
     */
    public function getAverageOrderValue(): float
    {
        return $this->averageOrderValue;
    }

    /**
     * Set average order value.
     *
     * @param float $value
     * @return void
     */
    public function setAverageOrderValue(float $value): void
    {
        $this->averageOrderValue = $value;
    }

    /**
     * Get orders grouped by status.
     *
     * @return array<string, int>
     */
    public function getOrdersByStatus(): array
    {
        return $this->ordersByStatus;
    }

    /**
     * Set orders grouped by status.
     *
     * @param array $value
     * @return void
     */
    public function setOrdersByStatus(array $value): void
    {
        $this->ordersByStatus = $value;
    }

    /**
     * Get revenue trend data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRevenueTrend(): array
    {
        return $this->revenueTrend;
    }

    /**
     * Set revenue trend data.
     *
     * @param array $value
     * @return void
     */
    public function setRevenueTrend(array $value): void
    {
        $this->revenueTrend = $value;
    }

    /**
     * Get recent orders.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentOrders(): array
    {
        return $this->recentOrders;
    }

    /**
     * Set recent orders.
     *
     * @param array $value
     * @return void
     */
    public function setRecentOrders(array $value): void
    {
        $this->recentOrders = $value;
    }

    /**
     * Get coupon metrics.
     *
     * @return array<string, mixed>
     */
    public function getCouponMetrics(): array
    {
        return $this->couponMetrics;
    }

    /**
     * Set coupon metrics.
     *
     * @param array $value
     * @return void
     */
    public function setCouponMetrics(array $value): void
    {
        $this->couponMetrics = $value;
    }

    /**
     * Get revenue trend slope.
     *
     * @return float
     */
    public function getRevenueTrendSlope(): float
    {
        return $this->revenueTrendSlope;
    }

    /**
     * Set revenue trend slope.
     *
     * @param float $value
     * @return void
     */
    public function setRevenueTrendSlope(float $value): void
    {
        $this->revenueTrendSlope = $value;
    }
}
