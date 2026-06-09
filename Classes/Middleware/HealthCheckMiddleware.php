<?php
declare(strict_types=1);

namespace Devskio\Typo3OhDearHealthCheck\Middleware;

use Devskio\Typo3OhDearHealthCheck\Core\HealthCheck;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * PSR-15 middleware handling the Oh Dear application-health endpoint.
 *
 * Intercepts requests matching either:
 *   - the configured path  (default: /healthcheck)
 *   - the legacy typeNum query parameter (?type=1689678601)
 */
class HealthCheckMiddleware implements MiddlewareInterface
{
    /** Legacy typeNum kept for backward compatibility with existing OhDear configs. */
    private const TYPE_NUM = '1689678601';

    /** Default path when none is configured in the extension settings. */
    private const DEFAULT_PATH = '/healthcheck';

    private string $healthCheckPath;

    public function __construct(
        private readonly HealthCheck $healthCheck,
        private readonly ResponseFactoryInterface $responseFactory,
        ExtensionConfiguration $extensionConfiguration,
    ) {
        $config = $extensionConfiguration->get(HealthCheck::IDENTIFIER);
        $this->healthCheckPath = rtrim($config['healthCheckPath'] ?? self::DEFAULT_PATH, '/') ?: self::DEFAULT_PATH;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->isHealthCheckRequest($request)) {
            return $handler->handle($request);
        }

        if (!$this->healthCheck->checkSecret($request)) {
            return $this->responseFactory->createResponse(403, 'Forbidden');
        }

        $result = $this->healthCheck->executeChecks();

        $response = $this->responseFactory->createResponse(200);
        $response->getBody()->write($result);

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('X-Robots-Tag', 'noindex')
            ->withHeader('Cache-Control', 'no-cache, no-store');
    }

    private function isHealthCheckRequest(ServerRequestInterface $request): bool
    {
        // Match the clean /healthcheck path
        $path = rtrim($request->getUri()->getPath(), '/');
        if ($path === $this->healthCheckPath) {
            return true;
        }

        // Fallback: legacy ?type=1689678601 query parameter
        return ($request->getQueryParams()['type'] ?? '') === self::TYPE_NUM;
    }
}
