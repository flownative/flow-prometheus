<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Sample;
use Neos\Flow\Tests\UnitTestCase;

class SampleTest extends UnitTestCase
{
    /**
     * @test
     */
    public function gettersReturnProvidedValues(): void
    {
        $sample = new Sample('some_metric', ['flownative', 'test'], 4.2);

        self::assertSame('some_metric', $sample->getName());
        self::assertSame(['flownative', 'test'], $sample->getLabels());
        self::assertSame(4.2, $sample->getValue());
    }
}
