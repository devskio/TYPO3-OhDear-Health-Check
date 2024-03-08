<?php
namespace Devskio\Typo3OhDearHealthCheck\Core;

use DateTime;
use Devskio\Typo3OhDearHealthCheck\Checks\DiskUsedSpace;
use Devskio\Typo3OhDearHealthCheck\Checks\ForgottenFiles;
use Devskio\Typo3OhDearHealthCheck\Checks\MySqlSize;
use Devskio\Typo3OhDearHealthCheck\Checks\PhpErrorLogSize;
use Devskio\Typo3OhDearHealthCheck\Checks\Typo3DatabaseLog;
use Devskio\Typo3OhDearHealthCheck\Checks\Typo3Version;
use Devskio\Typo3OhDearHealthCheck\Checks\VarFolderSize;
use OhDear\HealthCheckResults\CheckResults;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Devskio\Typo3OhDearHealthCheck\Events\CustomHealthCheckEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class OhDearHealthCheck
 * @package Devskio\Typo3OhDearHealthCheck\Controller
 */
class HealthCheck extends ActionController
{

    /**
     * Array of check classes.
     *
     * @var array
     */
    private $checkClasses = [
        DiskUsedSpace::class,
        ForgottenFiles::class,
        MySqlSize::class,
        PhpErrorLogSize::class,
        Typo3DatabaseLog::class,
        Typo3Version::class,
        VarFolderSize::class,
    ];

    /**
     * Cache identifier
     *
     * @var array
     */
    const CACHE_IDENTIFIER = 'typo3_ohdear_health_check';

    /**
     * @var \TYPO3\CMS\Core\Cache\Frontend\FrontendInterface
     */
    protected $cache;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('typo3_ohdear_health_check');
        $this->eventDispatcher = $eventDispatcher;

        $config = $this->extensionConfiguration->get('typo3_ohdear_health_check');
        $this->cachingTime = $config['cachingTime'] ?? $config['defaultCachingTime'];
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

        if (isset($this->cache)) {
            $cachedResult = $this->cache->get(self::CACHE_IDENTIFIER);
            if ($cachedResult !== false) {
                return $cachedResult;
            }
        }

        $checkResults = new CheckResults(DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s')));
        foreach ($this->checkClasses as $checkClass) {
            $checkInstance = GeneralUtility::makeInstance($checkClass, $this->extensionConfiguration);
            $checkResults->addCheckResult($checkInstance->run());
        }

        $event = new CustomHealthCheckEvent($checkResults);
        $this->eventDispatcher->dispatch($event);

        $result = $checkResults->toJson() ?? "";

        if (isset($this->cache)) {
            $this->cache->set(self::CACHE_IDENTIFIER, $result, [], $this->cachingTime);
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
        $extensionConfig = $this->extensionConfiguration->get('typo3_ohdear_health_check');
        $ohdearSecretConfig = $extensionConfig['ohdearHealthCheckSecret'];
        $ohdearSecretHeader = $request->getHeader('oh-dear-health-check-secret')[0] ?? '';
        return !empty($ohdearSecretConfig) && $ohdearSecretConfig === $ohdearSecretHeader;
    }
}
