<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Data;

use Gtstudio\AiDashboard\Api\Data\OrderMetricsInterface;

class OrderMetrics implements OrderMetricsInterface
{
    private int $todayOrdersCount = 0;
    private float $todayRevenue = 0.0;
    private int $weekOrdersCount = 0;
    private float $weekRevenue = 0.0;
    private int $monthOrdersCount = 0;
    private float $monthRevenue = 0.0;
    private float $revenueGrowthPercent = 0.0;
    private float $averageOrderValue = 0.0;
    private array $ordersByStatus = [];
    private array $revenueTrend = [];
    private array $recentOrders = [];
    private array $couponMetrics = [];
    private float $revenueTrendSlope = 0.0;

    public function getTodayOrdersCount(): int { return $this->todayOrdersCount; }
    public function setTodayOrdersCount(int $value): void { $this->todayOrdersCount = $value; }

    public function getTodayRevenue(): float { return $this->todayRevenue; }
    public function setTodayRevenue(float $value): void { $this->todayRevenue = $value; }

    public function getWeekOrdersCount(): int { return $this->weekOrdersCount; }
    public function setWeekOrdersCount(int $value): void { $this->weekOrdersCount = $value; }

    public function getWeekRevenue(): float { return $this->weekRevenue; }
    public function setWeekRevenue(float $value): void { $this->weekRevenue = $value; }

    public function getMonthOrdersCount(): int { return $this->monthOrdersCount; }
    public function setMonthOrdersCount(int $value): void { $this->monthOrdersCount = $value; }

    public function getMonthRevenue(): float { return $this->monthRevenue; }
    public function setMonthRevenue(float $value): void { $this->monthRevenue = $value; }

    public function getRevenueGrowthPercent(): float { return $this->revenueGrowthPercent; }
    public function setRevenueGrowthPercent(float $value): void { $this->revenueGrowthPercent = $value; }

    public function getAverageOrderValue(): float { return $this->averageOrderValue; }
    public function setAverageOrderValue(float $value): void { $this->averageOrderValue = $value; }

    public function getOrdersByStatus(): array { return $this->ordersByStatus; }
    public function setOrdersByStatus(array $value): void { $this->ordersByStatus = $value; }

    public function getRevenueTrend(): array { return $this->revenueTrend; }
    public function setRevenueTrend(array $value): void { $this->revenueTrend = $value; }

    public function getRecentOrders(): array { return $this->recentOrders; }
    public function setRecentOrders(array $value): void { $this->recentOrders = $value; }

    public function getCouponMetrics(): array { return $this->couponMetrics; }
    public function setCouponMetrics(array $value): void { $this->couponMetrics = $value; }

    public function getRevenueTrendSlope(): float { return $this->revenueTrendSlope; }
    public function setRevenueTrendSlope(float $value): void { $this->revenueTrendSlope = $value; }
}
