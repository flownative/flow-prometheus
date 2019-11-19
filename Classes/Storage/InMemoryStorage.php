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

class InMemoryStorage implements StorageInterface
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
     */
    public function collect(): array
    {
        return $this->prepareCollections($this->countersData);
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
     * @param array $collectorsData
     * @return SampleCollection[]
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
#            $this->sortSamples($data['samples']);
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

    /**
     * Sorts labels by key and returns them JSON and BASE64 encoded
     *
     * @param array $labels
     * @return string
     */
    private function encodeLabels(array $labels): string
    {
        ksort($labels);
        return base64_encode(json_encode($labels, JSON_THROW_ON_ERROR, 512));
    }

    /**
     * @param string $encodedLabels
     * @return array
     */
    private function decodeLabels(string $encodedLabels): array
    {
        return json_decode(base64_decode($encodedLabels), true, 512, JSON_THROW_ON_ERROR);
    }
}
