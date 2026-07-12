<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Model;

use Magento\CacheInvalidate\Model\PurgeCache;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SamJUK\CacheDebounce\Model\Config as CacheDebounceConfig;
use SamJUK\CacheDebounce\Model\Entries as CacheDebounceEntries;
use SamJUK\CacheDebounce\Model\Storage\QueueStorageInterface;

class EntriesTest extends TestCase
{
    private const BATCH_ID = 'batch-123';
    private const CACHE_TAGS = ['cat_c_1', 'cat_c_2', 'cat_c_p_1'];

    private $cacheDebounceConfig;
    private $purgeCacheModel;
    private $storage;
    private $loggerInterface;
    private $cacheDebounceEntries;

    protected function setUp(): void
    {
        $this->cacheDebounceConfig = $this->createMock(CacheDebounceConfig::class);
        $this->storage = $this->createMock(QueueStorageInterface::class);
        $this->purgeCacheModel = $this->createMock(PurgeCache::class);
        $this->loggerInterface = $this->createMock(LoggerInterface::class);
        $this->cacheDebounceEntries = new CacheDebounceEntries(
            $this->cacheDebounceConfig,
            $this->purgeCacheModel,
            $this->storage,
            $this->loggerInterface
        );
    }

    public function testFlushDoesNothingWhenNothingIsClaimed()
    {
        $this->storage->method('activeBatch')->willReturn('');
        $this->storage->method('claim')->willReturn('');

        $this->purgeCacheModel->expects($this->never())->method('sendPurgeRequest');

        $this->cacheDebounceEntries->flush();
    }

    public function testFlushPurgesClaimedTagsAndClearsBatch()
    {
        $this->storage->method('activeBatch')->willReturn('');
        $this->storage->method('claim')->willReturn(self::BATCH_ID);
        $this->storage->method('tags')->with(self::BATCH_ID)->willReturn(self::CACHE_TAGS);

        $this->purgeCacheModel->expects($this->once())
            ->method('sendPurgeRequest')
            ->with(self::CACHE_TAGS)
            ->willReturn(true);

        $this->storage->expects($this->once())->method('clear')->with(self::BATCH_ID);

        $this->cacheDebounceEntries->flush();
    }

    public function testFlushResumesAnAlreadyClaimedBatchInsteadOfClaimingNew()
    {
        $this->storage->method('activeBatch')->willReturn(self::BATCH_ID);
        $this->storage->method('tags')->with(self::BATCH_ID)->willReturn(self::CACHE_TAGS);
        $this->storage->expects($this->never())->method('claim');

        $this->purgeCacheModel->method('sendPurgeRequest')->willReturn(true);

        $this->storage->expects($this->once())->method('clear')->with(self::BATCH_ID);

        $this->cacheDebounceEntries->flush();
    }

    public function testFlushReleasesBatchInsteadOfClearingWhenPurgeRequestFails()
    {
        $this->storage->method('activeBatch')->willReturn('');
        $this->storage->method('claim')->willReturn(self::BATCH_ID);
        $this->storage->method('tags')->willReturn(self::CACHE_TAGS);

        $this->purgeCacheModel->expects($this->once())
            ->method('sendPurgeRequest')
            ->with(self::CACHE_TAGS)
            ->willReturn(false);

        $this->storage->expects($this->never())->method('clear');
        $this->storage->expects($this->once())->method('release')->with(self::BATCH_ID);

        $this->loggerInterface->expects($this->once())->method('error');

        $this->cacheDebounceEntries->flush();
    }

    public function testFlushResetsDebounceFlagEvenWhenPurgeRequestThrows()
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);
        $config = new CacheDebounceConfig($scopeConfig);

        $this->storage->method('activeBatch')->willReturn('');
        $this->storage->method('claim')->willReturn(self::BATCH_ID);
        $this->storage->method('tags')->willReturn(self::CACHE_TAGS);
        $this->purgeCacheModel->method('sendPurgeRequest')->willThrowException(new \RuntimeException('varnish down'));

        $entries = new CacheDebounceEntries(
            $config,
            $this->purgeCacheModel,
            $this->storage,
            $this->loggerInterface
        );

        $this->storage->expects($this->never())->method('clear');
        $this->storage->expects($this->once())->method('release')->with(self::BATCH_ID);

        $this->assertTrue($config->shouldDebouncePurgeRequest());

        try {
            $entries->flush();
            $this->fail('Expected sendPurgeRequest exception to propagate');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertTrue($config->shouldDebouncePurgeRequest());
    }

    public function testAddDelegatesToStorage()
    {
        $this->storage->expects($this->once())->method('add')->with(self::CACHE_TAGS);

        $this->cacheDebounceEntries->add(self::CACHE_TAGS);
    }

    public function testAddWithEmptyTagsStillDelegatesToStorage()
    {
        $this->storage->expects($this->once())->method('add')->with([]);

        $this->cacheDebounceEntries->add([]);
    }
}
