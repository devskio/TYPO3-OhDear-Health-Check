<?php
declare(strict_types=1);

namespace Devskio\Typo3OhDearHealthCheck\Middleware;

use Devskio\Typo3OhDearHealthCheck\Core\HealthCheck;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware handling the Oh Dear application-health endpoint.
 *
 * The request is intercepted when the query parameter `type` equals the
 * configured TypoScript typeNum (1689678601), so existing OhDear site
 * configurations require no change.
 *
 * This is the TYPO3 14 replacement for the TypoScript PAGE / USER userFunc
 * approach that relied on the removed `config.disableAllHeaderCode`.
 */
class HealthCheckMiddleware implements MiddlewareInterface
{
    /**
     * TypoScript typeNum used to identify health-check requests.
     * Matches the value set in Configuration/TypoScript/setup.typoscript.
     */
    private const TYPE_NUM = '1689678601';

    public function __construct(
        private readonly HealthCheck $healthCheck,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        // Only intercept requests with the dedicated typeNum
        if (($queryParams['type'] ?? '') !== self::TYPE_NUM) {
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
}

