<?php
namespace Flownative\Prometheus;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

class Renderer
{
    /**
     * Provides the text format version produced by this renderer.
     *
     * @return string
     * @see https://prometheus.io/docs/instrumenting/exposition_formats/
     */
    public function getFormatVersion(): string
    {
        return '0.0.4';
    }

    /**
     * @param SampleCollection[] $sampleCollections
     * @return string
     */
    public function render(array $sampleCollections): string
    {
        usort( $sampleCollections, static function (SampleCollection $a, SampleCollection $b) {
            return strcmp($a->getName(), $b->getName());
        });

        $lines = [];

        foreach ($sampleCollections as $sampleCollection) {
            if ($sampleCollection->getHelp() !== '') {
                $lines[] = '# HELP ' . $sampleCollection->getName() . " {$sampleCollection->getHelp()}";
            }
            $lines[] = '# TYPE ' . $sampleCollection->getName() . " {$sampleCollection->getType()}";
            foreach ($sampleCollection->getSamples() as $sample) {
                $lines[] = $this->renderSample($sampleCollection, $sample);
            }
        }
        return implode("\n", $lines);
    }

    /**
     * @param SampleCollection $sampleCollection
     * @param Sample $sample
     * @return string
     */
    private function renderSample(SampleCollection $sampleCollection, Sample $sample): string
    {
        $labelStatements = [];
        foreach ($sample->getLabels() as $labelName => $labelValue) {
            $labelStatements[] = $labelName . '="' . $this->escapeLabelValue($labelValue) . '"';
        }
        return $sample->getName() . ($labelStatements ? '{' . implode(',', $labelStatements) . '}' : '') . ' ' . $sample->getValue();
    }

    /**
     * @param string $value
     * @return string
     */
    private function escapeLabelValue(string $value): string
    {
        return str_replace(array("\\", "\n", '"'), array("\\\\", "\\n", "\\\""), $value);
    }
}
