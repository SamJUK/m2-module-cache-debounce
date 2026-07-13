<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use SamJUK\CacheDebounce\Model\Config as CacheDebounceConfig;

class ConfigTest extends TestCase
{
    public function testIsModuleEnabled()
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $config = new CacheDebounceConfig($scopeConfig);
        $this->assertFalse($config->isModuleEnabled());

        $scopeConfig->method('isSetFlag')->willReturn(1);
        $this->assertTrue($config->isModuleEnabled());
    }

    public function testShouldDebouncePurgeRequestDependsOnModuleEnableState()
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $config = new CacheDebounceConfig($scopeConfig);
        $this->assertFalse($config->shouldDebouncePurgeRequest());

        $scopeConfig->method('isSetFlag')->willReturn(1);
        $this->assertTrue($config->shouldDebouncePurgeRequest());
    }

    public function testShouldDebouncePurgeRequestEnabledFlag()
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $config = new CacheDebounceConfig($scopeConfig);
        $scopeConfig->method('isSetFlag')->willReturn(1);
        $this->assertTrue($config->shouldDebouncePurgeRequest());
        $config->setShouldDebouncePurgeRequest(false);
        $this->assertFalse($config->shouldDebouncePurgeRequest());
    }

    public function testIsStaggerEnabled()
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $config = new CacheDebounceConfig($scopeConfig);
        $this->assertFalse($config->isStaggerEnabled());

        $scopeConfig->method('isSetFlag')->willReturn(1);
        $this->assertTrue($config->isStaggerEnabled());
    }

    public function testStaggerNumericGetters()
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            ['samjuk_cache_debounce/stagger/batch_size', 'default', null, '50'],
            ['samjuk_cache_debounce/stagger/interval_ms', 'default', null, '1000'],
            ['samjuk_cache_debounce/stagger/max_runtime_seconds', 'default', null, '240'],
            ['samjuk_cache_debounce/stagger/lag_ratio_threshold', 'default', null, '1.5'],
            ['samjuk_cache_debounce/stagger/lag_alert_after_runs', 'default', null, '3'],
        ]);
        $config = new CacheDebounceConfig($scopeConfig);

        $this->assertSame(50, $config->getStaggerBatchSize());
        $this->assertSame(1000, $config->getStaggerIntervalMs());
        $this->assertSame(240, $config->getStaggerMaxRuntimeSeconds());
        $this->assertSame(1.5, $config->getStaggerLagRatioThreshold());
        $this->assertSame(3, $config->getStaggerLagAlertAfterRuns());
    }
}
