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
use Neos\Flow\Http\Component\ComponentChain;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Request;
use Neos\Flow\Http\Response;
use Neos\Flow\Http\Uri;
use Neos\Flow\Tests\UnitTestCase;

class MetricsExporterComponentTest extends UnitTestCase
{
    /**
     * @test
     * @throws InvalidCollectorTypeException
     */
    public function componentIgnoresRequestsWithNonMatchingPath(): void
    {
        $componentContext = new ComponentContext(Request::create(new Uri('http://localhost/foo')), new Response());
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
        $componentContext = new ComponentContext(Request::create(new Uri('http://localhost/metrics')), new Response());
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
    }
}
