<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Storage;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

class GaugeUpdate
{
    /**
     * One of the StorageInterface::OPERATION_* constants
     *
     * @var string
     */
    private $operation;

    /**
     * A positive number
     *
     * @var int|float
     */
    private $value;

    /**
     * @var array
     */
    private $labels;

    /**
     * @param string $operation
     * @param float|int $value
     * @param array $labels
     */
    public function __construct(string $operation, $value, array $labels)
    {
        if (!in_array($operation, [StorageInterface::OPERATION_INCREASE, StorageInterface::OPERATION_SET, StorageInterface::OPERATION_DECREASE], true)) {
            throw new \InvalidArgumentException(sprintf('gauge update: invalid operation type "%s"', $operation), 1574257299);
        }
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('invalid value for gauge update, must be a number', 1574257303);
        }
        $this->operation = $operation;
        $this->value = $value;
        $this->labels = $labels;
    }

    /**
     * @return string
     */
    public function getOperation(): string
    {
        return $this->operation;
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
