<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\Histogram;
use Flownative\Prometheus\Exception\InvalidConfigurationException;
use Flownative\Prometheus\Storage\HistogramUpdate;
use Flownative\Prometheus\Storage\InMemoryStorage;
use Neos\Flow\Tests\UnitTestCase;

class HistogramTest extends UnitTestCase
{
    /**
     * @test
     * @throws InvalidConfigurationException
     */
    public function gettersReturnHistogramProperties(): void
    {
        $name = 'flownative_prometheus_test_duration_seconds';
        $help = 'A histogram for testing';
        $labels = ['method'];

        $histogram = new Histogram(new InMemoryStorage(), $name, $help, $labels);
        self::assertSame($name, $histogram->getName());
        self::assertSame($help, $histogram->getHelp());
        self::assertSame($labels, $histogram->getLabels());
        self::assertSame(Histogram::TYPE, $histogram->getType());
    }

    /**
     * @test
     * @throws InvalidConfigurationException
     */
    public function histogramUsesDefaultBucketsIfNoneAreGiven(): void
    {
        $histogram = new Histogram(new InMemoryStorage(), 'test_histogram');
        self::assertSame(Histogram::DEFAULT_BUCKETS, $histogram->getBuckets());
    }

    /**
     * @test
     * @throws InvalidConfigurationException
     */
    public function histogramAcceptsCustomBuckets(): void
    {
        $histogram = new Histogram(new InMemoryStorage(), 'test_histogram', '', [], [0.1, 0.5, 1, 5]);
        self::assertSame([0.1, 0.5, 1, 5], $histogram->getBuckets());
    }

    /**
     * @test
     * @throws InvalidConfigurationException
     */
    public function histogramStripsExplicitTrailingInfBucket(): void
    {
        $histogram = new Histogram(new InMemoryStorage(), 'test_histogram', '', [], [1, 2, 5, INF]);
        self::assertSame([1, 2, 5], $histogram->getBuckets());

        $anotherHistogram = new Histogram(new InMemoryStorage(), 'another_histogram', '', [], [1, 2, 5, '+Inf']);
        self::assertSame([1, 2, 5], $anotherHistogram->getBuckets());
    }

    /**
     * @test
     */
    public function histogramRejectsNonAscendingBuckets(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new Histogram(new InMemoryStorage(), 'test_histogram', '', [], [1, 5, 2]);
    }

    /**
     * @test
     */
    public function histogramRejectsEmptyBuckets(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new Histogram(new InMemoryStorage(), 'test_histogram', '', [], [INF]);
    }

    /**
     * @test
     */
    public function histogramRejectsReservedLeLabel(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new Histogram(new InMemoryStorage(), 'test_histogram', '', ['le']);
    }

    /**
     * @test
     * @throws InvalidConfigurationException
     */
    public function observeRejectsReservedLeLabelAtRuntime(): void
    {
        $histogram = new Histogram(new InMemoryStorage(), 'test_histogram');
        $this->expectException(\InvalidArgumentException::class);
        $histogram->observe(1, ['le' => '5']);
    }

    /**
     * @test
     * @throws InvalidConfigurationException
     */
    public function observeDelegatesToStorage(): void
    {
        $storage = new InMemoryStorage();
        $histogram = new Histogram($storage, 'test_histogram', '', [], [1, 2, 5]);
        $histogram->observe(1.5);
        $histogram->observe(3);

        $sampleCollections = $storage->collect();
        $samples = $sampleCollections[$histogram->getIdentifier()]->getSamples();

        $countSample = null;
        $sumSample = null;
        foreach ($samples as $sample) {
            if ($sample->getName() === 'test_histogram_count') {
                $countSample = $sample;
            }
            if ($sample->getName() === 'test_histogram_sum') {
                $sumSample = $sample;
            }
        }

        self::assertNotNull($countSample);
        self::assertSame(2, $countSample->getValue());
        self::assertNotNull($sumSample);
        self::assertEquals(4.5, $sumSample->getValue());
    }

    /**
     * @test
     */
    public function histogramUpdateRejectsNonNumericValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HistogramUpdate('not-a-number', []);
    }

    /**
     * @test
     */
    public function histogramUpdateAcceptsNegativeValues(): void
    {
        $update = new HistogramUpdate(-2.5, []);
        self::assertSame(-2.5, $update->getValue());
    }
}
