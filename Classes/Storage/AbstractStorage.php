<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Storage;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\Histogram;
use Flownative\Prometheus\Sample;

abstract class AbstractStorage implements StorageInterface
{
    /**
     * Sorts labels by key and returns them JSON and BASE64 encoded
     *
     * @param array $labels
     * @return string
     */
    protected function encodeLabels(array $labels): string
    {
        ksort($labels);
        $encodedLabels = json_encode($labels);
        if ($encodedLabels === false) {
            throw new \RuntimeException(json_last_error_msg());
        }
        return base64_encode($encodedLabels);
    }

    /**
     * @param string $encodedLabels
     * @return array
     */
    protected function decodeLabels(string $encodedLabels): array
    {
        $decodedLabels = json_decode(base64_decode($encodedLabels), true);
        if ($decodedLabels === NULL) {
            throw new \RuntimeException(json_last_error_msg());
        }
        return $decodedLabels;
    }

    /**
     * @param Sample[] $samples
     * @return void
     */
    protected function sortSamples(array &$samples): void
    {
        usort($samples, static function (Sample $sampleA, Sample $sampleB) {
            $labelNamesA = array_keys($sampleA->getLabels());
            $labelNamesB = array_keys($sampleB->getLabels());
            sort($labelNamesA);
            sort($labelNamesB);
            if ($labelNamesA !== $labelNamesB) {
                return strcmp(implode('', $labelNamesA), implode('', $labelNamesB));
            }
            return strcmp(implode('', array_values($sampleA->getLabels())), implode('', array_values($sampleB->getLabels())));
        });
    }

    /**
     * Formats a bucket's upper bound for use as an "le" label value.
     *
     * The PHP standard string cast is used (0.005 -> "0.005", 1 and 1.0 -> "1"), infinity is rendered as "+Inf".
     *
     * @param int|float $bucket
     * @return string
     */
    protected function formatBucketLabel($bucket): string
    {
        if (is_infinite((float)$bucket)) {
            return '+Inf';
        }
        return (string)$bucket;
    }

    /**
     * Determines the "le" label of the single bucket a given observed value falls into.
     *
     * This is the smallest bucket whose upper bound is greater than or equal to the value; if the value exceeds all
     * declared buckets, the implicit "+Inf" bucket is returned.
     *
     * @param int|float $value
     * @param Histogram $histogram
     * @return string
     */
    protected function determineBucketLabel($value, Histogram $histogram): string
    {
        foreach ($histogram->getBuckets() as $bucket) {
            if ($value <= $bucket) {
                return $this->formatBucketLabel($bucket);
            }
        }
        return '+Inf';
    }

    /**
     * Builds the ordered list of histogram samples for one collector, following the Prometheus histogram semantics.
     *
     * The given values are keyed by encoded label set and each carry a map of stored per-bucket counts (keyed by the
     * "le" label), the sum and the total count of observations. For each label set (sorted by its encoded label
     * string) the following samples are produced, in this exact order:
     *
     *   1. one "{name}_bucket" sample per declared bucket in ascending order, carrying the cumulative count,
     *   2. one "{name}_bucket" sample with le="+Inf" carrying the total count,
     *   3. one "{name}_sum" sample,
     *   4. one "{name}_count" sample.
     *
     * @param Histogram $histogram
     * @param array $valuesByLabelSet [encodedLabels => ['buckets' => [leString => count], 'sum' => int|float, 'count' => int]]
     * @return Sample[]
     */
    protected function buildHistogramSamples(Histogram $histogram, array $valuesByLabelSet): array
    {
        ksort($valuesByLabelSet);

        $samples = [];
        foreach ($valuesByLabelSet as $encodedLabels => $data) {
            $labels = $this->decodeLabels($encodedLabels);
            $storedBuckets = $data['buckets'];

            $cumulativeCount = 0;
            foreach ($histogram->getBuckets() as $bucket) {
                $labelValue = $this->formatBucketLabel($bucket);
                $cumulativeCount += $storedBuckets[$labelValue] ?? 0;
                $samples[] = new Sample($histogram->getName() . '_bucket', $labels + ['le' => $labelValue], $cumulativeCount);
            }
            $samples[] = new Sample($histogram->getName() . '_bucket', $labels + ['le' => '+Inf'], $data['count']);
            $samples[] = new Sample($histogram->getName() . '_sum', $labels, $data['sum']);
            $samples[] = new Sample($histogram->getName() . '_count', $labels, $data['count']);
        }

        return $samples;
    }
}
