<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Magento\Framework\FlagManager;
use Magento\Framework\Notification\NotifierInterface;
use Psr\Log\LoggerInterface;
use SamJUK\CacheDebounce\Model\Config;
use SamJUK\CacheDebounce\Model\LagDetector;

class LagDetectorTest extends TestCase
{
    private $config;
    private $flagManager;
    private $notifier;
    private $logger;
    private $detector;

    /** @var mixed persisted flag value, backing the FlagManager mock like a real store would */
    private $flagState;

    protected function setUp(): void
    {
        $this->flagState = null;

        $this->config = $this->createMock(Config::class);
        $this->config->method('getStaggerLagRatioThreshold')->willReturn(1.0);
        $this->config->method('getStaggerLagAlertAfterRuns')->willReturn(3);

        $this->flagManager = $this->createMock(FlagManager::class);
        $this->flagManager->method('getFlagData')->willReturnCallback(function () {
            return $this->flagState;
        });
        $this->flagManager->method('saveFlag')->willReturnCallback(function ($code, $value) {
            $this->flagState = $value;
            return true;
        });
        $this->flagManager->method('deleteFlag')->willReturnCallback(function () {
            $this->flagState = null;
            return true;
        });

        $this->notifier = $this->createMock(NotifierInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->detector = new LagDetector($this->config, $this->flagManager, $this->notifier, $this->logger);
    }

    /**
     * @param array<array{0: int, 1: int}> $samples pairs of [claimed, arrivedDuring]
     */
    private function runSequence(array $samples): void
    {
        foreach ($samples as [$claimed, $arrivedDuring]) {
            $this->detector->recordSample($claimed, $arrivedDuring);
        }
    }

    public function testNonLaggingSampleDeletesTheCounterAndNeverNotifies()
    {
        $this->notifier->expects($this->never())->method('addMajor');

        $this->runSequence([[10, 5]]);

        $this->assertNull($this->flagState);
    }

    public function testRatioAtExactlyTheThresholdCountsAsLagging()
    {
        $this->runSequence([[10, 10]]);

        $this->assertSame(1, $this->flagState);
    }

    public function testZeroClaimedNeverCountsAsLaggingRegardlessOfArrivals()
    {
        $this->notifier->expects($this->never())->method('addMajor');

        $this->runSequence([[0, 5]]);

        $this->assertNull($this->flagState);
    }

    public function testConsecutiveLaggingRunsAccumulateAndNotifyOnlyOnReachingTheThreshold()
    {
        $this->notifier->expects($this->once())->method('addMajor');
        $this->logger->expects($this->once())->method('warning');

        // lag_alert_after_runs = 3: first two lagging runs stay silent.
        $this->runSequence([[10, 20], [10, 20], [10, 20]]);

        $this->assertSame(3, $this->flagState);
    }

    public function testANonLaggingRunResetsTheCounterSoTheNextLagStartsFromOne()
    {
        $this->notifier->expects($this->never())->method('addMajor');

        $this->runSequence([[10, 20], [10, 20], [10, 5], [10, 20]]);

        $this->assertSame(1, $this->flagState);
    }

    public function testSustainedLagReNotifiesEveryAlertAfterRunsWithoutResetting()
    {
        $this->notifier->expects($this->exactly(2))->method('addMajor');

        $this->runSequence([
            [10, 20], [10, 20], [10, 20], // 3rd run: 1st notification
            [10, 20], [10, 20], [10, 20], // 6th run: 2nd notification
        ]);

        $this->assertSame(6, $this->flagState);
    }
}
