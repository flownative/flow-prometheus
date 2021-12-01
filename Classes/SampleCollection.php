<?php
declare(strict_types=1);
namespace Flownative\Prometheus;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

class SampleCollection
{
    /**
     * @var string
     */
    private string $name;

    /**
     * @var string
     */
    private string $type;

    /**
     * @var string
     */
    private string $help;

    /**
     * @var array
     */
    private array $labels;

    /**
     * @var Sample[]
     */
    private array $samples;

    /**
     * @param string $name
     * @param string $type
     * @param string $help
     * @param array $labels
     * @param Sample[] $samples
     */
    public function __construct(string $name, string $type, string $help, array $labels, array $samples)
    {
        $this->name = $name;
        $this->type = $type;
        $this->help = $help;
        $this->labels = $labels;
        $this->samples = $samples;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return $this->help;
    }

    /**
     * @return array
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * @return Sample[]
     */
    public function getSamples(): array
    {
        return $this->samples;
    }
}
