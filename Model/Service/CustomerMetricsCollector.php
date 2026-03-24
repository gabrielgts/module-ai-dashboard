<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Service;

use Gtstudio\AiDashboard\Api\Data\CustomerMetricsInterface;
use Gtstudio\AiDashboard\Model\Config;
use Gtstudio\AiDashboard\Model\Data\CustomerMetrics;
use Gtstudio\AiDashboard\Model\Service\Ml\CustomerSegmentationAnalyzer;
use Gtstudio\AiDashboard\Model\Service\Ml\TrendSlopeAnalyzer;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/** Populates CustomerMetricsInterface by querying customer_entity and sales_order. */
class CustomerMetricsCollector
{
    private const EXCLUDED_STATUSES = ['canceled', 'closed'];
    /** Maximum number of customers loaded for RFM segmentation to stay within memory budget. */
    private const RFM_SAMPLE_LIMIT = 2000;

    private int $storeId = 0;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CustomerMetrics $metrics,
        private readonly LoggerInterface $logger,
        private readonly Config $config,
        private readonly CustomerSegmentationAnalyzer $segmentationAnalyzer,
        private readonly TrendSlopeAnalyzer $trendSlopeAnalyzer,
    ) {
    }

    public function collect(int $storeId = 0): CustomerMetricsInterface
    {
        $this->storeId = $storeId;

        try {
            $this->collectNewCustomers();
            $this->collectTotalCustomers();
            $this->collectTopCustomers();
            $this->collectAcquisitionTrend();
            $this->calculateRepeatRate();
            $this->collectSegments();
            $this->computeAcquisitionTrendSlope();
        } catch (\Throwable $e) {
            $this->logger->error('AiDashboard CustomerMetricsCollector: ' . $e->getMessage());
        }

        return $this->metrics;
    }

    /** Fetches today / this-week / this-month new registrations in one query. */
    private function collectNewCustomers(): void
    {
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('customer_entity');

        $newCustSelect = $conn->select()
            ->from($table, [
                'today' => new \Zend_Db_Expr(
                    "SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END)"
                ),
                'week'  => new \Zend_Db_Expr(
                    "SUM(CASE WHEN YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END)"
                ),
                'month' => new \Zend_Db_Expr(
                    "SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END)"
                ),
            ])
            ->where('created_at >= DATE_SUB(CURDATE(), INTERVAL 31 DAY)');

        if ($this->storeId > 0) {
            $newCustSelect->where('store_id = ?', $this->storeId);
        }

        $row = $conn->fetchRow($newCustSelect);

        $this->metrics->setNewCustomersToday((int) ($row['today'] ?? 0));
        $this->metrics->setNewCustomersWeek((int) ($row['week']  ?? 0));
        $this->metrics->setNewCustomersMonth((int) ($row['month'] ?? 0));
    }

    private function collectTotalCustomers(): void
    {
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('customer_entity');

        $totalSelect = $conn->select()
            ->from($table, [new \Zend_Db_Expr('COUNT(*)')])
            ->where('is_active = 1');

        if ($this->storeId > 0) {
            $totalSelect->where('store_id = ?', $this->storeId);
        }

        $total = (int) $conn->fetchOne($totalSelect);

        $this->metrics->setTotalRegisteredCustomers($total);
    }

    private function collectTopCustomers(): void
    {
        $conn      = $this->resource->getConnection();
        $cTable    = $this->resource->getTableName('customer_entity');
        $oTable    = $this->resource->getTableName('sales_order');

        $topCustSelect = $conn->select()
            ->from(['c' => $cTable], [
                'customer_id'    => 'c.entity_id',
                'name'           => new \Zend_Db_Expr("CONCAT(c.firstname, ' ', c.lastname)"),
                'email'          => 'c.email',
                'total_orders'   => new \Zend_Db_Expr('COUNT(o.entity_id)'),
                'lifetime_value' => new \Zend_Db_Expr('COALESCE(SUM(o.base_grand_total), 0)'),
            ])
            ->join(['o' => $oTable], 'o.customer_id = c.entity_id', [])
            ->where('o.status NOT IN (?)', self::EXCLUDED_STATUSES)
            ->group('c.entity_id')
            ->order('lifetime_value DESC')
            ->limit($this->config->getTopCustomersLimit());

        if ($this->storeId > 0) {
            $topCustSelect->where('o.store_id = ?', $this->storeId);
        }

        $rows = $conn->fetchAll($topCustSelect);

        $top = array_map(static fn(array $r): array => [
            'customer_id' => (int) $r['customer_id'],
            'name' => (string) $r['name'],
            'email' => (string) $r['email'],
            'total_orders' => (int) $r['total_orders'],
            'lifetime_value' => (float) $r['lifetime_value'],
        ], $rows);

        $this->metrics->setTopCustomersByLtv($top);
    }

    private function collectAcquisitionTrend(): void
    {
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('customer_entity');

        $acqSelect = $conn->select()
            ->from($table, [
                'date'  => new \Zend_Db_Expr('DATE(created_at)'),
                'count' => new \Zend_Db_Expr('COUNT(*)'),
            ])
            ->where('created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)', $this->config->getTrendDays() - 1)
            ->group(new \Zend_Db_Expr('DATE(created_at)'))
            ->order(new \Zend_Db_Expr('DATE(created_at) ASC'));

        if ($this->storeId > 0) {
            $acqSelect->where('store_id = ?', $this->storeId);
        }

        $rows = $conn->fetchAll($acqSelect);

        $trend = array_map(static fn(array $r): array => [
            'date' => (string) $r['date'],
            'count' => (int) $r['count'],
        ], $rows);

        $this->metrics->setAcquisitionTrend($trend);
    }

    private function calculateRepeatRate(): void
    {
        $conn   = $this->resource->getConnection();
        $oTable = $this->resource->getTableName('sales_order');

        // Subquery counts orders per customer, outer query counts repeat vs total buyers.
        $sub = $conn->select()
            ->from($oTable, ['customer_id', 'order_count' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('customer_id IS NOT NULL')
            ->where('status NOT IN (?)', self::EXCLUDED_STATUSES)
            ->group('customer_id');

        if ($this->storeId > 0) {
            $sub->where('store_id = ?', $this->storeId);
        }

        $row = $conn->fetchRow(
            $conn->select()
                ->from(['buyers' => $sub], [
                    'repeat' => new \Zend_Db_Expr('SUM(CASE WHEN order_count > 1 THEN 1 ELSE 0 END)'),
                    'total'  => new \Zend_Db_Expr('COUNT(*)'),
                ])
        );

        $total  = (int) ($row['total']  ?? 0);
        $repeat = (int) ($row['repeat'] ?? 0);
        $rate   = $total > 0 ? round(($repeat / $total) * 100, 2) : 0.0;

        $this->metrics->setRepeatCustomerRate($rate);
    }

    /**
     * Fetches RFM (Recency, Frequency, Monetary) data for up to RFM_SAMPLE_LIMIT customers
     * and runs k-Means segmentation to produce VIP / Active / At-Risk tiers.
     */
    private function collectSegments(): void
    {
        $conn   = $this->resource->getConnection();
        $cTable = $this->resource->getTableName('customer_entity');
        $oTable = $this->resource->getTableName('sales_order');

        $rfmSelect = $conn->select()
            ->from(['c' => $cTable], [
                'customer_id' => 'c.entity_id',
                'name'        => new \Zend_Db_Expr("CONCAT(c.firstname, ' ', c.lastname)"),
                'email'       => 'c.email',
                'recency'     => new \Zend_Db_Expr('DATEDIFF(NOW(), MAX(o.created_at))'),
                'frequency'   => new \Zend_Db_Expr('COUNT(o.entity_id)'),
                'monetary'    => new \Zend_Db_Expr('COALESCE(SUM(o.base_grand_total), 0)'),
            ])
            ->join(['o' => $oTable], 'o.customer_id = c.entity_id', [])
            ->where('o.status NOT IN (?)', self::EXCLUDED_STATUSES)
            ->group('c.entity_id')
            ->order('monetary DESC')
            ->limit(self::RFM_SAMPLE_LIMIT);

        if ($this->storeId > 0) {
            $rfmSelect->where('o.store_id = ?', $this->storeId);
        }

        $rows = $conn->fetchAll($rfmSelect);

        if (empty($rows)) {
            $this->metrics->setSegments($this->segmentationAnalyzer->segment([]));
            return;
        }

        // Key by customer_id for the segmentation analyzer
        $rfmRows = [];
        foreach ($rows as $row) {
            $rfmRows[(int) $row['customer_id']] = [
                'recency' => (int) $row['recency'],
                'frequency' => (int) $row['frequency'],
                'monetary' => (float) $row['monetary'],
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
            ];
        }

        $this->metrics->setSegments($this->segmentationAnalyzer->segment($rfmRows));
    }

    /** OLS slope of the customer acquisition trend — positive = growing, negative = declining. */
    private function computeAcquisitionTrendSlope(): void
    {
        $slope = $this->trendSlopeAnalyzer->slope($this->metrics->getAcquisitionTrend(), 'count');
        $this->metrics->setAcquisitionTrendSlope($slope);
    }
}
