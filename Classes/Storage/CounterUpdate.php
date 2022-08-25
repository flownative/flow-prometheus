<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Storage;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

class CounterUpdate
{
    /**
     * One of the StorageInterface::OPERATION_* constants
     *
     * @var string
     */
    private string $operation;

    /**
     * A positive number
     *
     * @var int|float
     */
    private $value;

    /**
     * @var array
     */
    private array $labels;

    /**
     * @param string $operation
     * @param float|int $value
     * @param array $labels
     */
    public function __construct(string $operation, $value, array $labels)
    {
        if (!in_array($operation, [StorageInterface::OPERATION_INCREASE, StorageInterface::OPERATION_SET], true)) {
            throw new \InvalidArgumentException(sprintf('counter update: invalid operation type "%s"', $operation), 1573814341);
        }
        if (!is_numeric($value) || $value < 0) {
            throw new \InvalidArgumentException('invalid value for counter update, must be a positive number', 1573814397);
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
