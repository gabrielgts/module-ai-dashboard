<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Service;

use Gtstudio\AiDashboard\Api\Data\OrderMetricsInterface;
use Gtstudio\AiDashboard\Model\Config;
use Gtstudio\AiDashboard\Model\Data\OrderMetrics;
use Gtstudio\AiDashboard\Model\Service\Ml\TrendSlopeAnalyzer;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Populates OrderMetricsInterface by querying sales_order directly.
 * Uses raw SQL to avoid loading full ORM collections on large datasets.
 */
class OrderMetricsCollector
{
    private const EXCLUDED_STATUSES = ['canceled', 'closed'];

    /** @var int */
    private int $storeId = 0;

    /**
     * @param ResourceConnection $resource
     * @param OrderMetrics $metrics
     * @param LoggerInterface $logger
     * @param Config $config
     * @param TrendSlopeAnalyzer $trendSlopeAnalyzer
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly OrderMetrics $metrics,
        private readonly LoggerInterface $logger,
        private readonly Config $config,
        private readonly TrendSlopeAnalyzer $trendSlopeAnalyzer,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function collect(int $storeId = 0): OrderMetricsInterface
    {
        $this->storeId = $storeId;

        try {
            $this->collectPeriodMetrics();
            $this->collectPriorMonthRevenue();
            $this->collectOrdersByStatus();
            $this->collectRevenueTrend();
            $this->collectRecentOrders();
            $this->collectCouponMetrics();
            $this->computeRevenueTrendSlope();
        } catch (\Throwable $e) {
            $this->logger->error('AiDashboard OrderMetricsCollector: ' . $e->getMessage());
        }

        return $this->metrics;
    }

    /**
     * Fetch today, this-week, and this-month order counts and revenues in one query.
     *
     * @return void
     */
    private function collectPeriodMetrics(): void
    {
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('sales_order');

        // Single pass over the last ~31 days covers today, current week and current month.
        $select = $conn->select()
            ->from($table, [
                'today_cnt' => new \Zend_Db_Expr(
                    "SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END)"
                ),
                'today_rev' => new \Zend_Db_Expr(
                    "COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN base_grand_total ELSE NULL END), 0)"
                ),
                'week_cnt'  => new \Zend_Db_Expr(
                    "SUM(CASE WHEN YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END)"
                ),
                'week_rev'  => new \Zend_Db_Expr(
                    'COALESCE(SUM(CASE WHEN YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)' .
                    ' THEN base_grand_total ELSE NULL END), 0)'
                ),
                'month_cnt' => new \Zend_Db_Expr(
                    'SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE())' .
                    ' AND MONTH(created_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END)'
                ),
                'month_rev' => new \Zend_Db_Expr(
                    'COALESCE(SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE())' .
                    ' AND MONTH(created_at) = MONTH(CURDATE()) THEN base_grand_total ELSE NULL END), 0)'
                ),
            ])
            ->where('created_at >= DATE_SUB(CURDATE(), INTERVAL 31 DAY)')
            ->where('status NOT IN (?)', self::EXCLUDED_STATUSES);

        if ($this->storeId > 0) {
            $select->where('store_id = ?', $this->storeId);
        }

        $row = $conn->fetchRow($select);

        $this->metrics->setTodayOrdersCount((int) ($row['today_cnt'] ?? 0));
        $this->metrics->setTodayRevenue((float) ($row['today_rev'] ?? 0.0));
        $this->metrics->setWeekOrdersCount((int) ($row['week_cnt'] ?? 0));
        $this->metrics->setWeekRevenue((float) ($row['week_rev'] ?? 0.0));
        $this->metrics->setMonthOrdersCount((int) ($row['month_cnt'] ?? 0));
        $this->metrics->setMonthRevenue((float) ($row['month_rev'] ?? 0.0));

        $monthCount   = $this->metrics->getMonthOrdersCount();
        $monthRevenue = $this->metrics->getMonthRevenue();
        $this->metrics->setAverageOrderValue($monthCount > 0 ? $monthRevenue / $monthCount : 0.0);
    }

    /**
     * Calculate revenue growth percentage against the previous calendar month.
     *
     * @return void
     */
    private function collectPriorMonthRevenue(): void
    {
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('sales_order');

        $priorSelect = $conn->select()
            ->from($table, [new \Zend_Db_Expr('COALESCE(SUM(base_grand_total), 0)')])
            ->where('YEAR(created_at)  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))')
            ->where('MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))')
            ->where('status NOT IN (?)', self::EXCLUDED_STATUSES);

        if ($this->storeId > 0) {
            $priorSelect->where('store_id = ?', $this->storeId);
        }

        $priorRev = (float) $conn->fetchOne($priorSelect);

        $current = $this->metrics->getMonthRevenue();
        $growth  = $priorRev > 0.0 ? (($current - $priorRev) / $priorRev) * 100 : 0.0;

        $this->metrics->setRevenueGrowthPercent(round($growth, 2));
    }

    /**
     * Fetch order counts grouped by status.
     *
     * @return void
     */
    private function collectOrdersByStatus(): void
    {
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('sales_order');

        $statusSelect = $conn->select()
            ->from($table, ['status', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])
            ->group('status');

        if ($this->storeId > 0) {
            $statusSelect->where('store_id = ?', $this->storeId);
        }

        $rows = $conn->fetchAll($statusSelect);

        $byStatus = [];
        foreach ($rows as $row) {
            $byStatus[(string) $row['status']] = (int) $row['cnt'];
        }

        $this->metrics->setOrdersByStatus($byStatus);
    }

    /**
     * Fetch the daily revenue trend for the configured lookback window.
     *
     * @return void
     */
    private function collectRevenueTrend(): void
    {
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('sales_order');

        $trendSelect = $conn->select()
            ->from($table, [
                'date'    => new \Zend_Db_Expr('DATE(created_at)'),
                'revenue' => new \Zend_Db_Expr('COALESCE(SUM(base_grand_total), 0)'),
                'orders'  => new \Zend_Db_Expr('COUNT(*)'),
            ])
            ->where('created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)', $this->config->getTrendDays() - 1)
            ->where('status NOT IN (?)', self::EXCLUDED_STATUSES)
            ->group(new \Zend_Db_Expr('DATE(created_at)'))
            ->order(new \Zend_Db_Expr('DATE(created_at) ASC'));

        if ($this->storeId > 0) {
            $trendSelect->where('store_id = ?', $this->storeId);
        }

        $rows = $conn->fetchAll($trendSelect);

        $trend = array_map(static fn(array $r): array => [
            'date' => (string) $r['date'],
            'revenue' => (float) $r['revenue'],
            'orders' => (int) $r['orders'],
        ], $rows);

        $this->metrics->setRevenueTrend($trend);
    }

    /**
     * Fetch coupon usage counts and top coupons by use frequency for the current month.
     *
     * @return void
     */
    private function collectCouponMetrics(): void
    {
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('sales_order');

        $couponSelect = $conn->select()
            ->from($table, [
                'used_today' => new \Zend_Db_Expr(
                    "SUM(CASE WHEN DATE(created_at) = CURDATE()" .
                    " AND coupon_code IS NOT NULL AND coupon_code != '' THEN 1 ELSE 0 END)"
                ),
                'used_month' => new \Zend_Db_Expr(
                    'SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE())' .
                    " AND MONTH(created_at) = MONTH(CURDATE()) AND coupon_code IS NOT NULL AND coupon_code != ''" .
                    ' THEN 1 ELSE 0 END)'
                ),
                'total_discount_month' => new \Zend_Db_Expr(
                    'COALESCE(SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE())' .
                    " AND MONTH(created_at) = MONTH(CURDATE()) AND coupon_code IS NOT NULL AND coupon_code != ''" .
                    ' THEN ABS(discount_amount) ELSE 0 END), 0)'
                ),
            ])
            ->where('created_at >= DATE_SUB(CURDATE(), INTERVAL 31 DAY)')
            ->where('status NOT IN (?)', self::EXCLUDED_STATUSES);

        if ($this->storeId > 0) {
            $couponSelect->where('store_id = ?', $this->storeId);
        }

        $row = $conn->fetchRow($couponSelect);

        $topSelect = $conn->select()
            ->from($table, [
                'code'           => 'coupon_code',
                'uses'           => new \Zend_Db_Expr('COUNT(*)'),
                'total_discount' => new \Zend_Db_Expr('COALESCE(SUM(ABS(discount_amount)), 0)'),
            ])
            ->where('coupon_code IS NOT NULL')
            ->where('coupon_code != ?', '')
            ->where('created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)')
            ->where('status NOT IN (?)', self::EXCLUDED_STATUSES)
            ->group('coupon_code')
            ->order('uses DESC')
            ->limit($this->config->getTopCouponsLimit());

        if ($this->storeId > 0) {
            $topSelect->where('store_id = ?', $this->storeId);
        }

        $topRows = $conn->fetchAll($topSelect);

        $topCoupons = array_map(static fn(array $r): array => [
            'code' => (string) $r['code'],
            'uses' => (int) $r['uses'],
            'total_discount' => (float) $r['total_discount'],
        ], $topRows);

        $this->metrics->setCouponMetrics([
            'used_today' => (int) ($row['used_today'] ?? 0),
            'used_month' => (int) ($row['used_month'] ?? 0),
            'total_discount_month' => (float) ($row['total_discount_month'] ?? 0.0),
            'top_coupons' => $topCoupons,
        ]);
    }

    /**
     * Fetch the most recent orders for the dashboard table.
     *
     * @return void
     */
    private function collectRecentOrders(): void
    {
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('sales_order');

        $recentSelect = $conn->select()
            ->from($table, [
                'increment_id',
                'customer_name' => new \Zend_Db_Expr(
                    "CONCAT(COALESCE(customer_firstname, ''), ' ', COALESCE(customer_lastname, ''))"
                ),
                'grand_total'   => 'base_grand_total',
                'status',
                'created_at',
                'items_count'   => 'total_item_count',
            ])
            ->order('created_at DESC')
            ->limit($this->config->getRecentOrdersLimit());

        if ($this->storeId > 0) {
            $recentSelect->where('store_id = ?', $this->storeId);
        }

        $rows = $conn->fetchAll($recentSelect);

        $orders = array_map(static fn(array $r): array => [
            'increment_id' => (string) $r['increment_id'],
            'customer_name' => trim((string) $r['customer_name']),
            'grand_total' => (float) $r['grand_total'],
            'status' => (string) $r['status'],
            'created_at' => (string) $r['created_at'],
            'items_count' => (int) $r['items_count'],
        ], $rows);

        $this->metrics->setRecentOrders($orders);
    }

    /**
     * Compute the OLS slope of the revenue trend.
     *
     * A positive value means growing; negative means declining.
     *
     * @return void
     */
    private function computeRevenueTrendSlope(): void
    {
        $slope = $this->trendSlopeAnalyzer->slope($this->metrics->getRevenueTrend(), 'revenue');
        $this->metrics->setRevenueTrendSlope($slope);
    }
}
