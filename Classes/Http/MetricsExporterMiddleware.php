<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Http;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\CollectorRegistry;
use Flownative\Prometheus\Renderer;
use GuzzleHttp\Psr7\Response;
use Neos\Flow\Http\ContentStream;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\Security\Exception\AccessDeniedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * PSR-15 middleware which renders Prometheus metrics
 */
class MetricsExporterMiddleware implements MiddlewareInterface
{
    /**
     * @var CollectorRegistry
     */
    protected CollectorRegistry $collectorRegistry;

    /**
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $logger = null;

    /**
     * @var array
     */
    protected array $options = [
        'telemetryPath' => '/metrics',
        'basicAuth' => []
    ];

    /**
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->options = array_merge($this->options, $options);

        $this->options['basicAuth']['username'] = $this->options['basicAuth']['username'] ?? '';
        $this->options['basicAuth']['password'] = $this->options['basicAuth']['password'] ?? '';
        $this->options['basicAuth']['realm'] = $this->options['basicAuth']['realm'] ?? 'Flownative Prometheus Plugin';
    }

    /**
     * Note: In an Objects.yaml this injection is pre-defined to inject the DefaultCollectorRegistry
     *
     * @param CollectorRegistry $collectorRegistry
     */
    public function injectCollectorRegistry(CollectorRegistry $collectorRegistry): void
    {
        $this->collectorRegistry = $collectorRegistry;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function injectLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws AccessDeniedException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (getenv('FLOWNATIVE_PROMETHEUS_ENABLE') !== 'true') {
            return $handler->handle($request);
        }

        if ($request->getUri()->getPath() !== $this->options['telemetryPath']) {
            return $handler->handle($request);
        }

        if ($this->options['basicAuth']['username'] !== '' && $this->options['basicAuth']['password'] !== '') {
            $authenticated =  $this->authenticateWithBasicAuth($request);
            if (!$authenticated) {
                return $this->createResponseWithAuthenticateHeader();
            }
        }

        return $this->createResponseWithRenderedMetrics();
    }

    /**
     * @return ResponseInterface
     */
    private function createResponseWithRenderedMetrics(): ResponseInterface
    {
        $renderer = new Renderer();
        if ($this->collectorRegistry->hasCollectors()) {
            $output = $renderer->render($this->collectorRegistry->collect());
            if ($output === '') {
                $output = "# Flownative Prometheus Metrics Exporter: There are currently no metrics with data to export.\n";
            }
        } else {
            $output = "# Flownative Prometheus Metrics Exporter: There are no collectors registered at the registry.\n";
        }

        return new Response(
            200,
            ['Content-Type' => 'text/plain; version=' . $renderer->getFormatVersion() . '; charset=UTF-8'],
            ContentStream::fromContents($output)
        );
    }

    /**
     * @return ResponseInterface
     */
    private function createResponseWithAuthenticateHeader(): ResponseInterface
    {
        return new Response(200, ['WWW-Authenticate' => 'Basic realm="' . $this->options['basicAuth']['realm'] . '", charset="UTF-8"']);
    }

    /**
     * @param ServerRequestInterface $request
     * @return bool
     * @throws AccessDeniedException
     */
    private function authenticateWithBasicAuth(ServerRequestInterface $request): bool
    {
        $authorizationHeaders = $request->getHeader('Authorization');
        if ($authorizationHeaders === []) {
            if ($this->logger) {
                $this->logger->info('No authorization header found, asking for authentication for Prometheus telemetry endpoint', LogEnvironment::fromMethodName(__METHOD__));
            }
            return false;
        }

        foreach ($authorizationHeaders as $possibleAuthorizationHeader) {
            if (strpos($possibleAuthorizationHeader, 'Basic ') === 0) {
                $authorizationHeader = $possibleAuthorizationHeader;
                break;
            }
        }

        if (!isset($authorizationHeader)) {
            if ($this->logger) {
                $this->logger->warning('Failed authenticating for Prometheus telemetry endpoint, no "Basic" authorization header found', LogEnvironment::fromMethodName(__METHOD__));
            }
            return false;
        }

        $credentials = base64_decode(substr($authorizationHeader, 6));
        [$givenUsername, $givenPassword] = explode(':', $credentials, 2);

        if (
            $givenUsername !== $this->options['basicAuth']['username'] ||
            $givenPassword !== $this->options['basicAuth']['password']
        ) {
            if ($this->logger) {
                $this->logger->warning('Failed authenticating for Prometheus telemetry endpoint: wrong username or password');
            }
            throw new AccessDeniedException('Failed authenticating for Prometheus telemetry endpoint: wrong username or password', 1614338257);
        }
        return true;
    }
}
