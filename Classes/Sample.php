<?php
declare(strict_types=1);
namespace Flownative\Prometheus;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

class Sample
{
    /**
     * @var string
     */
    private string $name;

    /**
     * @var array
     */
    private array $labels;

    /**
     * @var int|float
     */
    private $value;

    /**
     * @param string $name
     * @param array $labels
     * @param float|int $value
     */
    public function __construct(string $name, array $labels, $value)
    {
        $this->name = $name;
        $this->labels = $labels;
        if (!is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException(sprintf('invalid value type for sample: got "%s", "int" or "float" expected', gettype($value)), 1574182081);
        }
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * @return float|int
     */
    public function getValue()
    {
        return $this->value;
    }
}
