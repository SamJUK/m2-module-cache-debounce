<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Model\Config\Source;

use PHPUnit\Framework\TestCase;
use SamJUK\CacheDebounce\Model\Config\Source\StorageDriver;

class StorageDriverTest extends TestCase
{
    public function testToOptionArrayListsEachRegisteredDriver()
    {
        $values = array_column((new StorageDriver())->toOptionArray(), 'value');

        $this->assertSame(['db', 'redis'], $values);
    }
}
