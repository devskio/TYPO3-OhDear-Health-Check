<?php
declare(strict_types=1);

namespace Devskio\Typo3OhDearHealthCheck\Widgets;

use OhDear\PhpSdk\OhDear;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class HealthcheckWidget implements WidgetInterface
{
    const CHECKS_ENDPOINT_MAP = [
        'uptime' => 'uptime/report',
        'performance' => 'performance/report',
        'certificate-health' => 'certificate-health/report',
        'broken-links' => 'broken-links/report',
        'cron' => 'scheduled-tasks/list',
        'application-health' => 'application-health/run',
        'dns' => 'dns/latest',
        'domain' => 'domain/report',
    ];

    private OhDear $ohDear;
    private int $siteId;

    /**
     * @var string
     */
    private string $summaryStatus;

    /**
     * HealthcheckWidget constructor.
     *
     * @param WidgetConfigurationInterface $configuration
     * @param ExtensionConfiguration $extensionConfiguration
     * @param StandaloneView $view
     */
    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        ExtensionConfiguration $extensionConfiguration,
        protected readonly ?StandaloneView $view = null,
    ) {
        $this->ohDear = new OhDear($extensionConfiguration->get('typo3_ohdear_health_check')['ohDearApiKey']);
        $this->siteId = (int)$extensionConfiguration->get('typo3_ohdear_health_check')['ohDearSiteId'] ?? 0;

        $this->view->setTemplateRootPaths(
            [GeneralUtility::getFileAbsFileName('EXT:typo3_ohdear_health_check/Resources/Private/Templates')]
        );
        $this->view->setPartialRootPaths(
            [GeneralUtility::getFileAbsFileName('EXT:typo3_ohdear_health_check/Resources/Private/Partials')]
        );
        $this->view->setTemplate('Widget/Healthcheck');
    }

    /**
     * Renders the widget content.
     *
     * @return string The rendered widget content.
     */
    public function renderWidgetContent(): string
    {
        $this->view->assignMultiple([
            'configuration' => $this->configuration,
        ]);
        if ($GLOBALS['BE_USER']->isAdmin()) {
            try {
                $applicationHealthChecks = $this->ohDear->applicationHealthChecks($this->siteId);

                foreach ($applicationHealthChecks as $check) {
                    $this->controlSummaryStatus($check->status);
                }

                if (!empty($this->siteId) && isset($this->ohDear)) {
                    $site = $this->ohDear->site($this->siteId);
                }

                foreach ($site->checks as $check) {
                    $this->controlSummaryStatus($check->attributes['latest_run_result']);
                    $check->type = $this->formatCheckType($check->type);
                }

                $this->view->assignMultiple([
                    'applicationHealthResults' => $applicationHealthChecks ?? null,
                    'basicChecks' => $site->checks ?? null,
                    'siteId' => $this->siteId,
                    'summaryStatus' => $this->summaryStatus,
                    'checksEndpointMap' => self::CHECKS_ENDPOINT_MAP,
                ]);

            } catch (\Exception $e) {
                $this->view->assignMultiple([
                    'error' => [
                        'label' => LocalizationUtility::translate(
                            'LLL:EXT:typo3_ohdear_health_check/Resources/Private/Language/locallang_backend.xlf:connection.error'
                        ),
                        'instructions' => LocalizationUtility::translate(
                            'LLL:EXT:typo3_ohdear_health_check/Resources/Private/Language/locallang_backend.xlf:connection.error.instructions'
                        ),
                        'message' => $e->getMessage()
                    ]
                ]);
            }
        }

        return $this->view->render();
    }

    /**
     * Formats the check type to a more human-readable format.
     *
     * @param string $checkType
     * @return string
     */
    private function formatCheckType(string $checkType): string
    {
        return str_replace('_', '-', $checkType);
    }

    /**
     * Controlling the summary status
     *
     * @param string $checkSummary
     * @return void
     */
    private function controlSummaryStatus(string $checkSummary): void
    {
        if ($this->summaryStatus === 'danger') {
            return;
        }
        if ($this->summaryStatus === 'warning' && $checkSummary !== 'danger') {
            return;
        }

        switch ($checkSummary) {
            case 'warning':
                $this->summaryStatus = 'warning';
                break;
            case 'failed':
                $this->summaryStatus = 'danger';
                break;
            default:
                $this->summaryStatus = 'success';
        }
    }

    public function getOptions(): array
    {
        return [];
    }
}
