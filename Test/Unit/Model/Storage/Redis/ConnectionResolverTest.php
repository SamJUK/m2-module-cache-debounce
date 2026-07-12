<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Model\Storage\Redis;

use PHPUnit\Framework\TestCase;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\LocalizedException;
use SamJUK\CacheDebounce\Model\Storage\Redis\ConnectionResolver;

class ConnectionResolverTest extends TestCase
{
    private const DEDICATED_CONFIG = ['host' => '10.0.0.1', 'port' => '6390', 'password' => 'x', 'database' => '2'];
    private const FALLBACK_CONFIG = ['server' => '10.0.0.2', 'port' => '6379', 'password' => '', 'database' => '0'];

    private $deploymentConfig;

    protected function setUp(): void
    {
        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);
    }

    public function testDedicatedConfigTakesPriorityOverTheSharedFallback()
    {
        $this->deploymentConfig->method('get')->willReturnMap([
            ['cache_debounce/redis', null, self::DEDICATED_CONFIG],
            ['cache/frontend/page_cache/backend_options', null, self::FALLBACK_CONFIG],
        ]);

        $this->assertResolvesToClient();
    }

    public function testFallsBackToThePageCacheBackendWhenDedicatedConfigIsAbsent()
    {
        $this->deploymentConfig->method('get')->willReturnMap([
            ['cache_debounce/redis', null, null],
            ['cache/frontend/page_cache/backend_options', null, self::FALLBACK_CONFIG],
        ]);

        $this->assertResolvesToClient();
    }

    public function testThrowsLocalizedExceptionWhenNeitherConfigIsAvailable()
    {
        $this->deploymentConfig->method('get')->willReturn(null);

        $resolver = new ConnectionResolver($this->deploymentConfig);

        $this->expectException(LocalizedException::class);

        $resolver->getClient();
    }

    public function testDedicatedConfigMissingKeysFallsBackToDefaultsInsteadOfErroring()
    {
        $this->deploymentConfig->method('get')->willReturnMap([
            ['cache_debounce/redis', null, ['host' => '10.0.0.9']],
            ['cache/frontend/page_cache/backend_options', null, null],
        ]);

        $this->assertResolvesToClient();
    }

    private function assertResolvesToClient(): void
    {
        $resolver = new ConnectionResolver($this->deploymentConfig);

        $this->assertInstanceOf(\Credis_Client::class, $resolver->getClient());
    }
}
