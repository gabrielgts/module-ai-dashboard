<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Data;

use Gtstudio\AiDashboard\Api\Data\CustomerMetricsInterface;
use Gtstudio\AiDashboard\Api\Data\DashboardSnapshotInterface;
use Gtstudio\AiDashboard\Api\Data\OrderMetricsInterface;
use Gtstudio\AiDashboard\Api\Data\ProductMetricsInterface;

class DashboardSnapshot implements DashboardSnapshotInterface
{
    /** @var OrderMetricsInterface */
    private OrderMetricsInterface $orders;
    /** @var CustomerMetricsInterface */
    private CustomerMetricsInterface $customers;
    /** @var ProductMetricsInterface */
    private ProductMetricsInterface $products;
    /** @var string */
    private string $builtAt = '';

    /**
     * @param OrderMetricsInterface $orders
     * @param CustomerMetricsInterface $customers
     * @param ProductMetricsInterface $products
     */
    public function __construct(
        OrderMetricsInterface $orders,
        CustomerMetricsInterface $customers,
        ProductMetricsInterface $products,
    ) {
        $this->orders    = $orders;
        $this->customers = $customers;
        $this->products  = $products;
    }

    /**
     * Get order metrics.
     *
     * @return OrderMetricsInterface
     */
    public function getOrders(): OrderMetricsInterface
    {
        return $this->orders;
    }

    /**
     * Set order metrics.
     *
     * @param OrderMetricsInterface $orders
     * @return void
     */
    public function setOrders(OrderMetricsInterface $orders): void
    {
        $this->orders = $orders;
    }

    /**
     * Get customer metrics.
     *
     * @return CustomerMetricsInterface
     */
    public function getCustomers(): CustomerMetricsInterface
    {
        return $this->customers;
    }

    /**
     * Set customer metrics.
     *
     * @param CustomerMetricsInterface $customers
     * @return void
     */
    public function setCustomers(CustomerMetricsInterface $customers): void
    {
        $this->customers = $customers;
    }

    /**
     * Get product metrics.
     *
     * @return ProductMetricsInterface
     */
    public function getProducts(): ProductMetricsInterface
    {
        return $this->products;
    }

    /**
     * Set product metrics.
     *
     * @param ProductMetricsInterface $products
     * @return void
     */
    public function setProducts(ProductMetricsInterface $products): void
    {
        $this->products = $products;
    }

    /**
     * Get snapshot build timestamp.
     *
     * @return string
     */
    public function getBuiltAt(): string
    {
        return $this->builtAt;
    }

    /**
     * Set snapshot build timestamp.
     *
     * @param string $datetime
     * @return void
     */
    public function setBuiltAt(string $datetime): void
    {
        $this->builtAt = $datetime;
    }

    /**
     * Check if the snapshot is older than one hour.
     *
     * @return bool
     */
    public function isStale(): bool
    {
        if ($this->builtAt === '') {
            return true;
        }

        $age = time() - (new \DateTimeImmutable($this->builtAt))->getTimestamp();

        return $age > 3600;
    }
}
