<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Integration\Model\Queue;

use PHPUnit\Framework\TestCase;
use Magento\TestFramework\ObjectManager;

class DatabaseTest extends TestCase
{
    private const CACHE_TAGS = ['cat_c_1', 'cat_c_2', 'cat_c_p_1'];

    protected $objectManager;
    private $queue;

    protected function setUp(): void
    {
        $this->objectManager = ObjectManager::getInstance();
        $this->queue = $this->objectManager->get(\SamJUK\CacheDebounce\Api\QueueInterface::class);
    }

    public function testDatabaseStorage()
    {
        $this->queue->add(self::CACHE_TAGS);
        $this->assertEquals(self::CACHE_TAGS, $this->queue->get());

        $this->queue->flush();
        $this->assertEquals([], $this->queue->get());
    }
}
