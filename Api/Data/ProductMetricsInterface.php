<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Api\Data;

interface ProductMetricsInterface
{
    public function getTotalActiveProducts(): int;
    public function setTotalActiveProducts(int $value): void;

    public function getOutOfStockCount(): int;
    public function setOutOfStockCount(int $value): void;

    public function getLowStockCount(): int;
    public function setLowStockCount(int $value): void;

    /**
     * Top N products by qty sold over the last 30 days.
     *
     * @return array<int, array{product_id: int, name: string, sku: string, qty_sold: float, revenue: float}>
     */
    public function getTopSellingProducts(): array;
    public function setTopSellingProducts(array $value): void;

    /**
     * Products below the low-stock threshold.
     * Each item includes `depletion_rate` (qty_sold_30d / current_qty) and
     * `anomaly` (true when depletion rate exceeds mean + 2σ across all low-stock items).
     *
     * @return array<int, array{product_id: int, name: string, sku: string, qty: float, threshold: int, depletion_rate: float, anomaly: bool}>
     */
    public function getLowStockProducts(): array;
    public function setLowStockProducts(array $value): void;

    /**
     * Revenue by top 5 categories for the last 30 days.
     *
     * @return array<int, array{category: string, revenue: float, percent: float}>
     */
    public function getRevenueByCategory(): array;
    public function setRevenueByCategory(array $value): void;
}
