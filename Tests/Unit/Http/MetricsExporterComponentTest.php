<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\Counter;
use Flownative\Prometheus\CollectorRegistry;
use Flownative\Prometheus\Exception\InvalidCollectorTypeException;
use Flownative\Prometheus\Http\MetricsExporterComponent;
use Flownative\Prometheus\Storage\InMemoryStorage;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Http\Component\ComponentChain;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Tests\UnitTestCase;

class MetricsExporterComponentTest extends UnitTestCase
{
    /**
     * @test
     */
    public function componentIgnoresRequestsWithNonMatchingPath(): void
    {
        $componentContext = new ComponentContext(new ServerRequest('GET', new Uri('http://localhost/foo')), new Response());
        $componentContext->setParameter(ComponentChain::class, 'cancel', false);

        $httpComponent = new MetricsExporterComponent();
        $httpComponent->injectCollectorRegistry(new CollectorRegistry(new InMemoryStorage()));
        $httpComponent->handle($componentContext);

        self::assertFalse($componentContext->getParameter(ComponentChain::class, 'cancel'));
    }

    /**
     * @test
     * @throws InvalidCollectorTypeException
     */
    public function componentRendersMetrics(): void
    {
        $componentContext = new ComponentContext(new ServerRequest('GET', new Uri('http://localhost/metrics')), new Response());
        $componentContext->setParameter(ComponentChain::class, 'cancel', false);

        $storage = new InMemoryStorage();
        $collectorRegistry = new CollectorRegistry($storage);

        $collectorRegistry->register('test_counter', Counter::TYPE, 'This is a simple counter');
        $collectorRegistry->getCounter('test_counter')->inc(5);

        $httpComponent = new MetricsExporterComponent();
        $httpComponent->injectCollectorRegistry($collectorRegistry);
        $httpComponent->handle($componentContext);

        $expectedOutput = <<<'EOD'
# HELP test_counter This is a simple counter
# TYPE test_counter counter
test_counter 5
EOD;
        self::assertTrue($componentContext->getParameter(ComponentChain::class, 'cancel'));
        self::assertSame($expectedOutput, $componentContext->getHttpResponse()->getBody()->getContents());
        self::assertSame('text/plain; version=0.0.4; charset=UTF-8', $componentContext->getHttpResponse()->getHeader('Content-Type')[0]);
    }

    /**
     * @test
     */
    public function componentRendersCommentIfNoMetricsExist(): void
    {
        $componentContext = new ComponentContext(new ServerRequest('GET', new Uri('http://localhost/metrics')), new Response());

        $httpComponent = new MetricsExporterComponent();
        $httpComponent->injectCollectorRegistry(new CollectorRegistry(new InMemoryStorage()));
        $httpComponent->handle($componentContext);

        $expectedOutput = "# Flownative Prometheus Metrics Exporter: There are currently no metrics with data to export.\n";
        self::assertSame($expectedOutput, $componentContext->getHttpResponse()->getBody()->getContents());
    }

    /**
     * @test
     */
    public function telemetryPathIsConfigurable(): void
    {
        $httpComponent = new MetricsExporterComponent(['telemetryPath' => '/different-metrics']);
        $httpComponent->injectCollectorRegistry(new CollectorRegistry(new InMemoryStorage()));

        $componentContext = new ComponentContext(new ServerRequest('GET', new Uri('http://localhost/metrics')), new Response());
        $httpComponent->handle($componentContext);
        self::assertEmpty($componentContext->getHttpResponse()->getBody()->getContents());

        $componentContext = new ComponentContext(new ServerRequest('GET', new Uri('http://localhost/different-metrics')), new Response());
        $httpComponent->handle($componentContext);
        self::assertNotEmpty($componentContext->getHttpResponse()->getBody()->getContents());
    }
}
