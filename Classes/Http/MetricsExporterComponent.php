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
use Neos\Flow\Http\Component\ComponentChain;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Neos\Flow\Http\ContentStream;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP component which renders Prometheus metrics
 */
class MetricsExporterComponent implements ComponentInterface
{
    /**
     * @var CollectorRegistry
     */
    protected $collectorRegistry;

    /**
     * @var array
     */
    protected $options = [
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
     * @param ComponentContext $componentContext
     */
    public function handle(ComponentContext $componentContext): void
    {
        if ($componentContext->getHttpRequest()->getUri()->getPath() !== $this->options['telemetryPath']) {
            return;
        }

        $componentContext->setParameter(ComponentChain::class, 'cancel', true);

        if ($this->options['basicAuth']['username'] !== '' && $this->options['basicAuth']['password'] !== '') {
            if ($this->authenticateWithBasicAuth($componentContext) === false) {
                $response = $this->createResponseWithAuthenticateHeader($componentContext->getHttpResponse());
                $componentContext->replaceHttpResponse($response);
                return;
            }
        }

        $response = $this->createResponseWithRenderedMetrics($componentContext->getHttpResponse());
        $componentContext->replaceHttpResponse($response);
    }

    /**
     * @param ResponseInterface $existingResponse
     * @return ResponseInterface
     */
    private function createResponseWithRenderedMetrics(ResponseInterface $existingResponse): ResponseInterface
    {
        $renderer = new Renderer();
        $output = $renderer->render($this->collectorRegistry->collect());
        if ($output === '') {
            $output = "# Flownative Prometheus Metrics Exporter: There are currently no metrics with data to export.\n";
        }

        return $existingResponse
            ->withBody(ContentStream::fromContents($output))
            ->withHeader('Content-Type', 'text/plain; version=' . $renderer->getFormatVersion() . '; charset=UTF-8');
    }

    /**
     * @param ResponseInterface $existingResponse
     * @return ResponseInterface
     */
    private function createResponseWithAuthenticateHeader(ResponseInterface $existingResponse): ResponseInterface
    {
        return $existingResponse
            ->withHeader('WWW-Authenticate', 'Basic realm="' . $this->options['basicAuth']['realm'] . '", charset="UTF-8"');
    }

    /**
     * @param ComponentContext $componentContext
     * @return bool
     */
    private function authenticateWithBasicAuth(ComponentContext $componentContext): bool
    {
        $authorizationHeaders = $componentContext->getHttpRequest()->getHeader('Authorization');
        if (count($authorizationHeaders) !== 1) {
            return false;
        }
        $authorizationHeader = $authorizationHeaders[0];

        if (strpos($authorizationHeader, 'Basic ') !== 0) {
            return false;
        }

        $credentials = base64_decode(substr($authorizationHeader, 6));
        [$givenUsername, $givenPassword] = explode(':', $credentials, 2);

        if (
            $givenUsername !== $this->options['basicAuth']['username'] ||
            $givenPassword !== $this->options['basicAuth']['password']
        ) {
            $componentContext->replaceHttpResponse($componentContext->getHttpResponse()->withStatus(403));
            return false;
        }

        return true;
    }

}
