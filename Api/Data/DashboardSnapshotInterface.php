<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Api\Data;

interface DashboardSnapshotInterface
{
    public function getOrders(): OrderMetricsInterface;
    public function setOrders(OrderMetricsInterface $orders): void;

    public function getCustomers(): CustomerMetricsInterface;
    public function setCustomers(CustomerMetricsInterface $customers): void;

    public function getProducts(): ProductMetricsInterface;
    public function setProducts(ProductMetricsInterface $products): void;

    /** ISO-8601 datetime when this snapshot was built */
    public function getBuiltAt(): string;
    public function setBuiltAt(string $datetime): void;

    /** True when the snapshot is older than the configured snapshot TTL */
    public function isStale(): bool;
}
