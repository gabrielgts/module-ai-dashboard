<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Api\Data;

interface DashboardSnapshotInterface
{
    /**
     * Get the order metrics snapshot.
     *
     * @return OrderMetricsInterface
     */
    public function getOrders(): OrderMetricsInterface;

    /**
     * Set order metrics.
     *
     * @param OrderMetricsInterface $orders
     * @return void
     */
    public function setOrders(OrderMetricsInterface $orders): void;

    /**
     * Get the customer metrics snapshot.
     *
     * @return CustomerMetricsInterface
     */
    public function getCustomers(): CustomerMetricsInterface;

    /**
     * Set customer metrics.
     *
     * @param CustomerMetricsInterface $customers
     * @return void
     */
    public function setCustomers(CustomerMetricsInterface $customers): void;

    /**
     * Get the product metrics snapshot.
     *
     * @return ProductMetricsInterface
     */
    public function getProducts(): ProductMetricsInterface;

    /**
     * Set product metrics.
     *
     * @param ProductMetricsInterface $products
     * @return void
     */
    public function setProducts(ProductMetricsInterface $products): void;

    /**
     * Get ISO-8601 datetime when this snapshot was built.
     *
     * @return string
     */
    public function getBuiltAt(): string;

    /**
     * Set built-at datetime.
     *
     * @param string $datetime
     * @return void
     */
    public function setBuiltAt(string $datetime): void;

    /**
     * Check whether the snapshot is older than the configured snapshot TTL.
     *
     * @return bool
     */
    public function isStale(): bool;
}
