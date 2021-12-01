<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\Counter;
use Flownative\Prometheus\Collector\Gauge;
use Flownative\Prometheus\Sample;
use Flownative\Prometheus\Storage\CounterUpdate;
use Flownative\Prometheus\Storage\GaugeUpdate;
use Flownative\Prometheus\Storage\InMemoryStorage;
use Flownative\Prometheus\Storage\StorageInterface;
use Neos\Flow\Tests\UnitTestCase;

abstract class AbstractStorageTest extends UnitTestCase
{
    /**
     * @var StorageInterface
     */
    protected StorageInterface $storage;

    /**
     * @test
     */
    public function flushRemovesExistingMetrics(): void
    {
        $counter = new Counter($this->storage, 'test_counter');
        $this->storage->registerCollector($counter);
        $this->storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 1, []));

        self::assertCount(1, $this->storage->collect());
        $this->storage->flush();
        self::assertCount(0, $this->storage->collect());
    }

    /**
     * @test
     */
    public function updateCounterIncreasesCounterByGivenValue(): void
    {
        $counter = new Counter($this->storage, 'test_counter');
        $this->storage->registerCollector($counter);

        $this->storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 5, []));
        $this->storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 3, []));
        $this->storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 1.5, []));
        $sampleCollections = $this->storage->collect();

        self::assertCount(1, $sampleCollections);

        $samples = $sampleCollections[$counter->getIdentifier()]->getSamples();
        self::assertCount(1, $samples);
        self::assertSame($samples[0]->getValue(), 9.5);
    }

    /**
     * @test
     */
    public function updateCounterSupportsLabels(): void
    {
        $counter = new Counter($this->storage, 'http_responses_total');
        $this->storage->registerCollector($counter);

        $this->storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 25, ['code' => 200]));
        $this->storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 3, ['code' => 404]));
        $this->storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 43, ['code' => 200]));

        $metrics = $this->storage->collect();
        $samples = $metrics[$counter->getIdentifier()]->getSamples();

        self::assertCount(2, $samples);

        foreach ($samples as $sample) {
            switch ($sample->getLabels()['code']) {
                case 200:
                    self::assertSame(68, $sample->getValue());
                break;
                case 404:
                    self::assertSame(3, $sample->getValue());
                break;
                default:
                    self::fail(sprintf('Unexpected code %s', $sample->getLabels()['code']));
            }
        }
    }

    /**
     * @test
     */
    public function updateCounterSortsSamplesByLabels(): void
    {
        $counter = new Counter($this->storage, 'http_responses_total');
        $this->storage->registerCollector($counter);

        $this->storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 25, ['code' => 200, 'access_protection' => 'no']));
        $this->storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 3, ['code' => 404]));
        $this->storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 105, ['code' => 200, 'access_protection' => 'yes']));
        $this->storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 9, ['code' => 404]));

        $metrics = $this->storage->collect();
        $samples = $metrics[$counter->getIdentifier()]->getSamples();

        self::assertCount(3, $samples);

        self::assertEquals([
            new Sample('http_responses_total', ['access_protection' => 'no', 'code' => 200], 25),
            new Sample('http_responses_total', ['access_protection' => 'yes', 'code' => 200], 105),
            new Sample('http_responses_total', ['code' => 404], 12),
        ], $samples);
    }

    /**
     * @test
     * @throws \Exception
     */
    public function updateCounterResetsCounterIfSetOperationIsSpecified(): void
    {
        $this->storage = new InMemoryStorage();
        $counter = new Counter($this->storage, 'test_counter');
        $this->storage->registerCollector($counter);

        $this->storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 5, []));

        // Will reset the counter to 0, even though 2 was specified:
        $this->storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_SET, 2, []));
        $sampleCollections = $this->storage->collect();

        $samples = $sampleCollections[$counter->getIdentifier()]->getSamples();
        self::assertCount(1, $samples);
        self::assertSame($samples[0]->getValue(), 0);
    }

    /**
     * @test
     */
    public function multipleCountersDontInfluenceEachOther(): void
    {
        $counterA = new Counter($this->storage, 'test_counter_a');
        $counterB = new Counter($this->storage, 'test_counter_b');

        $this->storage->registerCollector($counterA);
        $this->storage->registerCollector($counterB);

        $this->storage->updateCounter($counterA, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 5, []));
        $this->storage->updateCounter($counterB, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 3, []));
        $this->storage->updateCounter($counterA, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 1.5, []));
        $this->storage->updateCounter($counterB, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 2.75, []));
        $this->storage->updateCounter($counterA, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 2, []));
        $sampleCollections = $this->storage->collect();

        self::assertCount(2, $sampleCollections);

        $samplesA = $sampleCollections[$counterA->getIdentifier()]->getSamples();

        self::assertCount(1, $samplesA);
        self::assertSame($samplesA[0]->getValue(), 8.5);

        $samplesB = $sampleCollections[$counterB->getIdentifier()]->getSamples();
        self::assertCount(1, $samplesB);
        self::assertSame($samplesB[0]->getValue(), 5.75);
    }

    /**
     * @test
     */
    public function updateGaugeIncreasesGaugeByGivenValue(): void
    {
        $gauge = new Gauge($this->storage, 'test_gauge');
        $this->storage->registerCollector($gauge);

        $this->storage->updateGauge($gauge, new GaugeUpdate(StorageInterface::OPERATION_INCREASE, 6, []));
        $this->storage->updateGauge($gauge, new GaugeUpdate(StorageInterface::OPERATION_INCREASE, 4, []));
        $this->storage->updateGauge($gauge, new GaugeUpdate(StorageInterface::OPERATION_INCREASE, 2.5, []));
        $sampleCollections = $this->storage->collect();

        self::assertCount(1, $sampleCollections);

        $samples = $sampleCollections[$gauge->getIdentifier()]->getSamples();
        self::assertCount(1, $samples);
        self::assertSame($samples[0]->getValue(), 12.5);
    }
}
