<?php
/**
 * Copyright © GTstudio All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Cache;

class Dashboard extends \Magento\Framework\Cache\Frontend\Decorator\TagScope
{

    public const TYPE_IDENTIFIER = 'dashboard_cache_tag';
    public const CACHE_TAG = 'DASHBOARD_CACHE_TAG';

    /**
     * @param \Magento\Framework\App\Cache\Type\FrontendPool $cacheFrontendPool
     */
    public function __construct(
        \Magento\Framework\App\Cache\Type\FrontendPool $cacheFrontendPool
    ) {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
