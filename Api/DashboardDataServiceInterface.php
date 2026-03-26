<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Api;

use Gtstudio\AiDashboard\Api\Data\DashboardSnapshotInterface;

interface DashboardDataServiceInterface
{
    /**
     * Return a snapshot from cache, or build one if the cache is cold.
     *
     * @param bool $forceRefresh Bypass cache and rebuild when true.
     * @param int $storeId Store view ID; 0 = all stores combined.
     * @return DashboardSnapshotInterface
     */
    public function getSnapshot(bool $forceRefresh = false, int $storeId = 0): DashboardSnapshotInterface;

    /**
     * Build a fresh snapshot by running all collectors.
     *
     * Does not write to cache; use getSnapshot() for cached access.
     *
     * @param int $storeId Store view ID; 0 = all stores combined.
     * @return DashboardSnapshotInterface
     */
    public function buildSnapshot(int $storeId = 0): DashboardSnapshotInterface;

    /**
     * Purge all dashboard cache entries.
     *
     * @return void
     */
    public function invalidateCache(): void;
}
