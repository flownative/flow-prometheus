<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\Counter;
use Flownative\Prometheus\Storage\CounterUpdate;
use Flownative\Prometheus\Storage\InMemoryStorage;
use Flownative\Prometheus\Storage\StorageInterface;
use Neos\Flow\Tests\UnitTestCase;

abstract class AbstractStorageTest extends UnitTestCase
{
    /**
     * @var StorageInterface
     */
    protected $storage;

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
}
