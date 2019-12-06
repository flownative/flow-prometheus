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
    protected $options;

    /**
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->options = $options;
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

        $renderer = new Renderer();
        $output = $renderer->render($this->collectorRegistry->collect());
        if ($output === '') {
            $output = "# Flownative Prometheus Metrics Exporter: There are currently no metrics with data to export.\n";
        }
        $body = ContentStream::fromContents($output);

        $response = $componentContext->getHttpResponse()
            ->withBody($body)
            ->withHeader('Content-Type', 'text/plain; version=' . $renderer->getFormatVersion() . '; charset=UTF-8');

        $componentContext->replaceHttpResponse($response);
        $componentContext->setParameter(ComponentChain::class, 'cancel', true);
    }
}
