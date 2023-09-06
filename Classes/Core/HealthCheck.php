<?php
namespace Devskio\Typo3OhDearHealthCheck\Core;

use DateTime;
use Devskio\Typo3OhDearHealthCheck\Service\OhDearHealthCheckService;
use Devskio\Typo3OhDearHealthCheck\Traits\Injection\InjectOhDearHealthCheckService;
use OhDear\HealthCheckResults\CheckResults;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\Response;

/**
 * Class OhDearHealthCheck
 * @package Devskio\Typo3OhDearHealthCheck\Controller
 */
class HealthCheck extends ActionController
{
    use InjectOhDearHealthCheckService;

    /**
     * @var OhDearHealthCheckService
     */
    protected $healthCheckService;

    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
        OhDearHealthCheckService $healthCheckService
    ) {
        $this->healthCheckService = $healthCheckService;;
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

        $checkResults = new CheckResults(DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s')));

        $checkResults->addCheckResult($this->healthCheckService->getUsedDiskSpace());
        $checkResults->addCheckResult($this->healthCheckService->checkPHPErrorLogSize());
        $checkResults->addCheckResult($this->healthCheckService->getTYPO3VarFolderSize());
        $checkResults->addCheckResult($this->healthCheckService->getMysqlSize());
        $checkResults->addCheckResult($this->healthCheckService->scanDocumentRootForForgottenFiles());
        $checkResults->addCheckResult($this->healthCheckService->getTYPO3DBLog());
        $checkResults->addCheckResult($this->healthCheckService->getTYPO3Version());

        return $checkResults->toJson();
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
        $ohdearSecretHeader = $request->getHeader('oh-dear-health-check-secret')[0];

        return !empty($ohdearSecretConfig) && $ohdearSecretConfig === $ohdearSecretHeader;
    }

}
