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
use Flownative\Prometheus\Exception\InvalidConfigurationException;
use Flownative\Prometheus\Sample;
use Flownative\Prometheus\SampleCollection;
use Predis;

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
     * @var Predis\Client
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
     * @var array
     */
    protected $sentinels = [];

    /**
     * @var string
     */
    protected $service = 'mymaster';

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
     * @throws InvalidConfigurationException
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $key => $value) {
            switch ($key) {
                case 'hostname':
                case 'password':
                case 'keyPrefix':
                case 'service':
                    $this->$key = (string)$value;
                break;
                case 'sentinels':
                    if (is_string($value)) {
                        $this->sentinels = explode(',', $value);
                    } elseif(is_array($value)) {
                        $this->sentinels = $value;
                    } else {
                        throw new \InvalidArgumentException(sprintf('setSentinels(): Invalid type %s, string or array expected', gettype($value)), 1575969465);
                    }
                break;
                case 'database':
                case 'port':
                    $this->$key = (int)$value;
                break;
                default:
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
        return $this->collectCountersAndGauges();
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
        $this->redis->sAdd($this->keyPrefix . Counter::TYPE . self::KEY_SUFFIX, [$counter->getIdentifier()]);
    }

    /**
     * @param Gauge $gauge
     * @param GaugeUpdate $update
     * @return void
     */
    public function updateGauge(Gauge $gauge, GaugeUpdate $update): void
    {
        $identifier = $gauge->getIdentifier();
        if (!isset($this->gauges[$identifier])) {
            throw new \InvalidArgumentException(sprintf('failed updating unknown gauge %s (%s)', $gauge->getName(), $identifier), 1574259278);
        }

        switch ($update->getOperation()) {
            case StorageInterface::OPERATION_INCREASE:
                $this->redis->hIncrByFloat(
                    $gauge->getIdentifier(),
                    $this->encodeLabels($update->getLabels()),
                    $update->getValue()
                );
            break;
            case StorageInterface::OPERATION_SET:
                $this->redis->hSet(
                    $gauge->getIdentifier(),
                    $this->encodeLabels($update->getLabels()),
                    $update->getValue()
                );
            break;
        }

        $this->redis->hSet($gauge->getIdentifier(), '__name', $gauge->getName());
        $this->redis->sAdd($this->keyPrefix . Gauge::TYPE . self::KEY_SUFFIX, [$gauge->getIdentifier()]);
    }

    /**
     * @return SampleCollection[]
     */
    private function collectCountersAndGauges(): array
    {
        $sampleCollections = [];
        foreach ([Counter::TYPE, Gauge::TYPE] as $collectorType) {
            $collectorKeys = $this->redis->sMembers($this->keyPrefix . $collectorType . self::KEY_SUFFIX);
            sort($collectorKeys);
            foreach ($collectorKeys as $collectorKey) {
                $collectorRawHash = $this->redis->hGetAll($collectorKey);
                $collectorName = $collectorRawHash['__name'];
                unset($collectorRawHash['__name']);

                $samples = [];
                foreach ($collectorRawHash as $sampleKey => $value) {
                    $value = (strpos($value,'.') !== false) ? (float)$value : (int)$value;
                    $samples[] = new Sample(
                        $collectorName,
                        $this->decodeLabels($sampleKey),
                        $value
                    );
                }
                $this->sortSamples($samples);

                $collector =  $this->counters[$collectorKey] ?? $this->gauges[$collectorKey] ?? null;
                if ($collector) {
                    $sampleCollections[$collectorKey] = new SampleCollection(
                        $collectorName,
                        $collectorType,
                        $collector->getHelp(),
                        $collector->getLabels(),
                        $samples
                    );

                }
            }
        }
        return $sampleCollections;
    }

    /**
     * @return Predis\Client
     */
    private function getRedisClient(): Predis\Client
    {
        $options = [
            'parameters' => [
                'database' => $this->database
            ]
        ];

        if (!empty($this->password)) {
            $options['parameters']['password'] = $this->password;
        }

        if ($this->sentinels !== []) {
            $connectionParameters = $this->sentinels;
            $options['replication'] = 'sentinel';
            $options['service'] = $this->service;
        } else {
            $connectionParameters = 'tcp://' . $this->hostname . ':' . $this->port;
        }
        return new Predis\Client($connectionParameters, $options);
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
