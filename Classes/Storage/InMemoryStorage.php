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
use Flownative\Prometheus\Collector\Histogram;
use Flownative\Prometheus\Sample;
use Flownative\Prometheus\SampleCollection;

/*
 * Warning:
 * 
 * InMemoryStorage for testing purposes.
 * You will want to use the RedisStorage instead, so you don't loose all metrics values between requests.
 */
class InMemoryStorage extends AbstractStorage
{
    /**
     * @var array
     */
    private array $countersData = [];

    /**
     * @var array
     */
    private array $gaugesData = [];

    /**
     * @var array
     */
    private array $histogramsData = [];

    /**
     * @return SampleCollection[]
     * @throws \Exception
     */
    public function collect(): array
    {
        return array_merge(
            $this->prepareCollections(array_merge($this->countersData, $this->gaugesData)),
            $this->prepareHistogramCollections()
        );
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        $this->countersData = [];
        $this->gaugesData = [];
        $this->histogramsData = [];
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
            case Histogram::TYPE:
                $this->histogramsData[$collector->getIdentifier()] = [
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
     * @param Histogram $histogram
     * @param HistogramUpdate $update
     * @return void
     * @throws \Exception
     */
    public function updateHistogram(Histogram $histogram, HistogramUpdate $update): void
    {
        $identifier = $histogram->getIdentifier();
        if (!isset($this->histogramsData[$identifier])) {
            throw new \InvalidArgumentException(sprintf('failed updating unknown histogram %s (%s)', $histogram->getName(), $identifier), 1783060245);
        }

        $encodedLabels = $this->encodeLabels($update->getLabels());
        if (!isset($this->histogramsData[$identifier]['values'][$encodedLabels])) {
            $this->histogramsData[$identifier]['values'][$encodedLabels] = [
                'buckets' => [],
                'sum' => 0,
                'count' => 0
            ];
        }

        $bucketLabel = $this->determineBucketLabel($update->getValue(), $histogram);
        $currentBucketCount = $this->histogramsData[$identifier]['values'][$encodedLabels]['buckets'][$bucketLabel] ?? 0;
        $this->histogramsData[$identifier]['values'][$encodedLabels]['buckets'][$bucketLabel] = $currentBucketCount + 1;
        $this->histogramsData[$identifier]['values'][$encodedLabels]['sum'] += $update->getValue();
        $this->histogramsData[$identifier]['values'][$encodedLabels]['count']++;
    }

    /**
     * @return SampleCollection[]
     * @throws \Exception
     */
    private function prepareHistogramCollections(): array
    {
        $collections = [];
        foreach ($this->histogramsData as $collectorIdentifier => $collectorData) {
            $collector = $collectorData['collector'];
            assert($collector instanceof Histogram);

            $samples = $this->buildHistogramSamples($collector, $collectorData['values']);
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
