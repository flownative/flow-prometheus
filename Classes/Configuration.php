<?php
declare(strict_types=1);
namespace Flownative\Prometheus;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Collector\Counter;
use Flownative\Prometheus\Collector\Gauge;
use Flownative\Prometheus\Exception\InvalidConfigurationException;

class Configuration
{
    /**
     * @var string
     */
    protected string $type;

    /**
     * @var string
     */
    protected string $help = '';

    /**
     * @var array
     */
    protected array $labels = [];

    /**
     * @param string $type
     * @param string $help
     * @param array $labels
     * @throws InvalidConfigurationException
     */
    public function __construct(string $type, string $help, array $labels)
    {
        $this->setType($type);
        $this->setHelp($help);
        $this->setLabels($labels);
    }

    /**
     * @param string $type
     * @throws InvalidConfigurationException
     */
    private function setType(string $type): void
    {
        if (!in_array($type, [Counter::TYPE, Gauge::TYPE], true)) {
            throw new InvalidConfigurationException(sprintf('failed creating configuration, invalid metric type "%s"', $type), 1573807113);
        }
        $this->type = $type;
    }

    /**
     * @param string $help
     */
    private function setHelp(string $help): void
    {
        $this->help = $help;
    }

    /**
     * @param array $labels
     * @throws InvalidConfigurationException
     */
    private function setLabels(array $labels): void
    {
        foreach ($labels as $label) {
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $label) !== 1) {
                throw new InvalidConfigurationException(sprintf('failed creating configuration, invalid character in label "%s"', $label), 1573807237);
            }
            if (strpos($label, '__') === 0) {
                throw new InvalidConfigurationException(sprintf('failed creating configuration, only one leading underscore allowed for label "%s"', $label), 1573807441);
            }
        }
        $this->labels = $labels;
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
}
