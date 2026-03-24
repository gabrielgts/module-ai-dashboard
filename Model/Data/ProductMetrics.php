<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Data;

use Gtstudio\AiDashboard\Api\Data\ProductMetricsInterface;

class ProductMetrics implements ProductMetricsInterface
{
    private int $totalActiveProducts = 0;
    private int $outOfStockCount = 0;
    private int $lowStockCount = 0;
    private array $topSellingProducts = [];
    private array $lowStockProducts = [];
    private array $revenueByCategory = [];

    public function getTotalActiveProducts(): int { return $this->totalActiveProducts; }
    public function setTotalActiveProducts(int $value): void { $this->totalActiveProducts = $value; }

    public function getOutOfStockCount(): int { return $this->outOfStockCount; }
    public function setOutOfStockCount(int $value): void { $this->outOfStockCount = $value; }

    public function getLowStockCount(): int { return $this->lowStockCount; }
    public function setLowStockCount(int $value): void { $this->lowStockCount = $value; }

    public function getTopSellingProducts(): array { return $this->topSellingProducts; }
    public function setTopSellingProducts(array $value): void { $this->topSellingProducts = $value; }

    public function getLowStockProducts(): array { return $this->lowStockProducts; }
    public function setLowStockProducts(array $value): void { $this->lowStockProducts = $value; }

    public function getRevenueByCategory(): array { return $this->revenueByCategory; }
    public function setRevenueByCategory(array $value): void { $this->revenueByCategory = $value; }
}
