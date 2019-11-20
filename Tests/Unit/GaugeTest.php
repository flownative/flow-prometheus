<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\Gauge;
use Flownative\Prometheus\Storage\InMemoryStorage;
use Neos\Flow\Tests\UnitTestCase;

class GaugeTest extends UnitTestCase
{
    /**
     * @test
     */
    public function gettersReturnGaugeProperties(): void
    {
        $name = 'flownative_prometheus_test_sessions';
        $help = 'A gauge for testing';
        $labels = ['status' => 200, 'test' => 1];

        $gauge = new Gauge(new InMemoryStorage(), $name, $help, $labels);
        self::assertSame($name, $gauge->getName());
        self::assertSame($help, $gauge->getHelp());
        self::assertSame($labels, $gauge->getLabels());
    }

    /**
     * @test
     */
    public function getIdentifierReturnsGeneratedString(): void
    {
        $name = 'flownative_prometheus_test_sessions';
        $help = 'A gauge for testing';
        $labels = ['state' => 'active'];

        $gauge = new Gauge(new InMemoryStorage(), $name, $help, $labels);
        $expectedIdentifier = ':' . Gauge::TYPE . ':' . $name;
        self::assertSame($expectedIdentifier, $gauge->getIdentifier());
    }

    /**
     * @test
     */
    public function incIncreasesGaugeByOne(): void
    {
        $storage = new InMemoryStorage();

        $gauge = new Gauge($storage,'test');
        $gauge->inc();

        $metrics = $storage->collect();
        $samples = $metrics[$gauge->getIdentifier()]->getSamples();

        self::assertCount(1, $samples);
        self::assertSame(1, reset($samples)->getValue());
    }

    /**
     * @return array
     */
    public function increaseValues(): array
    {
        return [
            [1, 2, 3],
            [5.5, 4.5, 10.0],
            [5, 0, 5]
        ];
    }

    /**
     * @test
     * @dataProvider increaseValues
     * @param $firstValue
     * @param $secondValue
     * @param $expectedResult
     */
    public function incIncreasesGaugeByGivenValue($firstValue, $secondValue, $expectedResult): void
    {
        $storage = new InMemoryStorage();

        $gauge = new Gauge($storage,'test');
        $gauge->inc($firstValue);
        $gauge->inc($secondValue);

        $metrics = $storage->collect();
        $samples = $metrics[$gauge->getIdentifier()]->getSamples();
        self::assertCount(1, $samples);
        self::assertSame($expectedResult, reset($samples)->getValue());
    }

    /**
     * @test
     */
    public function decDecreasesGaugeByOne(): void
    {
        $storage = new InMemoryStorage();

        $gauge = new Gauge($storage,'test');
        $gauge->dec();

        $metrics = $storage->collect();
        $samples = $metrics[$gauge->getIdentifier()]->getSamples();

        self::assertCount(1, $samples);
        self::assertSame(-1, reset($samples)->getValue());
    }

    /**
     * @test
     */
    public function setSetsTheGaugesValue(): void
    {
        $storage = new InMemoryStorage();

        $gauge = new Gauge($storage,'test');
        $gauge->set(42);

        $metrics = $storage->collect();
        $samples = $metrics[$gauge->getIdentifier()]->getSamples();

        self::assertCount(1, $samples);
        self::assertSame(42, reset($samples)->getValue());
    }

    /**
     * @test
     */
    public function setToCurrentTimeSetsValueToCurrentUnixTimestamp(): void
    {
        $storage = new InMemoryStorage();
        $currentTime = time();

        $gauge = new Gauge($storage,'test');
        $gauge->setToCurrentTime();

        $metrics = $storage->collect();
        $samples = $metrics[$gauge->getIdentifier()]->getSamples();

        self::assertCount(1, $samples);

        $actualValue = reset($samples)->getValue();
        self::assertGreaterThanOrEqual($currentTime, $actualValue);
        self::assertLessThan($currentTime + 5, $actualValue);
    }

    /**
     * @test
     */
    public function incSupportsLabels(): void
    {
        $storage = new InMemoryStorage();

        $gauge = new Gauge($storage,'flownative_prometheus_test_sessions');
        $gauge->inc(5, ['state' => 'active']);
        $gauge->inc(2, ['state' => 'expired']);
        $gauge->inc(3, ['state' => 'active']);

        $metrics = $storage->collect();
        $samples = $metrics[$gauge->getIdentifier()]->getSamples();

        self::assertCount(2, $samples);

        foreach($samples as $sample) {
            switch ($sample->getLabels()['state']) {
                case 'active':
                    self::assertSame(8, $sample->getValue());
                break;
                case 'expired':
                    self::assertSame(2, $sample->getValue());
                break;
                default:
                    self::fail(sprintf('Unexpected state %s', $sample->getLabels()['state']));
            }
        }
    }
}
