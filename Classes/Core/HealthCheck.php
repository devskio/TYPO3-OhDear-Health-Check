<?php
namespace Devskio\Typo3OhDearHealthCheck\Core;

use Devskio\Typo3OhDearHealthCheck\Events\HealthCheckAfterRunEvent;
use Devskio\Typo3OhDearHealthCheck\Checks\AbstractCheck;
use OhDear\HealthCheckResults\CheckResults;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Class OhDearHealthCheck
 * @package Devskio\Typo3OhDearHealthCheck\Controller
 */
class HealthCheck extends ActionController
{
    /**
     * @var int
     */
    const CACHE_LIFETIME_DEFAULT = 3600;

    /**
     * identifier
     *
     * @var string
     */
    const IDENTIFIER = 'typo3_ohdear_health_check';

    /**
     * @var \TYPO3\CMS\Core\Cache\Frontend\FrontendInterface
     */
    protected $cache;

    /**
     * @var int
     */
    protected $cachingTime;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

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
     * Action to perform the health check and return the data as JSON.
     * @throws PropagateResponseException
     */
    public function run(string $content, array $conf, ServerRequestInterface $request): string
    {
        if (!$this->checkSecret($request)) {
             $this->throwStatus(403, 'Forbidden');
        }

        // Check if the result is cached
        if (isset($this->cache)) {
            $cachedResult = $this->cache->get(self::IDENTIFIER);
            if ($cachedResult !== false) {
                return $cachedResult;
            }
        }

        // Run all checks
        $checkResults = new CheckResults(\DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s')));

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['typo3_ohdear_health_check']['checks'] as $checkClass) {
            $classConfiguration = $this->extensionConfiguration->get(self::IDENTIFIER)[$checkClass::getIdentifier()] ?? [];
            /** @var AbstractCheck $checkInstance */
            $checkInstance = GeneralUtility::makeInstance($checkClass, $classConfiguration);
            $checkResults->addCheckResult($checkInstance->run());
        }

        // Dispatch event
        $event = new HealthCheckAfterRunEvent($checkResults);

        $this->eventDispatcher->dispatch($event);

        $result = $checkResults->toJson();

        // Cache the result
        if (isset($this->cache)) {
            $this->cache->set(self::IDENTIFIER, $result, [], $this->cachingTime);
        }

        return $result;
    }


    /**
     * Check if the secret is set and matches the one from the request header.
     * @param ServerRequestInterface $request
     * @return bool
     */
    public function checkSecret(ServerRequestInterface $request): bool
    {
        $extensionConfig = $this->extensionConfiguration->get(self::IDENTIFIER);
        $ohdearSecretConfig = $extensionConfig['ohdearHealthCheckSecret'];
        $ohdearSecretHeader = $request->getHeader('oh-dear-health-check-secret')[0] ?? '';
        return !empty($ohdearSecretConfig) && $ohdearSecretConfig === $ohdearSecretHeader;
    }
}
