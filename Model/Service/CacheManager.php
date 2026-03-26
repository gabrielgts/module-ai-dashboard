<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Service;

use Gtstudio\AiDashboard\Model\Cache\Dashboard as DashboardCache;
use Gtstudio\AiDashboard\Model\Config;
use Magento\Framework\Serialize\SerializerInterface;

class CacheManager
{
    private const KEY_SNAPSHOT = 'dashboard_snapshot_v1';
    private const KEY_TODAY    = 'dashboard_today_v1';
    private const KEY_INSIGHTS = 'dashboard_ai_insights_v1';

    /**
     * @param DashboardCache $cache
     * @param SerializerInterface $serializer
     * @param Config $config
     */
    public function __construct(
        private readonly DashboardCache $cache,
        private readonly SerializerInterface $serializer,
        private readonly Config $config,
    ) {
    }

    /**
     * Load the full dashboard snapshot from cache.
     *
     * @param int $storeId
     * @return array<string, mixed>|null
     */
    public function loadSnapshot(int $storeId = 0): ?array
    {
        $raw = $this->cache->load($this->snapshotKey($storeId));
        return $raw !== false ? $this->serializer->unserialize($raw) : null;
    }

    /**
     * Persist the full dashboard snapshot to cache.
     *
     * @param array $data
     * @param int $storeId
     * @return void
     */
    public function saveSnapshot(array $data, int $storeId = 0): void
    {
        $this->cache->save(
            $this->serializer->serialize($data),
            $this->snapshotKey($storeId),
            [DashboardCache::CACHE_TAG],
            $this->config->getSnapshotTtl()
        );
    }

    /**
     * Load the today-only incremental snapshot from cache.
     *
     * @param int $storeId
     * @return array<string, mixed>|null
     */
    public function loadToday(int $storeId = 0): ?array
    {
        $raw = $this->cache->load($this->todayKey($storeId));
        return $raw !== false ? $this->serializer->unserialize($raw) : null;
    }

    /**
     * Persist the today-only incremental snapshot to cache.
     *
     * @param array $data
     * @param int $storeId
     * @return void
     */
    public function saveToday(array $data, int $storeId = 0): void
    {
        $this->cache->save(
            $this->serializer->serialize($data),
            $this->todayKey($storeId),
            [DashboardCache::CACHE_TAG],
            $this->config->getTodayTtl()
        );
    }

    /**
     * Build the cache key for the full snapshot.
     *
     * @param int $storeId
     * @return string
     */
    private function snapshotKey(int $storeId): string
    {
        return self::KEY_SNAPSHOT . ($storeId > 0 ? '_s' . $storeId : '');
    }

    /**
     * Build the cache key for the today-only snapshot.
     *
     * @param int $storeId
     * @return string
     */
    private function todayKey(int $storeId): string
    {
        return self::KEY_TODAY . ($storeId > 0 ? '_s' . $storeId : '');
    }

    /**
     * Load cached AI insights by question hash.
     *
     * @param string $questionHash SHA-256 hash of the question and date
     * @return string|null
     */
    public function loadInsights(string $questionHash): ?string
    {
        $raw = $this->cache->load(self::KEY_INSIGHTS . '_' . $questionHash);
        return $raw !== false ? (string) $raw : null;
    }

    /**
     * Persist AI insights markdown to cache.
     *
     * @param string $questionHash SHA-256 hash of the question and date
     * @param string $markdown
     * @return void
     */
    public function saveInsights(string $questionHash, string $markdown): void
    {
        $this->cache->save(
            $markdown,
            self::KEY_INSIGHTS . '_' . $questionHash,
            [DashboardCache::CACHE_TAG],
            $this->config->getInsightsTtl()
        );
    }

    /**
     * Invalidate all dashboard cache entries.
     *
     * @return void
     */
    public function invalidateAll(): void
    {
        $this->cache->clean('matchingTag', [DashboardCache::CACHE_TAG]);
    }
}
