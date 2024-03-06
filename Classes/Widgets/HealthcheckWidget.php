<?php
declare(strict_types=1);

namespace Devskio\Typo3OhDearHealthCheck\Widgets;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

class HealthcheckWidget implements WidgetInterface
{
    /**
     * @var WidgetConfigurationInterface
     */
    private $configuration;

    /**
     * @var StandaloneView
     */
    private $view;

    /**
     * @var string
     */
    const API_URL_APPLICATION_HEALTH = 'https://ohdear.app/api/sites/59595/application-health-checks';

    /**
     * @var string
     */
    const API_URL_SITE = 'https://ohdear.app/api/sites/59595';


    /**
     * @var string
     */
    const ACCESS_TOKEN = 'GC4h0e5cSZ5d8LQcXBVlcVSQ3GYlkRZ4XqmPGLxe94ab1dc4';

    /**
     * @var RequestFactory
     */
    private $requestFactory;

    /**
     * @var BackendUserAuthentication $backendUser
     */
    private $backendUser = null;


    /**
     * HealthcheckWidget constructor.
     *
     * @param WidgetConfigurationInterface $configuration
     * @param StandaloneView $view
     * @param RequestFactory $requestFactory
     * @param BackendUserAuthentication $backendUser
     */
    public function __construct(
        WidgetConfigurationInterface $configuration,
        StandaloneView $view,
        RequestFactory $requestFactory,
        BackendUserAuthentication $backendUser
    ) {
        $this->configuration = $configuration;
        $this->view = $view;
        $this->requestFactory = $requestFactory;
        $this->backendUser = $backendUser;

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
        if ($GLOBALS['BE_USER']->isAdmin()) {
            $applicationHealthChecks = $this->getApiData(self::API_URL_APPLICATION_HEALTH)['data'] ?? null;
        }

        $this->view->assignMultiple([
            'configuration' => $this->configuration,
            'applicationHealthResults' => $applicationHealthChecks ?? null,
            'basicChecks' => $this->getApiData(self::API_URL_SITE)['checks'] ?? null,
        ]);
        return $this->view->render();
    }

    /**
     * Fetches data from the Oh Dear API.
     * @param string $apiUrl The URL to fetch data from.
     *
     * @return array|null The fetched data, or null if the fetch failed.
     */
    public function getApiData(string $apiUrl): array|null
    {
        $response = $this->requestFactory->request(
            $apiUrl,
            'GET',
            ['headers' => ['Authorization' => 'Bearer ' . self::ACCESS_TOKEN]]
        );

        $body = $response->getBody()->getContents() ?? null;
        if (isset($body)) {
            return json_decode($body, true);
        }
        return null;
    }
}
