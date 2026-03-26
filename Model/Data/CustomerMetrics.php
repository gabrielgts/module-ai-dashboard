<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Data;

use Gtstudio\AiDashboard\Api\Data\CustomerMetricsInterface;

class CustomerMetrics implements CustomerMetricsInterface
{
    /** @var int */
    private int $newCustomersToday = 0;
    /** @var int */
    private int $newCustomersWeek = 0;
    /** @var int */
    private int $newCustomersMonth = 0;
    /** @var int */
    private int $totalRegisteredCustomers = 0;
    /** @var float */
    private float $repeatCustomerRate = 0.0;
    /** @var array<int, array<string, mixed>> */
    private array $topCustomersByLtv = [];
    /** @var array<int, array<string, mixed>> */
    private array $acquisitionTrend = [];
    /** @var float */
    private float $acquisitionTrendSlope = 0.0;
    /** @var array<string, array<string, mixed>> */
    private array $segments = [];

    /**
     * Get new customers today count.
     *
     * @return int
     */
    public function getNewCustomersToday(): int
    {
        return $this->newCustomersToday;
    }

    /**
     * Set new customers today count.
     *
     * @param int $value
     * @return void
     */
    public function setNewCustomersToday(int $value): void
    {
        $this->newCustomersToday = $value;
    }

    /**
     * Get new customers this week count.
     *
     * @return int
     */
    public function getNewCustomersWeek(): int
    {
        return $this->newCustomersWeek;
    }

    /**
     * Set new customers this week count.
     *
     * @param int $value
     * @return void
     */
    public function setNewCustomersWeek(int $value): void
    {
        $this->newCustomersWeek = $value;
    }

    /**
     * Get new customers this month count.
     *
     * @return int
     */
    public function getNewCustomersMonth(): int
    {
        return $this->newCustomersMonth;
    }

    /**
     * Set new customers this month count.
     *
     * @param int $value
     * @return void
     */
    public function setNewCustomersMonth(int $value): void
    {
        $this->newCustomersMonth = $value;
    }

    /**
     * Get total registered customers count.
     *
     * @return int
     */
    public function getTotalRegisteredCustomers(): int
    {
        return $this->totalRegisteredCustomers;
    }

    /**
     * Set total registered customers count.
     *
     * @param int $value
     * @return void
     */
    public function setTotalRegisteredCustomers(int $value): void
    {
        $this->totalRegisteredCustomers = $value;
    }

    /**
     * Get repeat customer rate.
     *
     * @return float
     */
    public function getRepeatCustomerRate(): float
    {
        return $this->repeatCustomerRate;
    }

    /**
     * Set repeat customer rate.
     *
     * @param float $value
     * @return void
     */
    public function setRepeatCustomerRate(float $value): void
    {
        $this->repeatCustomerRate = $value;
    }

    /**
     * Get top customers by lifetime value.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopCustomersByLtv(): array
    {
        return $this->topCustomersByLtv;
    }

    /**
     * Set top customers by lifetime value.
     *
     * @param array $value
     * @return void
     */
    public function setTopCustomersByLtv(array $value): void
    {
        $this->topCustomersByLtv = $value;
    }

    /**
     * Get acquisition trend data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAcquisitionTrend(): array
    {
        return $this->acquisitionTrend;
    }

    /**
     * Set acquisition trend data.
     *
     * @param array $value
     * @return void
     */
    public function setAcquisitionTrend(array $value): void
    {
        $this->acquisitionTrend = $value;
    }

    /**
     * Get acquisition trend slope.
     *
     * @return float
     */
    public function getAcquisitionTrendSlope(): float
    {
        return $this->acquisitionTrendSlope;
    }

    /**
     * Set acquisition trend slope.
     *
     * @param float $value
     * @return void
     */
    public function setAcquisitionTrendSlope(float $value): void
    {
        $this->acquisitionTrendSlope = $value;
    }

    /**
     * Get customer segmentation data.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     * Set customer segmentation data.
     *
     * @param array $value
     * @return void
     */
    public function setSegments(array $value): void
    {
        $this->segments = $value;
    }
}
