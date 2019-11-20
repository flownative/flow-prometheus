<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Storage;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\Counter;
use Flownative\Prometheus\Collector\Gauge;
use Flownative\Prometheus\SampleCollection;

interface StorageInterface
{
    public const OPERATION_INCREASE = 'i';
    public const OPERATION_SET = 's';
    public const OPERATION_DECREASE = 'd';

    /**
     * @return SampleCollection[]
     */
    public function collect(): array;

    /**
     * @return void
     */
    public function flush(): void;

    /**
     * @return string
     */
    public function getKeyPrefix(): string;

    /**
     * @param Counter $counter
     * @param CounterUpdate $update
     * @return void
     */
    public function updateCounter(Counter $counter, CounterUpdate $update): void;

    /**
     * @param Gauge $gauge
     * @param GaugeUpdate $update
     * @return void
     */
    public function updateGauge(Gauge $gauge, GaugeUpdate $update): void;
}
