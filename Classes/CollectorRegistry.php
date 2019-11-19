<?php
declare(strict_types=1);
namespace Flownative\Prometheus;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Exception\InvalidCollectorTypeException;
use Flownative\Prometheus\Collector\AbstractCollector;
use Flownative\Prometheus\Collector\Counter;
use Flownative\Prometheus\Collector\Gauge;
use Flownative\Prometheus\Storage\StorageInterface;

class CollectorRegistry
{
    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var array
     */
    private $collectors = [];

    /**
     * @param StorageInterface $storage
     * @param array $collectorConfigurations
     * @throws InvalidCollectorTypeException
     */
    public function __construct(StorageInterface $storage, array $collectorConfigurations = [])
    {
        $this->storage = $storage;

        if ($collectorConfigurations !== []) {
            $this->registerMany($collectorConfigurations);
        }
    }

    /**
     * @param array $collectorConfigurations
     * @throws InvalidCollectorTypeException
     */
    public function registerMany(array $collectorConfigurations): void
    {
        foreach ($collectorConfigurations as $name => $collectorConfiguration) {
            $this->register($name, $collectorConfiguration['type'], $collectorConfiguration['help'] ?? '', $collectorConfiguration['labels'] ?? []);
        }
    }

    /**
     * @param string $name
     * @param string $type
     * @param string|null $help
     * @param array $labels
     * @throws InvalidCollectorTypeException
     */
    public function register(string $name, string $type, string $help = '', array $labels = []): void
    {
        switch ($type) {
            case 'counter':
                $collector = new Counter($this->storage, $name, $help, $labels);
            break;
            case 'gauge':
                $collector = new Gauge($this->storage, $name, $help, $labels);
            break;
            default:
                throw new InvalidCollectorTypeException(sprintf('failed registering collector: invalid type "%s"', $type), 1573742887);
        }

        $this->collectors[$name] = $collector;
    }

    /**
     * @param string $name
     */
    public function unregister(string $name): void
    {
        unset($this->collectors[$name]);
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
    public function getCounter(string $name): ?Counter
    {
        $collector = $this->collectors[$name] ?? null;
        if ($collector instanceof AbstractCollector && !$collector instanceof Counter) {
            throw new \InvalidArgumentException(sprintf('failed returning collector "%s" through getCounter() because it is a %s', $name, get_class($collector)), 1573803469);
        }
        return $collector;
    }

    /**
     * @param string $name
     * @return Gauge|null
     */
    public function getGauge(string $name): ?Gauge
    {
        return $this->collectors[$name] ?? null;
    }
}
