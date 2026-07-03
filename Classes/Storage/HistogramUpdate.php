<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Storage;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

class HistogramUpdate
{
    /**
     * The observed value
     *
     * @var int|float
     */
    private $value;

    /**
     * @var array
     */
    private array $labels;

    /**
     * @param float|int $value
     * @param array $labels
     */
    public function __construct($value, array $labels)
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('invalid value for histogram update, must be a number', 1783060239);
        }
        $this->value = $value;
        $this->labels = $labels;
    }

    /**
     * @return float|int
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return array
     */
    public function getLabels(): array
    {
        return $this->labels;
    }
}
