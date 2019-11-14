<?php
namespace Flownative\Prometheus\Tests\Unit;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\CollectorRegistry;
use Neos\Flow\Tests\UnitTestCase;

class CollectorRegistryTest extends UnitTestCase
{
    /**
     * @test
     */
    public function counterReturnsCounterCollector(): void
    {
        $collectorRegistry = new CollectorRegistry();
        $collectorRegistry->counter();
    }
}
