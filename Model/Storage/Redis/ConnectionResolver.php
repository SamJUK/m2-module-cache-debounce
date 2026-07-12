<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model\Storage\Redis;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\LocalizedException;

class ConnectionResolver
{
    private const CONFIG_PATH_DEDICATED = 'cache_debounce/redis';
    private const CONFIG_PATH_SHARED_FALLBACK = 'cache/frontend/page_cache/backend_options';

    /** @var DeploymentConfig $deploymentConfig */
    private $deploymentConfig;

    /** @var \Credis_Client|null $client */
    private $client;

    public function __construct(
        DeploymentConfig $deploymentConfig
    ) {
        $this->deploymentConfig = $deploymentConfig;
    }

    /**
     * @throws LocalizedException when no usable connection can be resolved.
     */
    public function getClient(): \Credis_Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $dedicated = $this->deploymentConfig->get(self::CONFIG_PATH_DEDICATED);
        $config = $dedicated ? $this->normalize($dedicated) : $this->resolveFallback();

        if (!$config) {
            throw new LocalizedException(__(
                'SamJUK_CacheDebounce: no Redis connection could be resolved. Configure the '
                . '"cache_debounce.redis" block in app/etc/env.php, or configure a Redis backend '
                . 'for the "page_cache" cache frontend.'
            ));
        }

        $this->client = new \Credis_Client(
            $config['host'],
            (int)$config['port'],
            null,
            '',
            (int)$config['database'],
            $config['password'] ?: null
        );

        return $this->client;
    }

    private function resolveFallback(): ?array
    {
        $backendOptions = $this->deploymentConfig->get(self::CONFIG_PATH_SHARED_FALLBACK);

        if (!$backendOptions) {
            return null;
        }

        return [
            'host' => $backendOptions['server'] ?? '127.0.0.1',
            'port' => $backendOptions['port'] ?? 6379,
            'password' => $backendOptions['password'] ?? '',
            'database' => $backendOptions['database'] ?? 0,
        ];
    }

    /**
     * Fill in defaults for whichever of host/port/database/password the
     * "cache_debounce.redis" env.php block leaves out.
     */
    private function normalize(array $config): array
    {
        return [
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? 6379,
            'password' => $config['password'] ?? '',
            'database' => $config['database'] ?? 0,
        ];
    }
}
