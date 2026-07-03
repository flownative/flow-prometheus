<?php
declare(strict_types=1);
namespace Flownative\Prometheus;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\Counter;
use Flownative\Prometheus\Collector\Gauge;
use Flownative\Prometheus\Collector\Histogram;
use Flownative\Prometheus\Exception\InvalidCollectorTypeException;
use Flownative\Prometheus\Exception\InvalidConfigurationException;
use Flownative\Prometheus\Storage\StorageInterface;

class CollectorRegistry
{
    /**
     * @var StorageInterface
     */
    protected StorageInterface $storage;

    /**
     * @var array
     */
    protected array $counters = [];

    /**
     * @var array
     */
    protected array $gauges = [];

    /**
     * @var array
     */
    protected array $histograms = [];

    /**
     * @param StorageInterface $storage
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @param array $collectorConfigurations
     * @throws InvalidCollectorTypeException
     * @throws InvalidConfigurationException
     */
    public function registerMany(array $collectorConfigurations): void
    {
        foreach ($collectorConfigurations as $name => $collectorConfiguration) {
            $options = [];
            if (isset($collectorConfiguration['buckets'])) {
                $options['buckets'] = $collectorConfiguration['buckets'];
            }
            $this->register($name, $collectorConfiguration['type'], $collectorConfiguration['help'] ?? '', $collectorConfiguration['labels'] ?? [], $options);
        }
    }

    /**
     * @param string $name
     * @param string $type
     * @param string $help
     * @param array $labels
     * @param array $options Additional type-specific options, e.g. "buckets" for histograms
     * @throws InvalidCollectorTypeException
     * @throws InvalidConfigurationException
     */
    public function register(string $name, string $type, string $help = '', array $labels = [], array $options = []): void
    {
        switch ($type) {
            case 'counter':
                $counter = new Counter($this->storage, $name, $help, $labels);
                $this->counters[$name] = $counter;
            break;
            case 'gauge':
                $gauge = new Gauge($this->storage, $name, $help, $labels);
                $this->gauges[$name] = $gauge;
            break;
            case 'histogram':
                $histogram = new Histogram($this->storage, $name, $help, $labels, $options['buckets'] ?? []);
                $this->histograms[$name] = $histogram;
            break;
            default:
                throw new InvalidCollectorTypeException(sprintf('failed registering collector: invalid type "%s"', $type), 1573742887);
        }
    }

    /**
     * @param string $name
     */
    public function unregister(string $name): void
    {
        if (isset($this->counters[$name])) {
            unset($this->counters[$name]);
        }
        if (isset($this->gauges[$name])) {
            unset($this->gauges[$name]);
        }
        if (isset($this->histograms[$name])) {
            unset($this->histograms[$name]);
        }
    }

    /**
     * @return bool
     */
    public function hasCollectors(): bool
    {
        return (count($this->counters) + count($this->gauges) + count($this->histograms) > 0);
    }

    /**
     * @return SampleCollection[]
     */
    public function collect(): array
    {
        return $this->storage->collect();
    }

    /**
     * @param string $name
     * @return Counter
     */
    public function getCounter(string $name): Counter
    {
        if (!isset($this->counters[$name])) {
            throw new \RuntimeException(sprintf('unknown counter %s', $name), 1574259838);
        }
        return $this->counters[$name];
    }

    /**
     * @param string $name
     * @return Gauge
     */
    public function getGauge(string $name): Gauge
    {
        if (!isset($this->gauges[$name])) {
            throw new \RuntimeException(sprintf('unknown gauge %s', $name), 1574259870);
        }
        return $this->gauges[$name];
    }

    /**
     * @param string $name
     * @return Histogram
     */
    public function getHistogram(string $name): Histogram
    {
        if (!isset($this->histograms[$name])) {
            throw new \RuntimeException(sprintf('unknown histogram %s', $name), 1783060247);
        }
        return $this->histograms[$name];
    }
}
