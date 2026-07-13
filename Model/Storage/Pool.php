<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model\Storage;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManagerInterface;

class Pool implements QueueStorageInterface
{
    private const XML_PATH_STORAGE_DRIVER = 'samjuk_cache_debounce_advanced/general/storage_driver';
    private const DEFAULT_DRIVER = 'db';

    /** @var ObjectManagerInterface $objectManager */
    private $objectManager;

    /** @var ScopeConfigInterface $scopeConfig */
    private $scopeConfig;

    /** @var string[] $drivers */
    private $drivers;

    /** @var QueueStorageInterface|null $resolved */
    private $resolved;

    public function __construct(
        ObjectManagerInterface $objectManager,
        ScopeConfigInterface $scopeConfig,
        array $drivers = []
    ) {
        $this->objectManager = $objectManager;
        $this->scopeConfig = $scopeConfig;
        $this->drivers = $drivers;
    }

    /**
     * @inheritDoc
     */
    public function add(array $tags): void
    {
        $this->driver()->add($tags);
    }

    /**
     * @inheritDoc
     */
    public function claim(): string
    {
        return $this->driver()->claim();
    }

    /**
     * @inheritDoc
     */
    public function tags(string $batchId): array
    {
        return $this->driver()->tags($batchId);
    }

    /**
     * @inheritDoc
     */
    public function clear(string $batchId): void
    {
        $this->driver()->clear($batchId);
    }

    /**
     * @inheritDoc
     */
    public function release(string $batchId): void
    {
        $this->driver()->release($batchId);
    }

    /**
     * @inheritDoc
     */
    public function pendingCount(): int
    {
        return $this->driver()->pendingCount();
    }

    /**
     * @inheritDoc
     */
    public function activeBatch(): string
    {
        return $this->driver()->activeBatch();
    }

    /**
     * @inheritDoc
     */
    public function oldestPendingAgeSeconds(): ?int
    {
        return $this->driver()->oldestPendingAgeSeconds();
    }

    private function driver(): QueueStorageInterface
    {
        if ($this->resolved === null) {
            $key = (string)$this->scopeConfig->getValue(self::XML_PATH_STORAGE_DRIVER) ?: self::DEFAULT_DRIVER;
            $class = $this->drivers[$key] ?? $this->drivers[self::DEFAULT_DRIVER];
            $this->resolved = $this->objectManager->create($class);
        }

        return $this->resolved;
    }
}
