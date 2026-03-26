<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Api\Data;

interface ProductMetricsInterface
{
    /**
     * Get total number of active products.
     *
     * @return int
     */
    public function getTotalActiveProducts(): int;

    /**
     * Set total active products count.
     *
     * @param int $value
     * @return void
     */
    public function setTotalActiveProducts(int $value): void;

    /**
     * Get number of out-of-stock products.
     *
     * @return int
     */
    public function getOutOfStockCount(): int;

    /**
     * Set out-of-stock count.
     *
     * @param int $value
     * @return void
     */
    public function setOutOfStockCount(int $value): void;

    /**
     * Get number of products below the low-stock threshold.
     *
     * @return int
     */
    public function getLowStockCount(): int;

    /**
     * Set low-stock count.
     *
     * @param int $value
     * @return void
     */
    public function setLowStockCount(int $value): void;

    /**
     * Get top N products by qty sold over the last 30 days.
     *
     * @return array<int, array{
     *     product_id: int, name: string, sku: string, qty_sold: float, revenue: float
     * }>
     */
    public function getTopSellingProducts(): array;

    /**
     * Set top-selling products.
     *
     * @param array $value
     * @return void
     */
    public function setTopSellingProducts(array $value): void;

    /**
     * Get products below the low-stock threshold with depletion rate and anomaly flag.
     *
     * @return array<int, array{
     *     product_id: int, name: string, sku: string,
     *     qty: float, threshold: int, depletion_rate: float, anomaly: bool
     * }>
     */
    public function getLowStockProducts(): array;

    /**
     * Set low-stock products.
     *
     * @param array $value
     * @return void
     */
    public function setLowStockProducts(array $value): void;

    /**
     * Get revenue by top categories for the last 30 days.
     *
     * @return array<int, array{category: string, revenue: float, percent: float}>
     */
    public function getRevenueByCategory(): array;

    /**
     * Set revenue by category data.
     *
     * @param array $value
     * @return void
     */
    public function setRevenueByCategory(array $value): void;
}
