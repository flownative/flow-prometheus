<?php
namespace Flownative\Prometheus\Collector;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Storage\GaugeUpdate;
use Flownative\Prometheus\Storage\StorageInterface;

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

    /**
     * Increase the gauge's value
     *
     * @param int|float $amount
     * @param array $labels
     */
    public function inc($amount = 1, array $labels = []): void
    {
        $this->storage->updateGauge($this, new GaugeUpdate(StorageInterface::OPERATION_INCREASE, $amount, $labels));
    }

    /**
     * Decrease the gauge's value
     *
     * @param int|float $amount
     * @param array $labels
     */
    public function dec($amount = 1, array $labels = []): void
    {
        $this->storage->updateGauge($this, new GaugeUpdate(StorageInterface::OPERATION_DECREASE, $amount, $labels));
    }

    /**
     * Set the gauge's value
     *
     * @param int|float $value
     * @param array $labels
     */
    public function set($value, array $labels = []): void
    {
        $this->storage->updateGauge($this, new GaugeUpdate(StorageInterface::OPERATION_SET, $value, $labels));
    }

    /**
     * @param array $labels
     * @return void
     */
    public function setToCurrentTime(array $labels = []): void
    {
        $this->storage->updateGauge($this, new GaugeUpdate(StorageInterface::OPERATION_SET, time(), $labels));
    }
}
