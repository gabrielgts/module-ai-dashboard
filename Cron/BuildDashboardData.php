<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Cron;

use Gtstudio\AiDashboard\Api\DashboardDataServiceInterface;
use Psr\Log\LoggerInterface;

/** Full snapshot rebuild — runs daily at 00:05. */
class BuildDashboardData
{
    /**
     * @param DashboardDataServiceInterface $dashboardDataService
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly DashboardDataServiceInterface $dashboardDataService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Run the full dashboard snapshot rebuild.
     *
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info('AiDashboard: starting full snapshot rebuild.');

        try {
            $snapshot = $this->dashboardDataService->getSnapshot(forceRefresh: true);

            $this->logger->info('AiDashboard: snapshot built at ' . $snapshot->getBuiltAt());
        } catch (\Throwable $e) {
            $this->logger->error('AiDashboard: full rebuild failed — ' . $e->getMessage());
        }
    }
}
