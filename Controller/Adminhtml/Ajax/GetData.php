<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Controller\Adminhtml\Ajax;

use Gtstudio\AiDashboard\Api\DashboardDataServiceInterface;
use Gtstudio\AiDashboard\Model\Config;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/** Returns the cached dashboard snapshot as JSON. Pass ?force=1 to rebuild. */
class GetData extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Gtstudio_AiDashboard::view';

    public function __construct(
        Context $context,
        private readonly DashboardDataServiceInterface $dashboardDataService,
        private readonly JsonFactory $resultJsonFactory,
        private readonly Config $config,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        $storeId = (int) $this->getRequest()->getParam('store_id', 0);

        if (!$this->config->isEnabled($storeId)) {
            return $result->setData(['success' => true, 'disabled' => true, 'data' => []]);
        }

        try {
            $forceRefresh = (bool) $this->getRequest()->getParam('force', 0)
                && $this->_authorization->isAllowed('Gtstudio_AiDashboard::refresh');

            $snapshot = $this->dashboardDataService->getSnapshot($forceRefresh, $storeId);
            $o        = $snapshot->getOrders();
            $c        = $snapshot->getCustomers();
            $p        = $snapshot->getProducts();

            $result->setData([
                'success'  => true,
                'data'     => [
                    'built_at' => $snapshot->getBuiltAt(),
                    'is_stale' => $snapshot->isStale(),
                    'orders'   => [
                        'today_count'         => $o->getTodayOrdersCount(),
                        'today_revenue'       => $o->getTodayRevenue(),
                        'week_count'          => $o->getWeekOrdersCount(),
                        'week_revenue'        => $o->getWeekRevenue(),
                        'month_count'         => $o->getMonthOrdersCount(),
                        'month_revenue'       => $o->getMonthRevenue(),
                        'avg_order_value'     => $o->getAverageOrderValue(),
                        'growth_percent'      => $o->getRevenueGrowthPercent(),
                        'by_status'           => $o->getOrdersByStatus(),
                        'revenue_trend'       => $o->getRevenueTrend(),
                        'revenue_trend_slope' => $o->getRevenueTrendSlope(),
                        'recent_orders'       => $o->getRecentOrders(),
                        'coupon_metrics'      => $o->getCouponMetrics(),
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
                    'products' => [
                        'total_active'        => $p->getTotalActiveProducts(),
                        'out_of_stock'        => $p->getOutOfStockCount(),
                        'low_stock_count'     => $p->getLowStockCount(),
                        'top_sellers'         => $p->getTopSellingProducts(),
                        'low_stock_list'      => $p->getLowStockProducts(),
                        'revenue_by_category' => $p->getRevenueByCategory(),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }

        return $result;
    }
}
