<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Service;

use Gtstudio\AiDashboard\Api\Data\ProductMetricsInterface;
use Gtstudio\AiDashboard\Model\Config;
use Gtstudio\AiDashboard\Model\Data\ProductMetrics;
use Gtstudio\AiDashboard\Model\Service\Ml\StockAnomalyAnalyzer;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/** Populates ProductMetricsInterface by querying catalog and inventory tables. */
class ProductMetricsCollector
{
    private const EXCLUDED_STATUSES = ['canceled', 'closed'];
    private const PRODUCT_ENTITY_TYPE = 4;
    private const CATEGORY_ENTITY_TYPE = 3;

    /** @var int */
    private int $storeId = 0;

    /**
     * @param ResourceConnection $resource
     * @param ProductMetrics $metrics
     * @param LoggerInterface $logger
     * @param Config $config
     * @param StockAnomalyAnalyzer $stockAnomalyAnalyzer
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ProductMetrics $metrics,
        private readonly LoggerInterface $logger,
        private readonly Config $config,
        private readonly StockAnomalyAnalyzer $stockAnomalyAnalyzer,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function collect(int $storeId = 0): ProductMetricsInterface
    {
        $this->storeId = $storeId;

        try {
            $this->collectCatalogueTotals();
            $this->collectTopSellers();
            $this->collectLowStock();
            $this->flagStockAnomalies();
            $this->collectRevenueByCategory();
        } catch (\Throwable $e) {
            $this->logger->error('AiDashboard ProductMetricsCollector: ' . $e->getMessage());
        }

        return $this->metrics;
    }

    /**
     * Fetch total active products, out-of-stock count, and low-stock count.
     *
     * @return void
     */
    private function collectCatalogueTotals(): void
    {
        $conn   = $this->resource->getConnection();
        $status = $this->resource->getTableName('cataloginventory_stock_status');
        $stock  = $this->resource->getTableName('cataloginventory_stock_item');

        $row = $conn->fetchRow(
            $conn->select()
                ->from($status, [
                    'total'        => new \Zend_Db_Expr('COUNT(*)'),
                    'out_of_stock' => new \Zend_Db_Expr('SUM(CASE WHEN stock_status = 0 THEN 1 ELSE 0 END)'),
                ])
        );

        $this->metrics->setTotalActiveProducts((int) ($row['total'] ?? 0));
        $this->metrics->setOutOfStockCount((int) ($row['out_of_stock'] ?? 0));

        $lowStockCount = (int) $conn->fetchOne(
            $conn->select()
                ->from($stock, [new \Zend_Db_Expr('COUNT(*)')])
                ->where('qty < ?', $this->config->getLowStockThreshold())
                ->where('qty >= 0')
                ->where('is_in_stock = 1')
        );

        $this->metrics->setLowStockCount($lowStockCount);
    }

    /**
     * Uses oi.name (product name captured at order time) to avoid a heavy EAV join.
     *
     * Excludes child (bundle/configurable) items via parent_item_id IS NULL.
     */
    private function collectTopSellers(): void
    {
        $conn   = $this->resource->getConnection();
        $oiTable = $this->resource->getTableName('sales_order_item');
        $oTable  = $this->resource->getTableName('sales_order');

        $topSellersSelect = $conn->select()
            ->from(['oi' => $oiTable], [
                'product_id' => 'oi.product_id',
                'name'       => 'oi.name',
                'sku'        => 'oi.sku',
                'qty_sold'   => new \Zend_Db_Expr('SUM(oi.qty_ordered)'),
                'revenue'    => new \Zend_Db_Expr('COALESCE(SUM(oi.base_row_total), 0)'),
            ])
            ->join(['o' => $oTable], 'o.entity_id = oi.order_id', [])
            ->where('o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')
            ->where('o.status NOT IN (?)', self::EXCLUDED_STATUSES)
            ->where('oi.parent_item_id IS NULL')
            ->group('oi.product_id')
            ->order('qty_sold DESC')
            ->limit($this->config->getTopProductsLimit());

        if ($this->storeId > 0) {
            $topSellersSelect->where('o.store_id = ?', $this->storeId);
        }

        $rows = $conn->fetchAll($topSellersSelect);

        $sellers = array_map(static fn(array $r): array => [
            'product_id' => (int) $r['product_id'],
            'name' => (string) $r['name'],
            'sku' => (string) $r['sku'],
            'qty_sold' => (float) $r['qty_sold'],
            'revenue' => (float) $r['revenue'],
        ], $rows);

        $this->metrics->setTopSellingProducts($sellers);
    }

    /**
     * Fetch products that are below the low-stock threshold.
     *
     * @return void
     */
    private function collectLowStock(): void
    {
        $conn    = $this->resource->getConnection();
        $stock   = $this->resource->getTableName('cataloginventory_stock_item');
        $product = $this->resource->getTableName('catalog_product_entity');
        $eavAttr = $this->resource->getTableName('eav_attribute');
        $eavVarchar = $this->resource->getTableName('catalog_product_entity_varchar');

        $nameAttrId = (int) $conn->fetchOne(
            $conn->select()
                ->from($eavAttr, ['attribute_id'])
                ->where('attribute_code = ?', 'name')
                ->where('entity_type_id = ?', self::PRODUCT_ENTITY_TYPE)
        );

        $rows = $conn->fetchAll(
            $conn->select()
                ->from(['si' => $stock], [
                    'product_id' => 'si.product_id',
                    'qty'        => 'si.qty',
                    'threshold'  => new \Zend_Db_Expr((string) $this->config->getLowStockThreshold()),
                ])
                ->join(['p' => $product], 'p.entity_id = si.product_id', ['sku'])
                ->joinLeft(
                    ['v' => $eavVarchar],
                    "v.entity_id = si.product_id AND v.attribute_id = {$nameAttrId} AND v.store_id = 0",
                    ['name' => 'v.value']
                )
                ->where('si.qty < ?', $this->config->getLowStockThreshold())
                ->where('si.qty >= 0')
                ->order('si.qty ASC')
        );

        $list = array_map(static fn(array $r): array => [
            'product_id' => (int) $r['product_id'],
            'name' => (string) ($r['name'] ?? $r['sku']),
            'sku' => (string) $r['sku'],
            'qty' => (float) $r['qty'],
            'threshold' => (int) $r['threshold'],
        ], $rows);

        $this->metrics->setLowStockProducts($list);
    }

    /**
     * Fetch revenue aggregated by category for the last 30 days.
     *
     * @return void
     */
    private function collectRevenueByCategory(): void
    {
        $conn       = $this->resource->getConnection();
        $oiTable    = $this->resource->getTableName('sales_order_item');
        $oTable     = $this->resource->getTableName('sales_order');
        $catProduct = $this->resource->getTableName('catalog_category_product');
        $eavAttr    = $this->resource->getTableName('eav_attribute');
        $catVarchar = $this->resource->getTableName('catalog_category_entity_varchar');

        $nameAttrId = (int) $conn->fetchOne(
            $conn->select()
                ->from($eavAttr, ['attribute_id'])
                ->where('attribute_code = ?', 'name')
                ->where('entity_type_id = ?', self::CATEGORY_ENTITY_TYPE)
        );

        $revByCatSelect = $conn->select()
            ->from(['oi' => $oiTable], [
                'category_id' => 'cp.category_id',
                'revenue'     => new \Zend_Db_Expr('COALESCE(SUM(oi.base_row_total), 0)'),
            ])
            ->join(['o' => $oTable], 'o.entity_id = oi.order_id', [])
            ->join(['cp' => $catProduct], 'cp.product_id = oi.product_id', [])
            ->joinLeft(
                ['cv' => $catVarchar],
                "cv.entity_id = cp.category_id AND cv.attribute_id = {$nameAttrId} AND cv.store_id = 0",
                ['category' => 'cv.value']
            )
            ->where('o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')
            ->where('o.status NOT IN (?)', self::EXCLUDED_STATUSES)
            ->where('oi.parent_item_id IS NULL')
            ->group('cp.category_id')
            ->order('revenue DESC')
            ->limit($this->config->getTopProductsLimit());

        if ($this->storeId > 0) {
            $revByCatSelect->where('o.store_id = ?', $this->storeId);
        }

        $rows = $conn->fetchAll($revByCatSelect);

        $total = array_sum(array_column($rows, 'revenue'));

        $categories = array_map(static fn(array $r): array => [
            'category' => (string) ($r['category'] ?? 'Uncategorised'),
            'revenue' => (float) $r['revenue'],
            'percent' => $total > 0 ? round(((float) $r['revenue'] / $total) * 100, 1) : 0.0,
        ], $rows);

        $this->metrics->setRevenueByCategory($categories);
    }

    /**
     * Enriches each low-stock item with a depletion_rate and anomaly flag.
     *
     * Must run after both collectTopSellers() and collectLowStock().
     */
    private function flagStockAnomalies(): void
    {
        $flagged = $this->stockAnomalyAnalyzer->flag(
            $this->metrics->getLowStockProducts(),
            $this->metrics->getTopSellingProducts()
        );
        $this->metrics->setLowStockProducts($flagged);
    }
}
