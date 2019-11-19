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
use Flownative\Prometheus\Storage\InMemoryStorage;
use Neos\Flow\Tests\UnitTestCase;

class CollectorRegistryTest extends UnitTestCase
{
    /**
     * @return array
     */
    public function validConfigurations(): array
    {
        return [
            [
                [
                    'flownative_prometheus_test_hits_total' => ['type' => 'counter', 'help' => 'A counter for testing', 'labels' => ['flownative', 'prometheus', 'test']],
                    'flownative_prometheus_test_temperature' => ['type' => 'gauge', 'help' => 'A temperature for testing', 'labels' => ['flownative', 'prometheus', 'test']],
                ]
            ]
        ];
    }

    /**
     * @test
     * @dataProvider validConfigurations()
     * @param array $collectorConfigurations
     * @throws InvalidCollectorTypeException
     */
    public function registerManyRegistersCollectorsDefinedInArray(array $collectorConfigurations): void
    {
        $registry = new CollectorRegistry(new InMemoryStorage());
        $registry->registerMany($collectorConfigurations);

        $name = array_key_first($collectorConfigurations);
        $counter = $registry->getCounter($name);
        self::assertNotNull($counter);
        self::assertSame($name, $counter->getName());

        $name = array_key_last($collectorConfigurations);
        $gauge = $registry->getGauge($name);
        self::assertNotNull($gauge);
        self::assertSame($name, $gauge->getName());
    }

    /**
     * @test
     * @throws InvalidCollectorTypeException
     */
    public function registerAlsoRegistersCollectorAtStorage(): void
    {
        $storage = new InMemoryStorage();
        $registry = new CollectorRegistry($storage);
        $registry->register('flownative_test_metric', 'counter');

        $sampleCollections = $storage->collect();
        self::assertCount(1, $sampleCollections);

        $collector = reset($sampleCollections);
        self::assertSame('flownative_test_metric', $collector->getName());
    }

    /**
     * @test
     * @dataProvider validConfigurations()
     * @param array $collectorConfigurations
     * @throws InvalidCollectorTypeException
     */
    public function constructorRegistersGivenCollectors(array $collectorConfigurations): void
    {
        $registry = new CollectorRegistry(new InMemoryStorage(), $collectorConfigurations);
        $name = array_key_first($collectorConfigurations);
        $counter = $registry->getCounter($name);
        self::assertNotNull($counter);
        self::assertSame($name, $counter->getName());
    }

    /**
     * @test
     * @throws InvalidCollectorTypeException
     */
    public function unregisterUnregistersGivenCollector(): void
    {
        $registry = new CollectorRegistry(new InMemoryStorage());

        $registry->register('flownative_prometheus_test_calls_total', Counter::TYPE);
        self::assertNotNull($registry->getCounter('flownative_prometheus_test_calls_total'));
        $registry->unregister('flownative_prometheus_test_calls_total');
        self::assertNull($registry->getCounter('flownative_prometheus_test_calls_total'));
    }

    /**
     * @test
     * @throws InvalidCollectorTypeException
     */
    public function getSampleCollectionsCollectsSamplesFromStorage(): void
    {
        $registry = new CollectorRegistry(new InMemoryStorage());
        $registry->register('flownative_prometheus_test_calls_total', Counter::TYPE, 'a test call counter', ['tests', 'counter']);

        $counterA = $registry->getCounter('flownative_prometheus_test_calls_total');
        $counterA->inc(1);

        $sampleCollections = $registry->collect();

        self::assertCount(1, $sampleCollections);
    }

    /**
     * @test
     * @throws InvalidCollectorTypeException
     */
    public function getCounterReturnsRegisteredCounter(): void
    {
        $registry = new CollectorRegistry(new InMemoryStorage());
        $registry->register('flownative_prometheus_test_calls_total', Counter::TYPE, 'a test call counter', ['tests', 'counter']);

        $counterA = $registry->getCounter('flownative_prometheus_test_calls_total');
        $counterB = $registry->getCounter('flownative_prometheus_test_calls_total');
        self::assertNotNull($counterA);
        self::assertSame($counterA, $counterB);

        $registry->register('flownative_prometheus_test_other_calls_total', Counter::TYPE, 'another test call counter', ['tests', 'counter']);
        $otherCounter = $registry->getCounter('flownative_prometheus_test_other_calls_total');
        self::assertNotSame($counterA, $otherCounter);
    }
}
