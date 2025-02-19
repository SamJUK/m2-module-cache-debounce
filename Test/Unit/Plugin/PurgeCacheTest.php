<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use SamJUK\CacheDebounce\Model\Config as CacheDebounceConfig;
use SamJUK\CacheDebounce\Api\QueueInterface;
use SamJUK\CacheDebounce\Plugin\PurgeCache as PurgeCachePlugin;
use Magento\CacheInvalidate\Model\PurgeCache as PurgeCacheModel;

class PurgeCacheTest extends TestCase
{
    public function testPluginDebouncesPurgeRequest()
    {
        $purgeCacheModel = $this->createMock(PurgeCacheModel::class);
        $cacheDebounceConfig = $this->createMock(CacheDebounceConfig::class);
        $queue = $this->createMock(QueueInterface::class);
        $purgeCachePlugin = new PurgeCachePlugin($cacheDebounceConfig, $queue);

        $cacheDebounceConfig->method('shouldDebouncePurgeRequest')
            ->willReturn(true);

        $purgeCacheModel->expects($this->never())
            ->method('sendPurgeRequest');

        $purgeCachePlugin->aroundSendPurgeRequest(
            $purgeCacheModel,
            [$purgeCacheModel, 'sendPurgeRequest'],
            ["cat_c_2", "cat_c_3"]
        );
    }

    public function testPluginSkipsDebouncePurgeRequest()
    {
        $purgeCacheModel = $this->createMock(PurgeCacheModel::class);
        $cacheDebounceConfig = $this->createMock(CacheDebounceConfig::class);
        $queue = $this->createMock(QueueInterface::class);
        $purgeCachePlugin = new PurgeCachePlugin($cacheDebounceConfig, $queue);

        $cacheDebounceConfig->method('shouldDebouncePurgeRequest')
            ->willReturn(false);

        $purgeCacheModel->expects($this->once())
            ->method('sendPurgeRequest')
            ->willReturn(true);

        $purgeCachePlugin->aroundSendPurgeRequest(
            $purgeCacheModel,
            [$purgeCacheModel, 'sendPurgeRequest'],
            ["cat_c_2", "cat_c_3"]
        );
    }
}
