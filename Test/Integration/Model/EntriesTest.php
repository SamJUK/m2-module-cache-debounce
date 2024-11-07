<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Integration\Model;

use PHPUnit\Framework\TestCase;
use Magento\TestFramework\ObjectManager;

class EntriesTest extends TestCase
{
    private const CACHE_TAGS = ['cat_c_1', 'cat_c_2', 'cat_c_p_1'];

    protected $objectManager;
    private $cacheDebouncedEntries;

    protected function setUp(): void
    {
        $this->objectManager = ObjectManager::getInstance();
        $this->cacheDebouncedEntries = $this->objectManager->get(\SamJUK\CacheDebounce\Model\Entries::class);
    }

    public function testDebouncedEntriesStorage()
    {
        $this->cacheDebouncedEntries->add(self::CACHE_TAGS);
        $this->assertEquals(self::CACHE_TAGS, $this->cacheDebouncedEntries->get());

        $this->cacheDebouncedEntries->flush();
        $this->assertEquals([], $this->cacheDebouncedEntries->get());
    }
}
