<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class StorageDriver implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'db', 'label' => __('Database (default)')],
            ['value' => 'redis', 'label' => __('Redis')],
        ];
    }
}
