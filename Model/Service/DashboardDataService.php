<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Service;

use Gtstudio\AiDashboard\Api\Data\DashboardSnapshotInterface;
use Gtstudio\AiDashboard\Api\DashboardDataServiceInterface;
use Gtstudio\AiDashboard\Model\Data\CustomerMetrics;
use Gtstudio\AiDashboard\Model\Data\DashboardSnapshot;
use Gtstudio\AiDashboard\Model\Data\OrderMetrics;
use Gtstudio\AiDashboard\Model\Data\ProductMetrics;
use Psr\Log\LoggerInterface;

class DashboardDataService implements DashboardDataServiceInterface
{
    /**
     * @param OrderMetricsCollector $orderCollector
     * @param CustomerMetricsCollector $customerCollector
     * @param ProductMetricsCollector $productCollector
     * @param CacheManager $cacheManager
     * @param DashboardSnapshot $snapshotProto
     * @param OrderMetrics $orderMetricsProto
     * @param CustomerMetrics $customerMetricsProto
     * @param ProductMetrics $productMetricsProto
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OrderMetricsCollector $orderCollector,
        private readonly CustomerMetricsCollector $customerCollector,
        private readonly ProductMetricsCollector $productCollector,
        private readonly CacheManager $cacheManager,
        private readonly DashboardSnapshot $snapshotProto,
        private readonly OrderMetrics $orderMetricsProto,
        private readonly CustomerMetrics $customerMetricsProto,
        private readonly ProductMetrics $productMetricsProto,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getSnapshot(bool $forceRefresh = false, int $storeId = 0): DashboardSnapshotInterface
    {
        if (!$forceRefresh) {
            $cached = $this->loadFromCache($storeId);
            if ($cached !== null) {
                return $cached;
            }
        }

        $snapshot = $this->buildSnapshot($storeId);
        $this->saveToCache($snapshot, $storeId);

        return $snapshot;
    }

    /**
     * @inheritdoc
     */
    public function buildSnapshot(int $storeId = 0): DashboardSnapshotInterface
    {
        $snapshot = clone $this->snapshotProto;
        $snapshot->setOrders($this->orderCollector->collect($storeId));
        $snapshot->setCustomers($this->customerCollector->collect($storeId));
        $snapshot->setProducts($this->productCollector->collect($storeId));
        $snapshot->setBuiltAt((new \DateTimeImmutable())->format(\DateTimeInterface::ATOM));

        return $snapshot;
    }

    /**
     * @inheritdoc
     */
    public function invalidateCache(): void
    {
        $this->cacheManager->invalidateAll();
    }

    /**
     * Attempt to hydrate a snapshot from cache.
     *
     * @param int $storeId
     * @return DashboardSnapshotInterface|null
     */
    private function loadFromCache(int $storeId = 0): ?DashboardSnapshotInterface
    {
        try {
            $data = $this->cacheManager->loadSnapshot($storeId);
            if ($data === null) {
                return null;
            }

            $o = $data['orders']    ?? [];
            $c = $data['customers'] ?? [];
            $p = $data['products']  ?? [];

            $orders = clone $this->orderMetricsProto;
            $orders->setTodayOrdersCount((int)   ($o['today_count']     ?? 0));
            $orders->setTodayRevenue((float)      ($o['today_revenue']   ?? 0.0));
            $orders->setWeekOrdersCount((int)     ($o['week_count']      ?? 0));
            $orders->setWeekRevenue((float)       ($o['week_revenue']    ?? 0.0));
            $orders->setMonthOrdersCount((int)    ($o['month_count']     ?? 0));
            $orders->setMonthRevenue((float)      ($o['month_revenue']   ?? 0.0));
            $orders->setAverageOrderValue((float) ($o['avg_order_value'] ?? 0.0));
            $orders->setRevenueGrowthPercent((float) ($o['growth_percent'] ?? 0.0));
            $orders->setOrdersByStatus((array)       ($o['by_status']           ?? []));
            $orders->setRevenueTrend((array)         ($o['revenue_trend']       ?? []));
            $orders->setRecentOrders((array)          ($o['recent_orders']       ?? []));
            $orders->setCouponMetrics((array)         ($o['coupon_metrics']      ?? []));
            $orders->setRevenueTrendSlope((float)     ($o['revenue_trend_slope'] ?? 0.0));

            $customers = clone $this->customerMetricsProto;
            $customers->setNewCustomersToday((int)          ($c['new_today']         ?? 0));
            $customers->setNewCustomersWeek((int)           ($c['new_week']          ?? 0));
            $customers->setNewCustomersMonth((int)          ($c['new_month']         ?? 0));
            $customers->setTotalRegisteredCustomers((int)   ($c['total']             ?? 0));
            $customers->setRepeatCustomerRate((float)       ($c['repeat_rate']       ?? 0.0));
            $customers->setTopCustomersByLtv((array)         ($c['top_by_ltv']                ?? []));
            $customers->setAcquisitionTrend((array)          ($c['acquisition_trend']         ?? []));
            $customers->setAcquisitionTrendSlope((float)     ($c['acquisition_trend_slope']   ?? 0.0));
            $customers->setSegments((array)                  ($c['segments']                  ?? []));

            $products = clone $this->productMetricsProto;
            $products->setTotalActiveProducts((int)  ($p['total_active']        ?? 0));
            $products->setOutOfStockCount((int)      ($p['out_of_stock']        ?? 0));
            $products->setLowStockCount((int)        ($p['low_stock_count']     ?? 0));
            $products->setTopSellingProducts((array) ($p['top_sellers']         ?? []));
            $products->setLowStockProducts((array)   ($p['low_stock_list']      ?? []));
            $products->setRevenueByCategory((array)  ($p['revenue_by_category'] ?? []));

            $snapshot = clone $this->snapshotProto;
            $snapshot->setOrders($orders);
            $snapshot->setCustomers($customers);
            $snapshot->setProducts($products);
            $snapshot->setBuiltAt((string) ($data['built_at'] ?? ''));

            return $snapshot;
        } catch (\Throwable $e) {
            $this->logger->error('AiDashboard: cache hydration failed — ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Serialize and persist the snapshot to cache.
     *
     * @param DashboardSnapshotInterface $snapshot
     * @param int $storeId
     * @return void
     */
    private function saveToCache(DashboardSnapshotInterface $snapshot, int $storeId = 0): void
    {
        try {
            $o = $snapshot->getOrders();
            $c = $snapshot->getCustomers();
            $p = $snapshot->getProducts();

            $this->cacheManager->saveSnapshot([
                'built_at'  => $snapshot->getBuiltAt(),
                'orders'    => [
                    'today_count'     => $o->getTodayOrdersCount(),
                    'today_revenue'   => $o->getTodayRevenue(),
                    'week_count'      => $o->getWeekOrdersCount(),
                    'week_revenue'    => $o->getWeekRevenue(),
                    'month_count'     => $o->getMonthOrdersCount(),
                    'month_revenue'   => $o->getMonthRevenue(),
                    'avg_order_value' => $o->getAverageOrderValue(),
                    'growth_percent'  => $o->getRevenueGrowthPercent(),
                    'by_status'           => $o->getOrdersByStatus(),
                    'revenue_trend'       => $o->getRevenueTrend(),
                    'recent_orders'       => $o->getRecentOrders(),
                    'coupon_metrics'      => $o->getCouponMetrics(),
                    'revenue_trend_slope' => $o->getRevenueTrendSlope(),
                ],
                'customers' => [
                    'new_today'         => $c->getNewCustomersToday(),
                    'new_week'          => $c->getNewCustomersWeek(),
                    'new_month'         => $c->getNewCustomersMonth(),
                    'total'             => $c->getTotalRegisteredCustomers(),
                    'repeat_rate'       => $c->getRepeatCustomerRate(),
                    'top_by_ltv'              => $c->getTopCustomersByLtv(),
                    'acquisition_trend'       => $c->getAcquisitionTrend(),
                    'acquisition_trend_slope' => $c->getAcquisitionTrendSlope(),
                    'segments'                => $c->getSegments(),
                ],
                'products'  => [
                    'total_active'        => $p->getTotalActiveProducts(),
                    'out_of_stock'        => $p->getOutOfStockCount(),
                    'low_stock_count'     => $p->getLowStockCount(),
                    'top_sellers'         => $p->getTopSellingProducts(),
                    'low_stock_list'      => $p->getLowStockProducts(),
                    'revenue_by_category' => $p->getRevenueByCategory(),
                ],
            ], $storeId);
        } catch (\Throwable $e) {
            $this->logger->error('AiDashboard: cache write failed — ' . $e->getMessage());
        }
    }
}
