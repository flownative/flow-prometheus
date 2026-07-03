<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\Counter;
use Flownative\Prometheus\Storage\InMemoryStorage;
use Neos\Flow\Tests\UnitTestCase;

class CounterTest extends UnitTestCase
{
    /**
     * @test
     */
    public function gettersReturnCounterProperties(): void
    {
        $name = 'flownative_prometheus_test_hits_total';
        $help = 'A counter for testing';
        $labels = ['status' => 200, 'test' => 1];

        $counter = new Counter(new InMemoryStorage(), $name, $help, $labels);
        self::assertSame($name, $counter->getName());
        self::assertSame($help, $counter->getHelp());
        self::assertSame($labels, $counter->getLabels());
    }

    /**
     * @test
     */
    public function getIdentifierReturnsGeneratedString(): void
    {
        $name = 'flownative_prometheus_test_hits_total';
        $help = 'A counter for testing';
        $labels = ['status' => 200, 'test' => 1];

        $counter = new Counter(new InMemoryStorage(), $name, $help, $labels);
        $expectedIdentifier = ':' . Counter::TYPE . ':' . $name;
        self::assertSame($expectedIdentifier, $counter->getIdentifier());
    }

    /**
     * @test
     * @throws \Exception
     */
    public function incIncreasesCounterByOne(): void
    {
        $storage = new InMemoryStorage();

        $counter = new Counter($storage,'test');
        $counter->inc();

        $metrics = $storage->collect();
        $samples = $metrics[$counter->getIdentifier()]->getSamples();
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
     * @throws \Exception
     */
    public function incIncreasesCounterByGivenValue($firstValue, $secondValue, $expectedResult): void
    {
        $storage = new InMemoryStorage();

        $counter = new Counter($storage,'test');
        $counter->inc($firstValue);
        $counter->inc($secondValue);

        $metrics = $storage->collect();
        $samples = $metrics[$counter->getIdentifier()]->getSamples();
        self::assertCount(1, $samples);
        self::assertSame($expectedResult, reset($samples)->getValue());
    }

    /**
     * @test
     * @throws \Exception
     */
    public function incSupportsLabels(): void
    {
        $storage = new InMemoryStorage();

        $counter = new Counter($storage,'http_responses_total');
        $counter->inc(5, ['code' => 200]);
        $counter->inc(2, ['code' => 404]);
        $counter->inc(3, ['code' => 200]);

        $metrics = $storage->collect();
        $samples = $metrics[$counter->getIdentifier()]->getSamples();

        self::assertCount(2, $samples);

        foreach($samples as $sample) {
            switch ($sample->getLabels()['code']) {
                case 200:
                    self::assertSame(8, $sample->getValue());
                break;
                case 404:
                    self::assertSame(2, $sample->getValue());
                break;
                default:
                    self::fail(sprintf('Unexpected code %s', $sample->getLabels()['code']));
            }
        }
    }
}
