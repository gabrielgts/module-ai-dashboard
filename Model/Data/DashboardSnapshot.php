<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Data;

use Gtstudio\AiDashboard\Api\Data\CustomerMetricsInterface;
use Gtstudio\AiDashboard\Api\Data\DashboardSnapshotInterface;
use Gtstudio\AiDashboard\Api\Data\OrderMetricsInterface;
use Gtstudio\AiDashboard\Api\Data\ProductMetricsInterface;

class DashboardSnapshot implements DashboardSnapshotInterface
{
    private OrderMetricsInterface $orders;
    private CustomerMetricsInterface $customers;
    private ProductMetricsInterface $products;
    private string $builtAt = '';

    public function __construct(
        OrderMetricsInterface $orders,
        CustomerMetricsInterface $customers,
        ProductMetricsInterface $products,
    ) {
        $this->orders    = $orders;
        $this->customers = $customers;
        $this->products  = $products;
    }

    public function getOrders(): OrderMetricsInterface { return $this->orders; }
    public function setOrders(OrderMetricsInterface $orders): void { $this->orders = $orders; }

    public function getCustomers(): CustomerMetricsInterface { return $this->customers; }
    public function setCustomers(CustomerMetricsInterface $customers): void { $this->customers = $customers; }

    public function getProducts(): ProductMetricsInterface { return $this->products; }
    public function setProducts(ProductMetricsInterface $products): void { $this->products = $products; }

    public function getBuiltAt(): string { return $this->builtAt; }
    public function setBuiltAt(string $datetime): void { $this->builtAt = $datetime; }

    public function isStale(): bool
    {
        if ($this->builtAt === '') {
            return true;
        }

        $age = time() - (new \DateTimeImmutable($this->builtAt))->getTimestamp();

        return $age > 3600;
    }
}
