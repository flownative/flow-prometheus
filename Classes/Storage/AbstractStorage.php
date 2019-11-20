<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Storage;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

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
        return base64_encode(json_encode($labels, JSON_THROW_ON_ERROR, 512));
    }

    /**
     * @param string $encodedLabels
     * @return array
     */
    protected function decodeLabels(string $encodedLabels): array
    {
        return json_decode(base64_decode($encodedLabels), true, 512, JSON_THROW_ON_ERROR);
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
}
