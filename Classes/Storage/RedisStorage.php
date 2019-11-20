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
use Flownative\Prometheus\Exception\ConnectionException;
use Flownative\Prometheus\Exception\InvalidConfigurationException;
use Flownative\Prometheus\Sample;
use Flownative\Prometheus\SampleCollection;
use Redis;

/**
 * A storage which stores metrics in Redis using the phpredis PHP extension.
 *
 * @see http://redis.io/
 * @see https://github.com/nicolasff/phpredis
 */
class RedisStorage extends AbstractStorage
{
    private const KEY_SUFFIX = '_KEYS';

    /**
     * @var Counter[]
     */
    protected $counters;

    /**
     * @var Gauge[]
     */
    protected $gauges;

    /**
     * @var Redis
     */
    protected $redis;

    /**
     * @var string
     */
    protected $hostname = '127.0.0.1';

    /**
     * @var integer
     */
    protected $port = 6379;

    /**
     * @var integer
     */
    protected $database = 0;

    /**
     * @var string
     */
    protected $password = '';

    /**
     * @var string
     */
    protected $keyPrefix = 'flownative_prometheus';

    /**
     * @param array $options
     * @throws ConnectionException
     * @throws InvalidConfigurationException
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $key => $value) {
            if (in_array($key, ['hostname', 'password', 'keyPrefix'])) {
                $this->$key = (string)$value;
            } elseif (in_array($key, ['database', 'port'])) {
                $this->$key = (int)$value;
            } else {
                throw new InvalidConfigurationException(sprintf('invalid configuration option "%s" for Prometheus RedisStorage', $key), 1574176047);
            }
        }
        $this->redis = $this->getRedisClient();
    }

    /**
     * @return SampleCollection[]
     */
    public function collect(): array
    {
        $sampleCollections = $this->collectCounters();
        return $sampleCollections;
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        $this->redis->flushDB();
    }

    /**
     * @return string
     */
    public function getKeyPrefix(): string
    {
        return $this->keyPrefix;
    }

    /**
     * @param AbstractCollector $collector
     */
    public function registerCollector(AbstractCollector $collector): void
    {
        switch ($collector->getType()) {
            case Counter::TYPE:
                $this->counters[$collector->getIdentifier()] = $collector;
            break;
            case Gauge::TYPE:
                $this->gauges[$collector->getIdentifier()] = $collector;
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
        if (!isset($this->counters[$identifier])) {
            throw new \InvalidArgumentException(sprintf('failed updating unknown counter %s (%s)', $counter->getName(), $identifier), 1574079998);
        }

        switch ($update->getOperation()) {
            case StorageInterface::OPERATION_INCREASE:
                $value = (float)$update->getValue();
                $this->redis->hIncrByFloat(
                    $counter->getIdentifier(),
                    $this->encodeLabels($update->getLabels()),
                    $update->getValue()
                );
            break;
            case StorageInterface::OPERATION_SET:
                $this->redis->hSet(
                    $counter->getIdentifier(),
                    $this->encodeLabels($update->getLabels()),
                    $update->getValue()
                );
            break;
        }

        $this->redis->hSet($counter->getIdentifier(), '__name', $counter->getName());
        $this->redis->sAdd($this->keyPrefix . Counter::TYPE . self::KEY_SUFFIX, $counter->getIdentifier());
    }

    /**
     * @return SampleCollection[]
     */
    private function collectCounters(): array
    {
        $keys = $this->redis->sMembers($this->keyPrefix . Counter::TYPE . self::KEY_SUFFIX);
        sort($keys);
        $sampleCollections = [];
        foreach ($keys as $key) {
            $counterRawHash = $this->redis->hGetAll($key);
            $counterName = $counterRawHash['__name'];
            unset($counterRawHash['__name']);

            $samples = [];
            foreach ($counterRawHash as $k => $value) {
                $value = (strpos($value,'.') !== false) ? (float)$value : (int)$value;
                $samples[] = new Sample(
                    $counterName,
                    $this->decodeLabels($k),
                    $value
                );
            }
            $this->sortSamples($samples);

            $sampleCollections[$key] = new SampleCollection(
                $counterName,
                Counter::TYPE,
                $this->counters[$key]->getHelp(),
                $this->counters[$key]->getLabels(),
                $samples
            );
        }
        return $sampleCollections;
    }

    /**
     * @return Redis
     * @throws ConnectionException
     */
    private function getRedisClient(): Redis
    {
        if (strpos($this->hostname, '/') !== false) {
            $this->port = null;
        }
        $redis = new Redis();

        try {
            $connected = false;
            // keep the above! the line below leave the variable undefined if an error occurs.
            $connected = $redis->connect($this->hostname, $this->port);
        } finally {
            if ($connected === false) {
                throw new ConnectionException(sprintf('failed connecting to Redis at %s:%s', $this->hostname, $this->port), 1574175735);
            }
        }

        if (($this->password !== '') && !$redis->auth($this->password)) {
            throw new ConnectionException(sprintf('failed authenticating with Redis at %s:%s', $this->hostname, $this->port), 1574175808);
        }
        $redis->select($this->database);
        return $redis;
    }

    /**
     * @param array $data
     * @return string
     */
    private function toMetricKey(array $data): string
    {
        return implode(':', [$this->keyPrefix, $data['type'], $data['name']]);
    }
}
