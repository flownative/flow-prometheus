<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Collector;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Exception\InvalidConfigurationException;
use Flownative\Prometheus\Storage\HistogramUpdate;
use Flownative\Prometheus\Storage\StorageInterface;

class Histogram extends AbstractCollector
{
    public const TYPE = 'histogram';

    /**
     * The default buckets are tailored to broadly measure the response time (in seconds) of a network service.
     *
     * @var array
     */
    public const DEFAULT_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];

    /**
     * The upper inclusive bounds of the histogram's buckets (without the implicit +Inf bucket), in ascending order
     *
     * @var array
     */
    protected array $buckets;

    /**
     * @param StorageInterface $storage
     * @param string $name
     * @param string $help
     * @param array $labels
     * @param array $buckets An empty array means the default buckets are used. A trailing +Inf bucket is removed.
     * @throws InvalidConfigurationException
     */
    public function __construct(StorageInterface $storage, string $name, string $help = '', array $labels = [], array $buckets = [])
    {
        if (in_array('le', $labels, true)) {
            throw new InvalidConfigurationException(sprintf('failed creating histogram "%s": the label name "le" is reserved for histograms', $name), 1783060240);
        }

        $this->buckets = $this->validateAndNormalizeBuckets($buckets === [] ? self::DEFAULT_BUCKETS : $buckets, $name);

        parent::__construct($storage, $name, $help, $labels);
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * @return array
     */
    public function getBuckets(): array
    {
        return $this->buckets;
    }

    /**
     * Observe the given value, incrementing the matching bucket, the sum and the count
     *
     * @param int|float $value
     * @param array $labels
     */
    public function observe($value, array $labels = []): void
    {
        if (array_key_exists('le', $labels)) {
            throw new \InvalidArgumentException(sprintf('failed observing histogram "%s": the label name "le" is reserved for histograms', $this->name), 1783060241);
        }
        $this->storage->updateHistogram($this, new HistogramUpdate($value, $labels));
    }

    /**
     * Validates the given buckets, strips a trailing +Inf bucket and returns the normalized bucket list
     *
     * @param array $buckets
     * @param string $name
     * @return array
     * @throws InvalidConfigurationException
     */
    private function validateAndNormalizeBuckets(array $buckets, string $name): array
    {
        $buckets = array_values($buckets);

        // Strip an explicitly given trailing +Inf bucket, since it is added implicitly during collection:
        $lastBucket = end($buckets);
        if ($lastBucket !== false && (is_infinite((float)$lastBucket) || $lastBucket === '+Inf')) {
            array_pop($buckets);
        }
        reset($buckets);

        if ($buckets === []) {
            throw new InvalidConfigurationException(sprintf('failed creating histogram "%s": at least one bucket must be defined', $name), 1783060242);
        }

        $previousBucket = null;
        foreach ($buckets as $bucket) {
            if (!is_numeric($bucket)) {
                throw new InvalidConfigurationException(sprintf('failed creating histogram "%s": bucket values must be numeric, got "%s"', $name, is_scalar($bucket) ? (string)$bucket : gettype($bucket)), 1783060243);
            }
            if ($previousBucket !== null && $bucket <= $previousBucket) {
                throw new InvalidConfigurationException(sprintf('failed creating histogram "%s": bucket values must be in strictly ascending order', $name), 1783060244);
            }
            $previousBucket = $bucket;
        }

        return $buckets;
    }
}
