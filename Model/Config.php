<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed accessor for all AiDashboard system configuration values.
 * Consumed by service classes via DI; never call ScopeConfigInterface directly
 * from collectors or controllers — add a getter here instead.
 */
class Config
{
    public const XML_PATH_ENABLED = 'aidashboard/general/enabled';
    public const XML_PATH_LOW_STOCK_THRESHOLD = 'aidashboard/data/low_stock_threshold';
    public const XML_PATH_TOP_PRODUCTS_LIMIT = 'aidashboard/data/top_products_limit';
    public const XML_PATH_TOP_CUSTOMERS_LIMIT = 'aidashboard/data/top_customers_limit';
    public const XML_PATH_TOP_COUPONS_LIMIT = 'aidashboard/data/top_coupons_limit';
    public const XML_PATH_RECENT_ORDERS_LIMIT = 'aidashboard/data/recent_orders_limit';
    public const XML_PATH_TREND_DAYS = 'aidashboard/data/trend_days';
    public const XML_PATH_SNAPSHOT_TTL = 'aidashboard/cache/snapshot_ttl';
    public const XML_PATH_TODAY_TTL = 'aidashboard/cache/today_ttl';
    public const XML_PATH_INSIGHTS_TTL = 'aidashboard/cache/insights_ttl';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function isEnabled(int $storeId = 0): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId ?: null
        );
    }

    /** Minimum qty before a product appears in low-stock alerts (min: 1). */
    public function getLowStockThreshold(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_LOW_STOCK_THRESHOLD));
    }

    /** Number of top-selling products to collect (min: 1, max: 50). */
    public function getTopProductsLimit(): int
    {
        return min(50, max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_TOP_PRODUCTS_LIMIT)));
    }

    /** Number of top customers by LTV to collect (min: 1, max: 50). */
    public function getTopCustomersLimit(): int
    {
        return min(50, max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_TOP_CUSTOMERS_LIMIT)));
    }

    /** Number of top coupons by usage to collect (min: 1, max: 50). */
    public function getTopCouponsLimit(): int
    {
        return min(50, max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_TOP_COUPONS_LIMIT)));
    }

    /** Number of recent orders shown in the table (min: 1, max: 100). */
    public function getRecentOrdersLimit(): int
    {
        return min(100, max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_RECENT_ORDERS_LIMIT)));
    }

    /** Lookback window in days for revenue and acquisition trend charts (min: 7, max: 365). */
    public function getTrendDays(): int
    {
        return min(365, max(7, (int) $this->scopeConfig->getValue(self::XML_PATH_TREND_DAYS)));
    }

    /** TTL in seconds for the full dashboard snapshot (min: 60). */
    public function getSnapshotTtl(): int
    {
        return max(60, (int) $this->scopeConfig->getValue(self::XML_PATH_SNAPSHOT_TTL));
    }

    /** TTL in seconds for the today-only incremental cache (min: 60). */
    public function getTodayTtl(): int
    {
        return max(60, (int) $this->scopeConfig->getValue(self::XML_PATH_TODAY_TTL));
    }

    /** TTL in seconds for cached AI insights (min: 60). */
    public function getInsightsTtl(): int
    {
        return max(60, (int) $this->scopeConfig->getValue(self::XML_PATH_INSIGHTS_TTL));
    }
}
