<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\DefaultCollectorRegistry;
use Flownative\Prometheus\Exception\InvalidCollectorTypeException;
use Flownative\Prometheus\Storage\InMemoryStorage;
use Neos\Flow\Tests\UnitTestCase;

class DefaultCollectorRegistryTest extends UnitTestCase
{
    /**
     * @test
     * @throws InvalidCollectorTypeException
     */
    public function collectorsDefinedInSettingsAreRegisteredAutomatically(): void
    {
        $metricsSettings = [
            'flownative_test_hits_total' => [
                'type' => 'counter',
                'help' => 'A counter for testing',
                'labelNames' => ['code']
            ]
        ];

        $registry = new DefaultCollectorRegistry(new InMemoryStorage());
        $this->inject($registry, 'settings', $metricsSettings);
        $registry->initializeObject();

        $counter = $registry->getCounter('flownative_test_hits_total');
        self::assertSame('flownative_test_hits_total', $counter->getName());
    }
}
