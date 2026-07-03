<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit\Storage;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\Counter;
use Flownative\Prometheus\Collector\Gauge;
use Flownative\Prometheus\Collector\Histogram;
use Flownative\Prometheus\Sample;
use Flownative\Prometheus\Storage\CounterUpdate;
use Flownative\Prometheus\Storage\GaugeUpdate;
use Flownative\Prometheus\Storage\HistogramUpdate;
use Flownative\Prometheus\Storage\InMemoryStorage;
use Flownative\Prometheus\Storage\StorageInterface;
use Neos\Flow\Tests\UnitTestCase;

abstract class AbstractStorageTestBase extends UnitTestCase
{
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

    /**
     * @test
     * @throws \Exception
     */
    public function updateHistogramPlacesObservationInMatchingBucket(): void
    {
        $histogram = new Histogram($this->storage, 'test_histogram', '', [], [1, 2, 5]);
        $this->storage->registerCollector($histogram);

        $this->storage->updateHistogram($histogram, new HistogramUpdate(1.5, []));

        $samples = $this->storage->collect()[$histogram->getIdentifier()]->getSamples();

        self::assertEquals([
            new Sample('test_histogram_bucket', ['le' => '1'], 0),
            new Sample('test_histogram_bucket', ['le' => '2'], 1),
            new Sample('test_histogram_bucket', ['le' => '5'], 1),
            new Sample('test_histogram_bucket', ['le' => '+Inf'], 1),
            new Sample('test_histogram_sum', [], 1.5),
            new Sample('test_histogram_count', [], 1),
        ], $samples);
    }

    /**
     * @test
     * @throws \Exception
     */
    public function updateHistogramAccumulatesBucketsCumulativelyAndTracksSumAndCount(): void
    {
        $histogram = new Histogram($this->storage, 'test_histogram', '', [], [1, 2, 5]);
        $this->storage->registerCollector($histogram);

        $this->storage->updateHistogram($histogram, new HistogramUpdate(0.5, []));
        $this->storage->updateHistogram($histogram, new HistogramUpdate(1.5, []));
        $this->storage->updateHistogram($histogram, new HistogramUpdate(3, []));
        $this->storage->updateHistogram($histogram, new HistogramUpdate(7.5, []));

        $samples = $this->storage->collect()[$histogram->getIdentifier()]->getSamples();

        self::assertEquals([
            new Sample('test_histogram_bucket', ['le' => '1'], 1),
            new Sample('test_histogram_bucket', ['le' => '2'], 2),
            new Sample('test_histogram_bucket', ['le' => '5'], 3),
            new Sample('test_histogram_bucket', ['le' => '+Inf'], 4),
            new Sample('test_histogram_sum', [], 12.5),
            new Sample('test_histogram_count', [], 4),
        ], $samples);
    }

    /**
     * @test
     * @throws \Exception
     */
    public function updateHistogramCountsValuesAboveLargestBucketOnlyInInf(): void
    {
        $histogram = new Histogram($this->storage, 'test_histogram', '', [], [1, 2, 5]);
        $this->storage->registerCollector($histogram);

        $this->storage->updateHistogram($histogram, new HistogramUpdate(42, []));

        $samples = $this->storage->collect()[$histogram->getIdentifier()]->getSamples();

        self::assertEquals([
            new Sample('test_histogram_bucket', ['le' => '1'], 0),
            new Sample('test_histogram_bucket', ['le' => '2'], 0),
            new Sample('test_histogram_bucket', ['le' => '5'], 0),
            new Sample('test_histogram_bucket', ['le' => '+Inf'], 1),
            new Sample('test_histogram_sum', [], 42),
            new Sample('test_histogram_count', [], 1),
        ], $samples);
    }

    /**
     * @test
     * @throws \Exception
     */
    public function updateHistogramSeparatesLabelSets(): void
    {
        $histogram = new Histogram($this->storage, 'test_histogram', '', ['method'], [1, 2, 5]);
        $this->storage->registerCollector($histogram);

        $this->storage->updateHistogram($histogram, new HistogramUpdate(0.5, ['method' => 'get']));
        $this->storage->updateHistogram($histogram, new HistogramUpdate(3, ['method' => 'get']));
        $this->storage->updateHistogram($histogram, new HistogramUpdate(1.5, ['method' => 'post']));

        $samples = $this->storage->collect()[$histogram->getIdentifier()]->getSamples();

        // Two label sets, each producing 3 buckets + Inf + sum + count = 6 samples:
        self::assertCount(12, $samples);

        $countByMethod = [];
        $sumByMethod = [];
        foreach ($samples as $sample) {
            if ($sample->getName() === 'test_histogram_count') {
                $countByMethod[$sample->getLabels()['method']] = $sample->getValue();
            }
            if ($sample->getName() === 'test_histogram_sum') {
                $sumByMethod[$sample->getLabels()['method']] = $sample->getValue();
            }
        }

        self::assertSame(2, $countByMethod['get']);
        self::assertSame(1, $countByMethod['post']);
        self::assertEquals(3.5, $sumByMethod['get']);
        self::assertEquals(1.5, $sumByMethod['post']);
    }

    /**
     * @test
     * @throws \Exception
     */
    public function flushRemovesHistograms(): void
    {
        $histogram = new Histogram($this->storage, 'test_histogram', '', [], [1, 2, 5]);
        $this->storage->registerCollector($histogram);
        $this->storage->updateHistogram($histogram, new HistogramUpdate(1.5, []));

        self::assertCount(1, $this->storage->collect());
        $this->storage->flush();
        self::assertCount(0, $this->storage->collect());
    }
}
