<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Integration\Cron;

use PHPUnit\Framework\TestCase;
use Magento\Cron\Model\ConfigInterface as CronConfig;
use Magento\Cron\Model\Groups\Config\Data as GroupsConfig;
use Magento\TestFramework\ObjectManager;
use SamJUK\CacheDebounce\Cron\Flush;

class GroupTest extends TestCase
{
    private const GROUP = 'samjuk_cache_debounce';
    private const JOB = 'samjuk_cache_debounce_flush';

    public function testFlushJobRunsInItsOwnGroup()
    {
        $jobs = ObjectManager::getInstance()->get(CronConfig::class)->getJobs();

        $this->assertArrayNotHasKey(self::JOB, $jobs['default'] ?? []);
        $this->assertSame(Flush::class, $jobs[self::GROUP][self::JOB]['instance'] ?? null);
    }

    public function testGroupRunsInASeparateProcess()
    {
        $group = ObjectManager::getInstance()->get(GroupsConfig::class)->getByGroupId(self::GROUP);

        $this->assertSame('1', $group['use_separate_process']['value'] ?? null);
    }
}
