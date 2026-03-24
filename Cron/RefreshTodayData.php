<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Cron;

use Gtstudio\AiDashboard\Model\Service\CacheManager;
use Gtstudio\AiDashboard\Model\Service\OrderMetricsCollector;
use Psr\Log\LoggerInterface;

/** Lightweight today-only refresh — runs every hour to keep KPI cards current. */
class RefreshTodayData
{
    public function __construct(
        private readonly OrderMetricsCollector $orderCollector,
        private readonly CacheManager $cacheManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        try {
            $metrics = $this->orderCollector->collect();

            $this->cacheManager->saveToday([
                'orders_count' => $metrics->getTodayOrdersCount(),
                'revenue'      => $metrics->getTodayRevenue(),
                'avg_order'    => $metrics->getAverageOrderValue(),
                'by_status'    => $metrics->getOrdersByStatus(),
                'updated_at'   => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]);

            $this->logger->info('AiDashboard: today data refreshed.');
        } catch (\Throwable $e) {
            $this->logger->error('AiDashboard: incremental refresh failed — ' . $e->getMessage());
        }
    }
}
