<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Magento\CacheInvalidate\Model\PurgeCache;
use Magento\Framework\FlagManager;
use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;
use SamJUK\CacheDebounce\Model\Config;
use SamJUK\CacheDebounce\Model\Entries;
use SamJUK\CacheDebounce\Model\LagDetector;
use SamJUK\CacheDebounce\Model\StaggeredFlush;
use SamJUK\CacheDebounce\Model\Storage\QueueStorageInterface;

class StaggeredFlushTest extends TestCase
{
    private const BATCH_ID = 'batch-123';

    private $config;
    private $storage;
    private $purgeCache;
    private $lockManager;
    private $lagDetector;
    private $flagManager;
    private $entries;
    private $logger;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('isStaggerEnabled')->willReturn(true);
        $this->config->method('getStaggerBatchSize')->willReturn(50);
        $this->config->method('getStaggerIntervalMs')->willReturn(0);
        $this->config->method('getStaggerMaxRuntimeSeconds')->willReturn(999);

        $this->storage = $this->createMock(QueueStorageInterface::class);
        $this->storage->method('activeBatch')->willReturn('');

        $this->purgeCache = $this->createMock(PurgeCache::class);
        $this->purgeCache->method('sendPurgeRequest')->willReturn(true);

        $this->lockManager = $this->createMock(LockManagerInterface::class);
        $this->lockManager->method('lock')->willReturn(true);

        $this->lagDetector = $this->createMock(LagDetector::class);
        $this->flagManager = $this->createMock(FlagManager::class);
        $this->entries = $this->createMock(Entries::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function staggeredFlush(): StaggeredFlush
    {
        return new StaggeredFlush(
            $this->config,
            $this->storage,
            $this->purgeCache,
            $this->lockManager,
            $this->lagDetector,
            $this->flagManager,
            $this->entries,
            $this->logger
        );
    }

    public function testFallsBackToASingleShotFlushWhenStaggerIsDisabled()
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('isStaggerEnabled')->willReturn(false);

        $this->entries->expects($this->once())->method('flush');
        $this->lockManager->expects($this->never())->method('lock');

        $this->staggeredFlush()->execute();
    }

    public function testFailedLockAcquisitionExitsWithoutCallingClaim()
    {
        $this->lockManager = $this->createMock(LockManagerInterface::class);
        $this->lockManager->method('lock')->willReturn(false);

        $this->storage->expects($this->never())->method('claim');
        $this->lockManager->expects($this->never())->method('unlock');

        $this->staggeredFlush()->execute();
    }

    public function testNothingPendingUnlocksWithoutPurging()
    {
        $this->storage->method('claim')->willReturn('');

        $this->purgeCache->expects($this->never())->method('sendPurgeRequest');
        $this->lockManager->expects($this->once())->method('unlock');

        $this->staggeredFlush()->execute();
    }

    public function testResumesAnAlreadyClaimedActiveBatchInsteadOfReclaiming()
    {
        $this->storage = $this->createMock(QueueStorageInterface::class);
        $this->storage->method('activeBatch')->willReturn(self::BATCH_ID);
        $this->storage->method('tags')->with(self::BATCH_ID)->willReturn(['cat_c_1']);
        $this->storage->expects($this->never())->method('claim');
        $this->storage->expects($this->once())->method('clear')->with(self::BATCH_ID);

        // A resumed batch's post-drain pendingCount() can include tags that
        // arrived before this run started, so it's not sampled for lag.
        $this->lagDetector->expects($this->never())->method('recordSample');

        $this->staggeredFlush()->execute();
    }

    public function testChunksTagsAccordingToBatchSize()
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('isStaggerEnabled')->willReturn(true);
        $this->config->method('getStaggerBatchSize')->willReturn(2);
        $this->config->method('getStaggerIntervalMs')->willReturn(0);
        $this->config->method('getStaggerMaxRuntimeSeconds')->willReturn(999);
        $this->storage->method('claim')->willReturn(self::BATCH_ID);
        $this->storage->method('tags')->willReturn(['a', 'b', 'c', 'd', 'e']);

        $chunks = [];
        $this->purgeCache->expects($this->exactly(3))
            ->method('sendPurgeRequest')
            ->willReturnCallback(function ($chunk) use (&$chunks) {
                $chunks[] = $chunk;
                return true;
            });

        $this->staggeredFlush()->execute();

        $this->assertSame([['a', 'b'], ['c', 'd'], ['e']], $chunks);
    }

    public function testStopsAndDoesNotClearOnceTheRuntimeBudgetIsExceededMidRun()
    {
        // Multiple chunks, so the budget check (which only applies from the
        // second chunk onward) has a chance to actually stop the run.
        $this->config = $this->createMock(Config::class);
        $this->config->method('isStaggerEnabled')->willReturn(true);
        $this->config->method('getStaggerBatchSize')->willReturn(1);
        $this->config->method('getStaggerIntervalMs')->willReturn(0);
        $this->config->method('getStaggerMaxRuntimeSeconds')->willReturn(0);
        $this->storage->method('claim')->willReturn(self::BATCH_ID);
        $this->storage->method('tags')->willReturn(['cat_c_1', 'cat_c_2']);

        $this->purgeCache->expects($this->once())->method('sendPurgeRequest')->willReturn(true);
        $this->storage->expects($this->never())->method('clear');
        $this->lagDetector->expects($this->never())->method('recordSample');
        $this->lockManager->expects($this->once())->method('unlock');

        $this->staggeredFlush()->execute();
    }

    public function testAlwaysSendsTheFirstChunkEvenWithAnExhaustedRuntimeBudget()
    {
        // A misconfigured (e.g. zero) budget must never block *every*
        // chunk — otherwise the batch can never drain, ever, on any run.
        $this->config = $this->createMock(Config::class);
        $this->config->method('isStaggerEnabled')->willReturn(true);
        $this->config->method('getStaggerBatchSize')->willReturn(50);
        $this->config->method('getStaggerIntervalMs')->willReturn(0);
        $this->config->method('getStaggerMaxRuntimeSeconds')->willReturn(0);
        $this->storage->method('claim')->willReturn(self::BATCH_ID);
        $this->storage->method('tags')->willReturn(['cat_c_1']);
        $this->storage->method('pendingCount')->willReturn(0);

        $this->purgeCache->expects($this->once())->method('sendPurgeRequest')->with(['cat_c_1'])->willReturn(true);
        $this->storage->expects($this->once())->method('clear')->with(self::BATCH_ID);

        $this->staggeredFlush()->execute();
    }

    public function testAFailedPurgeChunkLeavesTheBatchClaimedAndStops()
    {
        $this->storage->method('claim')->willReturn(self::BATCH_ID);
        $this->storage->method('tags')->willReturn(['cat_c_1', 'cat_c_2']);
        $this->purgeCache = $this->createMock(PurgeCache::class);
        $this->purgeCache->method('sendPurgeRequest')->willReturn(false);

        $this->storage->expects($this->never())->method('clear');
        $this->lagDetector->expects($this->never())->method('recordSample');

        $this->staggeredFlush()->execute();
    }

    public function testFullyDrainedBatchIsClearedAndRecordedForLagDetection()
    {
        $this->storage->method('claim')->willReturn(self::BATCH_ID);
        $this->storage->method('tags')->willReturn(['cat_c_1', 'cat_c_2']);
        $this->storage->method('pendingCount')->willReturn(1);

        $this->storage->expects($this->once())->method('clear')->with(self::BATCH_ID);
        $this->lagDetector->expects($this->once())->method('recordSample')->with(2, 1);
        $this->flagManager->expects($this->exactly(2))->method('saveFlag');

        $this->staggeredFlush()->execute();
    }
}
