<?php

declare(strict_types=1);

namespace Gtstudio\AiDashboard\Model\Data;

use Gtstudio\AiDashboard\Api\Data\CustomerMetricsInterface;

class CustomerMetrics implements CustomerMetricsInterface
{
    private int $newCustomersToday = 0;
    private int $newCustomersWeek = 0;
    private int $newCustomersMonth = 0;
    private int $totalRegisteredCustomers = 0;
    private float $repeatCustomerRate = 0.0;
    private array $topCustomersByLtv = [];
    private array $acquisitionTrend = [];
    private float $acquisitionTrendSlope = 0.0;
    private array $segments = [];

    public function getNewCustomersToday(): int { return $this->newCustomersToday; }
    public function setNewCustomersToday(int $value): void { $this->newCustomersToday = $value; }

    public function getNewCustomersWeek(): int { return $this->newCustomersWeek; }
    public function setNewCustomersWeek(int $value): void { $this->newCustomersWeek = $value; }

    public function getNewCustomersMonth(): int { return $this->newCustomersMonth; }
    public function setNewCustomersMonth(int $value): void { $this->newCustomersMonth = $value; }

    public function getTotalRegisteredCustomers(): int { return $this->totalRegisteredCustomers; }
    public function setTotalRegisteredCustomers(int $value): void { $this->totalRegisteredCustomers = $value; }

    public function getRepeatCustomerRate(): float { return $this->repeatCustomerRate; }
    public function setRepeatCustomerRate(float $value): void { $this->repeatCustomerRate = $value; }

    public function getTopCustomersByLtv(): array { return $this->topCustomersByLtv; }
    public function setTopCustomersByLtv(array $value): void { $this->topCustomersByLtv = $value; }

    public function getAcquisitionTrend(): array { return $this->acquisitionTrend; }
    public function setAcquisitionTrend(array $value): void { $this->acquisitionTrend = $value; }

    public function getAcquisitionTrendSlope(): float { return $this->acquisitionTrendSlope; }
    public function setAcquisitionTrendSlope(float $value): void { $this->acquisitionTrendSlope = $value; }

    public function getSegments(): array { return $this->segments; }
    public function setSegments(array $value): void { $this->segments = $value; }
}
