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
     * Note: In an Objects.yaml this injection is pre-defined to inject the DefaultCollectorRegisry
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
        if ($componentContext->getHttpRequest()->getUri()->getPath() !== '/metrics') {
            return;
        }
        $renderer = new Renderer();
        $body = ContentStream::fromContents($renderer->render($this->collectorRegistry->collect()));

        $componentContext->replaceHttpResponse($componentContext->getHttpResponse()->withBody($body));
        $componentContext->setParameter(ComponentChain::class, 'cancel', true);
    }
}
