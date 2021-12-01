<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Collector;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Storage\CounterUpdate;
use Flownative\Prometheus\Storage\StorageInterface;

class Counter extends AbstractCollector
{
    public const TYPE = 'counter';

    /**
     * @return string
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * @param int|float $amount
     * @param array $labels
     */
    public function inc($amount = 1, array $labels = []): void
    {
        $this->storage->updateCounter($this, new CounterUpdate(StorageInterface::OPERATION_INCREASE, $amount, $labels));
    }
}
