<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Integration\Model\Storage;

use PHPUnit\Framework\TestCase;
use Magento\Framework\App\DeploymentConfig;
use Magento\TestFramework\ObjectManager;
use SamJUK\CacheDebounce\Model\Storage\Redis;
use SamJUK\CacheDebounce\Model\Storage\Redis\ConnectionResolver;

/**
 * Requires a Redis server reachable at 127.0.0.1:6379.
 */
class RedisTest extends TestCase
{
    private const CONNECTION_CONFIG = [
        'host' => '127.0.0.1',
        'port' => '6379',
        'password' => '',
        'database' => '2',
    ];

    private $storage;
    private $rawClient;

    protected function setUp(): void
    {
        $reader = ObjectManager::getInstance()->get(DeploymentConfig\Reader::class);
        $deploymentConfig = new DeploymentConfig($reader, ['cache_debounce' => ['redis' => self::CONNECTION_CONFIG]]);

        $this->storage = new Redis(new ConnectionResolver($deploymentConfig));

        // Independent resolver instance, not the class under test.
        $this->rawClient = (new ConnectionResolver($deploymentConfig))->getClient();
        $this->rawClient->flushDb();
    }

    protected function tearDown(): void
    {
        $this->rawClient->flushDb();
    }

    public function testAddClaimTagsClearRoundTrip()
    {
        $this->storage->add(['cat_c_1', 'cat_c_2']);

        $batchId = $this->storage->claim();
        $this->assertNotSame('', $batchId);

        $tags = $this->storage->tags($batchId);
        sort($tags);
        $this->assertEquals(['cat_c_1', 'cat_c_2'], $tags);

        $this->storage->clear($batchId);
        $this->assertEquals([], $this->storage->tags($batchId));
    }

    public function testAddDuringClaimIsNotLostOrDuplicated()
    {
        $this->storage->add(['cat_c_1']);

        $batchId = $this->storage->claim();

        $this->storage->add(['cat_c_1', 'cat_c_2']);

        $this->assertEquals(['cat_c_1'], $this->storage->tags($batchId));

        $this->storage->clear($batchId);

        $nextBatchId = $this->storage->claim();
        $tags = $this->storage->tags($nextBatchId);
        sort($tags);
        $this->assertEquals(['cat_c_1', 'cat_c_2'], $tags);

        $this->storage->clear($nextBatchId);
    }

    public function testSecondConcurrentClaimReturnsEmptyString()
    {
        $this->storage->add(['cat_c_1']);

        $firstBatchId = $this->storage->claim();
        $this->assertNotSame('', $firstBatchId);

        $this->assertSame('', $this->storage->claim());

        $this->storage->clear($firstBatchId);
    }

    public function testReleaseMakesTheBatchClaimableAgain()
    {
        $this->storage->add(['cat_c_1', 'cat_c_2']);
        $batchId = $this->storage->claim();

        $this->storage->release($batchId);

        $this->assertEquals([], $this->storage->tags($batchId));

        $nextBatchId = $this->storage->claim();
        $tags = $this->storage->tags($nextBatchId);
        sort($tags);
        $this->assertEquals(['cat_c_1', 'cat_c_2'], $tags);
    }

    public function testReleaseMergesCleanlyWithTagsReQueuedWhileClaimed()
    {
        $this->storage->add(['cat_c_1']);
        $batchId = $this->storage->claim();

        // Same tag re-queued while the original batch is still in flight —
        // release() must not duplicate it; Redis sets dedupe natively.
        $this->storage->add(['cat_c_1']);

        $this->storage->release($batchId);

        $nextBatchId = $this->storage->claim();
        $this->assertEquals(['cat_c_1'], $this->storage->tags($nextBatchId));
    }
}
