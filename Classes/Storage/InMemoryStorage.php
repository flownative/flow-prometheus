<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Storage;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\AbstractCollector;
use Flownative\Prometheus\Collector\Counter;
use Flownative\Prometheus\Collector\Gauge;
use Flownative\Prometheus\Sample;
use Flownative\Prometheus\SampleCollection;

class InMemoryStorage extends AbstractStorage
{
    /**
     * @var array
     */
    private $countersData = [];

    /**
     * @var array
     */
    private $gaugesData = [];

    /**
     * @return SampleCollection[]
     * @throws \Exception
     */
    public function collect(): array
    {
        return $this->prepareCollections(array_merge($this->countersData, $this->gaugesData));
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        $this->countersData = [];
        $this->gaugesData = [];
    }

    public function getKeyPrefix(): string
    {
        return '';
    }

    /**
     * @param AbstractCollector $collector
     */
    public function registerCollector(AbstractCollector $collector): void
    {
        switch ($collector->getType()) {
            case Counter::TYPE:
                $this->countersData[$collector->getIdentifier()] = [
                    'collector' => $collector,
                    'values' => []
                ];
            break;
            case Gauge::TYPE:
                $this->gaugesData[$collector->getIdentifier()] = [
                    'collector' => $collector,
                    'values' => []
                ];
            break;
        }
    }

    /**
     * @param Counter $counter
     * @param CounterUpdate $update
     * @return void
     * @throws \Exception
     */
    public function updateCounter(Counter $counter, CounterUpdate $update): void
    {
        $identifier = $counter->getIdentifier();
        if (!isset($this->countersData[$identifier])) {
            throw new \InvalidArgumentException(sprintf('failed updating unknown counter %s (%s)', $counter->getName(), $identifier), 1574079998);
        }

        $encodedLabels = $this->encodeLabels($update->getLabels());
        $value = $this->countersData[$identifier]['values'][$encodedLabels] ?? 0;

        if ($update->getOperation() === StorageInterface::OPERATION_INCREASE) {
            $value += $update->getValue();
        } else {
            $value = 0;
        }
        $this->countersData[$identifier]['values'][$encodedLabels] = $value;
    }

    /**
     * @param Gauge $gauge
     * @param GaugeUpdate $update
     * @return void
     * @throws \Exception
     */
    public function updateGauge(Gauge $gauge, GaugeUpdate $update): void
    {
        $identifier = $gauge->getIdentifier();
        if (!isset($this->gaugesData[$identifier])) {
            throw new \InvalidArgumentException(sprintf('failed updating unknown gauge %s (%s)', $gauge->getName(), $identifier), 1574257469);
        }

        $encodedLabels = $this->encodeLabels($update->getLabels());
        $value = $this->gaugesData[$identifier]['values'][$encodedLabels] ?? 0;

        switch ($update->getOperation()) {
            case StorageInterface::OPERATION_INCREASE:
                $value += $update->getValue();
            break;
            case StorageInterface::OPERATION_SET:
                $value = $update->getValue();
            break;
            case StorageInterface::OPERATION_DECREASE:
                $value -= $update->getValue();
            break;
        }
        $this->gaugesData[$identifier]['values'][$encodedLabels] = $value;
    }

    /**
     * @param array $collectorsData
     * @return SampleCollection[]
     * @throws \Exception
     */
    private function prepareCollections(array $collectorsData): array
    {
        $collections = [];
        foreach ($collectorsData as $collectorIdentifier => $collectorData) {
            $samples = [];
            $collector = $collectorData['collector'];
            assert($collector instanceof AbstractCollector);

            foreach ($collectorData['values'] as $encodedLabels => $value) {
                $samples[] = new Sample($collector->getName(), $this->decodeLabels($encodedLabels), $value);
            }
            $this->sortSamples($samples);
            $collections[$collectorIdentifier] = new SampleCollection(
                $collector->getName(),
                $collector->getType(),
                $collector->getHelp(),
                $collector->getLabels(),
                $samples
            );
        }

        return $collections;
    }
}
