<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\Scheduler\CronExpressionHelper;
use Codeception\Test\Unit;

final class CronExpressionHelperTest extends Unit
{
    private CronExpressionHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new CronExpressionHelper();
    }

    public function testComputeNextRunReturnsDateInFuture(): void
    {
        $next = $this->helper->computeNextRun('* * * * *');

        $this->assertGreaterThan(new \DateTimeImmutable('now'), $next);
    }

    public function testComputeNextRunHourlyExpression(): void
    {
        $next = $this->helper->computeNextRun('0 * * * *');

        $this->assertSame('00', $next->format('i'));
        $this->assertGreaterThan(new \DateTimeImmutable('now'), $next);
    }

    public function testComputeNextRunEvery4Hours(): void
    {
        $next = $this->helper->computeNextRun('0 */4 * * *');

        $this->assertSame('00', $next->format('i'));
        $this->assertGreaterThan(new \DateTimeImmutable('now'), $next);
    }

    public function testComputeNextRunWithTimezone(): void
    {
        $nextUtc = $this->helper->computeNextRun('0 12 * * *', 'UTC');
        $nextKyiv = $this->helper->computeNextRun('0 12 * * *', 'Europe/Kyiv');

        // Both should be in the future
        $this->assertGreaterThan(new \DateTimeImmutable('now'), $nextUtc);
        $this->assertGreaterThan(new \DateTimeImmutable('now'), $nextKyiv);

        // They should differ (different timezones)
        $this->assertNotSame($nextUtc->getTimestamp(), $nextKyiv->getTimestamp());
    }

    public function testIsValidReturnsTrueForValidExpression(): void
    {
        $this->assertTrue($this->helper->isValid('* * * * *'));
        $this->assertTrue($this->helper->isValid('0 */4 * * *'));
        $this->assertTrue($this->helper->isValid('0 12 * * 1-5'));
        $this->assertTrue($this->helper->isValid('@hourly'));
        $this->assertTrue($this->helper->isValid('@daily'));
    }

    public function testIsValidReturnsFalseForInvalidExpression(): void
    {
        $this->assertFalse($this->helper->isValid('not-a-cron'));
        $this->assertFalse($this->helper->isValid('99 * * * *'));
        $this->assertFalse($this->helper->isValid(''));
    }

    public function testComputeNextRunReturnsDifferentTimesForDifferentExpressions(): void
    {
        $nextMinute = $this->helper->computeNextRun('* * * * *');
        $nextHour = $this->helper->computeNextRun('0 * * * *');

        // Hourly next run should be >= minutely next run
        $this->assertGreaterThanOrEqual($nextMinute->getTimestamp(), $nextHour->getTimestamp());
    }
}
