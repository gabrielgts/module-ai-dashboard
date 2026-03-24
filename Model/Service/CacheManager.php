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

    public function __construct(
        private readonly DashboardCache $cache,
        private readonly SerializerInterface $serializer,
        private readonly Config $config,
    ) {
    }

    public function loadSnapshot(int $storeId = 0): ?array
    {
        $raw = $this->cache->load($this->snapshotKey($storeId));
        return $raw !== false ? $this->serializer->unserialize($raw) : null;
    }

    public function saveSnapshot(array $data, int $storeId = 0): void
    {
        $this->cache->save(
            $this->serializer->serialize($data),
            $this->snapshotKey($storeId),
            [DashboardCache::CACHE_TAG],
            $this->config->getSnapshotTtl()
        );
    }

    public function loadToday(int $storeId = 0): ?array
    {
        $raw = $this->cache->load($this->todayKey($storeId));
        return $raw !== false ? $this->serializer->unserialize($raw) : null;
    }

    public function saveToday(array $data, int $storeId = 0): void
    {
        $this->cache->save(
            $this->serializer->serialize($data),
            $this->todayKey($storeId),
            [DashboardCache::CACHE_TAG],
            $this->config->getTodayTtl()
        );
    }

    private function snapshotKey(int $storeId): string
    {
        return self::KEY_SNAPSHOT . ($storeId > 0 ? '_s' . $storeId : '');
    }

    private function todayKey(int $storeId): string
    {
        return self::KEY_TODAY . ($storeId > 0 ? '_s' . $storeId : '');
    }

    /** @param string $questionHash md5 hash of the question + date */
    public function loadInsights(string $questionHash): ?string
    {
        $raw = $this->cache->load(self::KEY_INSIGHTS . '_' . $questionHash);
        return $raw !== false ? (string) $raw : null;
    }

    public function saveInsights(string $questionHash, string $markdown): void
    {
        $this->cache->save(
            $markdown,
            self::KEY_INSIGHTS . '_' . $questionHash,
            [DashboardCache::CACHE_TAG],
            $this->config->getInsightsTtl()
        );
    }

    public function invalidateAll(): void
    {
        $this->cache->clean('matchingTag', [DashboardCache::CACHE_TAG]);
    }
}
