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
     * @param int  $storeId      Store view ID; 0 = all stores combined.
     */
    public function getSnapshot(bool $forceRefresh = false, int $storeId = 0): DashboardSnapshotInterface;

    /** Build a fresh snapshot by running all collectors (does not write to cache).
     *
     * @param int $storeId Store view ID; 0 = all stores combined.
     */
    public function buildSnapshot(int $storeId = 0): DashboardSnapshotInterface;

    /** Purge all dashboard cache entries. */
    public function invalidateCache(): void;
}
