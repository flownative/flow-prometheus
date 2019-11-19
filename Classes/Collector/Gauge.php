<?php
namespace Flownative\Prometheus\Collector;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

class Gauge extends AbstractCollector
{
    public const TYPE = 'gauge';

    /**
     * @return string
     */
    public function getType(): string
    {
        return self::TYPE;
    }
}
