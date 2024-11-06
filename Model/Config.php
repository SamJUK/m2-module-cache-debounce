<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    private const XML_PATH_GENERAL_ENABLED = 'samjuk_cache_debounce/general/enabled';
    private const XML_PATH_CRON_FLUSH = 'samjuk_cache_debounce/cron/flush';

    private $shouldDebouncePurgeRequest = true;

    private $scopeConfig;
    
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function isModuleEnabled()
    {
        return $this->getFlag(self::XML_PATH_GENERAL_ENABLED);
    }

    public function shouldDebouncePurgeRequest()
    {
        return $this->isModuleEnabled() && $this->shouldDebouncePurgeRequest;
    }

    public function setShouldDebouncePurgeRequest($state)
    {
        $this->shouldDebouncePurgeRequest = $state;
    }

    private function getFlag($path, $scope = 'default', $scopeCode = null)
    {
        return (bool)$this->scopeConfig->isSetFlag($path, $scope, $scopeCode);
    }

    private function getValue($path, $scope = 'default', $scopeCode = null)
    {
        return $this->scopeConfig->getValue($path, $scope, $scopeCode);
    }
}
