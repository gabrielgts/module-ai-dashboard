<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Data;

use Gtstudio\AiDashboard\Api\Data\ProductMetricsInterface;

class ProductMetrics implements ProductMetricsInterface
{
    /** @var int */
    private int $totalActiveProducts = 0;
    /** @var int */
    private int $outOfStockCount = 0;
    /** @var int */
    private int $lowStockCount = 0;
    /** @var array<int, array<string, mixed>> */
    private array $topSellingProducts = [];
    /** @var array<int, array<string, mixed>> */
    private array $lowStockProducts = [];
    /** @var array<int, array<string, mixed>> */
    private array $revenueByCategory = [];

    /**
     * Get total active products count.
     *
     * @return int
     */
    public function getTotalActiveProducts(): int
    {
        return $this->totalActiveProducts;
    }

    /**
     * Set total active products count.
     *
     * @param int $value
     * @return void
     */
    public function setTotalActiveProducts(int $value): void
    {
        $this->totalActiveProducts = $value;
    }

    /**
     * Get out-of-stock products count.
     *
     * @return int
     */
    public function getOutOfStockCount(): int
    {
        return $this->outOfStockCount;
    }

    /**
     * Set out-of-stock products count.
     *
     * @param int $value
     * @return void
     */
    public function setOutOfStockCount(int $value): void
    {
        $this->outOfStockCount = $value;
    }

    /**
     * Get low-stock products count.
     *
     * @return int
     */
    public function getLowStockCount(): int
    {
        return $this->lowStockCount;
    }

    /**
     * Set low-stock products count.
     *
     * @param int $value
     * @return void
     */
    public function setLowStockCount(int $value): void
    {
        $this->lowStockCount = $value;
    }

    /**
     * Get top-selling products.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopSellingProducts(): array
    {
        return $this->topSellingProducts;
    }

    /**
     * Set top-selling products.
     *
     * @param array $value
     * @return void
     */
    public function setTopSellingProducts(array $value): void
    {
        $this->topSellingProducts = $value;
    }

    /**
     * Get low-stock products with depletion data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLowStockProducts(): array
    {
        return $this->lowStockProducts;
    }

    /**
     * Set low-stock products with depletion data.
     *
     * @param array $value
     * @return void
     */
    public function setLowStockProducts(array $value): void
    {
        $this->lowStockProducts = $value;
    }

    /**
     * Get revenue by category.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRevenueByCategory(): array
    {
        return $this->revenueByCategory;
    }

    /**
     * Set revenue by category.
     *
     * @param array $value
     * @return void
     */
    public function setRevenueByCategory(array $value): void
    {
        $this->revenueByCategory = $value;
    }
}
