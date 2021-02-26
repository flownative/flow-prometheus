<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit\Http;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\Counter;
use Flownative\Prometheus\CollectorRegistry;
use Flownative\Prometheus\Exception\InvalidCollectorTypeException;
use Flownative\Prometheus\Http\MetricsExporterMiddleware;
use Flownative\Prometheus\Storage\InMemoryStorage;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Tests\UnitTestCase;

class MetricsExporterMiddlewareTest extends UnitTestCase
{
    /**
     * @return void
     */
    public function setUp(): void
    {
        putenv('FLOWNATIVE_PROMETHEUS_ENABLE=true');
    }

    /**
     * @test
     * @throws
     */
    public function middlewareIgnoresRequestsWithNonMatchingPath(): void
    {
        $middleware = new MetricsExporterMiddleware();
        $middleware->injectCollectorRegistry(new CollectorRegistry(new InMemoryStorage()));

        $handler = new DummyRequestHandler();
        $request = new ServerRequest('GET', new Uri('http://localhost/foo'));

        $response = $middleware->process($request, $handler);

        self::assertTrue($handler->isHandleCalled());
        self::assertTrue($response->hasHeader('X-Dummy-Request-Handler'));
    }

    /**
     * @test
     * @throws InvalidCollectorTypeException
     */
    public function middlewareRendersMetrics(): void
    {
        $handler = new DummyRequestHandler();
        $request = new ServerRequest('GET', new Uri('http://localhost/metrics'));

        $storage = new InMemoryStorage();
        $collectorRegistry = new CollectorRegistry($storage);

        $collectorRegistry->register('test_counter', Counter::TYPE, 'This is a simple counter');
        $collectorRegistry->getCounter('test_counter')->inc(5);

        $middleware = new MetricsExporterMiddleware();
        $middleware->injectCollectorRegistry($collectorRegistry);

        $response = $middleware->process($request, $handler);

        $expectedOutput = <<<'EOD'
# HELP test_counter This is a simple counter
# TYPE test_counter counter
test_counter 5
EOD;
        self::assertSame($expectedOutput, $response->getBody()->getContents());
        self::assertSame('text/plain; version=0.0.4; charset=UTF-8', $response->getHeader('Content-Type')[0]);
    }

    /**
     * @test
     */
    public function middlewareRendersCommentIfNoCollectorsAreRegistered(): void
    {
        $middleware = new MetricsExporterMiddleware();
        $middleware->injectCollectorRegistry(new CollectorRegistry(new InMemoryStorage()));

        $handler = new DummyRequestHandler();
        $request = new ServerRequest('GET', new Uri('http://localhost/metrics'));

        $response = $middleware->process($request, $handler);

        $expectedOutput = "# Flownative Prometheus Metrics Exporter: There are no collectors registered at the registry.\n";
        self::assertSame($expectedOutput, $response->getBody()->getContents());
    }

    /**
     * @test
     * @throws
     */
    public function middlewareRendersCommentIfNoMetricsExist(): void
    {
        $handler = new DummyRequestHandler();
        $request = new ServerRequest('GET', new Uri('http://localhost/metrics'));

        $storage = new InMemoryStorage();
        $collectorRegistry = new CollectorRegistry($storage);

        $collectorRegistry->register('test_counter', Counter::TYPE, 'This is a simple counter');

        $middleware = new MetricsExporterMiddleware();
        $middleware->injectCollectorRegistry($collectorRegistry);

        $response = $middleware->process($request, $handler);

        $expectedOutput = "# Flownative Prometheus Metrics Exporter: There are currently no metrics with data to export.\n";
        self::assertSame($expectedOutput, $response->getBody()->getContents());
    }

    /**
     * @test
     */
    public function telemetryPathIsConfigurable(): void
    {
        $middleware = new MetricsExporterMiddleware(['telemetryPath' => '/metrix']);
        $middleware->injectCollectorRegistry(new CollectorRegistry(new InMemoryStorage()));

        $handler = new DummyRequestHandler();
        $request = new ServerRequest('GET', new Uri('http://localhost/metrix'));
        $response = $middleware->process($request, $handler);
        self::assertFalse($handler->isHandleCalled());
        self::assertNotEmpty($response->getBody()->getContents());

        $handler = new DummyRequestHandler();
        $request = new ServerRequest('GET', new Uri('http://localhost/metrics'));
        $middleware->process($request, $handler);
        self::assertTrue($handler->isHandleCalled());
    }

    /**
     * @test
     */
    public function middlewareRequiresHttpBasicAuthIfConfigured(): void
    {
        $middleware = new MetricsExporterMiddleware([
            'basicAuth' => [
                'username' => 'prometheus',
                'password' => 'password',
                'realm' => '👑'
            ]
        ]);
        $middleware->injectCollectorRegistry(new CollectorRegistry(new InMemoryStorage()));

        $handler = new DummyRequestHandler();
        $request = new ServerRequest('GET', new Uri('http://localhost/metrics'));
        $response = $middleware->process($request, $handler);

        $authenticateHeaders = $response->getHeader('WWW-Authenticate');
        self::assertCount(1, $authenticateHeaders);
        self::assertSame('Basic realm="👑", charset="UTF-8"', $authenticateHeaders[0]);
    }

    /**
     * @test
     */
    public function middlewareAcceptsCorrectHttpBasicAuthIfConfigured(): void
    {
        $middleware = new MetricsExporterMiddleware([
            'basicAuth' => [
                'username' => 'prometheus',
                'password' => 'password',
                'realm' => '👑'
            ]
        ]);
        $middleware->injectCollectorRegistry(new CollectorRegistry(new InMemoryStorage()));

        $handler = new DummyRequestHandler();
        $request = new ServerRequest(
            'GET',
            new Uri('http://localhost/metrics'),
            [
                'Authorization' => 'Basic ' . base64_encode('prometheus:password')
            ]
        );
        $response = $middleware->process($request, $handler);
        $authenticateHeaders = $response->getHeader('WWW-Authenticate');

        self::assertCount(0, $authenticateHeaders);
        self::assertNotEmpty($response->getBody()->getContents());
        self::assertFalse($handler->isHandleCalled());
    }

    /**
     * @test
     * @throws
     */
    public function middlewareDeniesIncorrectHttpBasicAuthIfConfigured(): void
    {
        $middleware = new MetricsExporterMiddleware([
            'basicAuth' => [
                'username' => 'prometheus',
                'password' => 'password',
                'realm' => '👑'
            ]
        ]);
        $middleware->injectCollectorRegistry(new CollectorRegistry(new InMemoryStorage()));

        $handler = new DummyRequestHandler();
        $request = new ServerRequest(
            'GET',
            new Uri('http://localhost/metrics'),
            [
                'Authorization' => 'Basic ' . base64_encode('prometheus:wrong-password')
            ]
        );

        $this->expectExceptionCode(1614338257);
        $middleware->process($request, $handler);
    }
}
