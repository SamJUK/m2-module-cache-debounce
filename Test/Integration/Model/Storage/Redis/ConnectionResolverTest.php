<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Integration\Model\Storage\Redis;

use PHPUnit\Framework\TestCase;
use Magento\Framework\App\DeploymentConfig;
use Magento\TestFramework\ObjectManager;
use SamJUK\CacheDebounce\Model\Storage\Redis\ConnectionResolver;

/**
 * Requires a Redis server reachable at 127.0.0.1:6379.
 */
class ConnectionResolverTest extends TestCase
{
    private const CANARY_KEY = 'samjuk_cache_debounce:test:canary';
    private const DEDICATED_DATABASE = 3;
    private const FALLBACK_DATABASE = 4;

    private $dedicatedResolver;
    private $fallbackResolver;

    protected function setUp(): void
    {
        $reader = ObjectManager::getInstance()->get(DeploymentConfig\Reader::class);

        $dedicatedConfig = new DeploymentConfig($reader, ['cache_debounce' => ['redis' => [
            'host' => '127.0.0.1',
            'port' => '6379',
            'password' => '',
            'database' => (string)self::DEDICATED_DATABASE,
        ]]]);
        $fallbackConfig = new DeploymentConfig($reader, ['cache' => ['frontend' => ['page_cache' => [
            'backend_options' => [
                'server' => '127.0.0.1',
                'port' => '6379',
                'password' => '',
                'database' => (string)self::FALLBACK_DATABASE,
            ],
        ]]]]);

        $this->dedicatedResolver = new ConnectionResolver($dedicatedConfig);
        $this->fallbackResolver = new ConnectionResolver($fallbackConfig);

        $this->dedicatedResolver->getClient()->flushDb();
        $this->fallbackResolver->getClient()->flushDb();
    }

    protected function tearDown(): void
    {
        $this->dedicatedResolver->getClient()->flushDb();
        $this->fallbackResolver->getClient()->flushDb();
    }

    public function testResolvesTheDedicatedConnection()
    {
        $client = $this->dedicatedResolver->getClient();

        $client->set(self::CANARY_KEY, '1');
        $this->assertSame('1', $client->get(self::CANARY_KEY));
    }

    public function testFallsBackToThePageCacheConnectionWhenNoDedicatedBlockIsConfigured()
    {
        $client = $this->fallbackResolver->getClient();

        $client->set(self::CANARY_KEY, '1');
        $this->assertSame('1', $client->get(self::CANARY_KEY));
    }
}
