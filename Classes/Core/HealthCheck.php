<?php
namespace Devskio\Typo3OhDearHealthCheck\Core;

use Devskio\Typo3OhDearHealthCheck\Events\HealthCheckAfterRunEvent;
use Devskio\Typo3OhDearHealthCheck\Checks\AbstractCheck;
use OhDear\HealthCheckResults\CheckResults;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Class HealthCheck
 * @package Devskio\Typo3OhDearHealthCheck\Core
 */
class HealthCheck
{
    const int CACHE_LIFETIME_DEFAULT = 3600;
    const string IDENTIFIER = 'typo3_ohdear_health_check';

    protected FrontendInterface $cache;
    protected int $cachingTime;
    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->cache = GeneralUtility::makeInstance(CacheManager::class)->getCache(self::IDENTIFIER);
        $this->eventDispatcher = $eventDispatcher;

        $config = $this->extensionConfiguration->get(self::IDENTIFIER);
        $this->cachingTime = $config['cachingTime'] ?? self::CACHE_LIFETIME_DEFAULT;
    }

    /**
     * Execute all registered health checks and return the JSON result.
     * Called by HealthCheckMiddleware.
     */
    public function executeChecks(): string
    {
        $currentTime = \DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s'));

        if (isset($this->cache)) {
            $cachedResult = $this->cache->get(self::IDENTIFIER);
            if ($cachedResult !== false) {
                $cachedResult = json_decode($cachedResult, true);
                $cachedResult['finishedAt'] = $currentTime->getTimestamp();
                return json_encode($cachedResult);
            }
        }

        $checkResults = new CheckResults($currentTime);

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['typo3_ohdear_health_check']['checks'] as $checkClass) {
            $classConfiguration = $this->extensionConfiguration->get(self::IDENTIFIER)[$checkClass::getIdentifier()] ?? [];
            /** @var AbstractCheck $checkInstance */
            $checkInstance = GeneralUtility::makeInstance($checkClass, $classConfiguration);
            $checkResults->addCheckResult($checkInstance->run());
        }

        $event = new HealthCheckAfterRunEvent($checkResults);
        $this->eventDispatcher->dispatch($event);

        $result = $checkResults->toJson();

        if (isset($this->cache)) {
            $this->cache->set(self::IDENTIFIER, $result, [], $this->cachingTime);
        }

        return $result;
    }

    /**
     * Verify the Oh Dear secret header against the configured value.
     */
    public function checkSecret(ServerRequestInterface $request): bool
    {
        $extensionConfig = $this->extensionConfiguration->get(self::IDENTIFIER);
        $ohdearSecretConfig = $extensionConfig['ohdearHealthCheckSecret'];
        $ohdearSecretHeader = $request->getHeader('oh-dear-health-check-secret')[0] ?? '';
        return !empty($ohdearSecretConfig) && $ohdearSecretConfig === $ohdearSecretHeader;
    }
}
