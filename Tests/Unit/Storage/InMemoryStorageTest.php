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

class InMemoryStorageTest extends UnitTestCase
{
    /**
     * @test
     */
    public function updateCounterIncreasesCounterByGivenValue(): void
    {
        $storage = new InMemoryStorage();
        $counter = new Counter($storage, 'test_counter');
        $storage->registerCollector($counter);

        $storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 5, []));
        $storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 2, []));
        $sampleCollections = $storage->collect();

        $samples = $sampleCollections[$counter->getIdentifier()]->getSamples();
        self::assertCount(1, $samples);
        self::assertSame($samples[0]->getValue(), 7);
    }

    /**
     * @test
     */
    public function updateCounterResetsCounterIfSetOperationIsSpecified(): void
    {
        $storage = new InMemoryStorage();
        $counter = new Counter($storage, 'test_counter');
        $storage->registerCollector($counter);

        $storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_INCREASE, 5, []));

        // Will reset the counter to 0, even though 2 was specified:
        $storage->updateCounter($counter, new CounterUpdate(StorageInterface::OPERATION_SET, 2, []));
        $sampleCollections = $storage->collect();

        $samples = $sampleCollections[$counter->getIdentifier()]->getSamples();
        self::assertCount(1, $samples);
        self::assertSame($samples[0]->getValue(), 0);
    }
}
