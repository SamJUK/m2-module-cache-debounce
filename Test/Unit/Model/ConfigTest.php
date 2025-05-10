<?php

declare(strict_types=1);

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
}
