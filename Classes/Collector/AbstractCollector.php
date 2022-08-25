<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Collector;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Storage\StorageInterface;

abstract class AbstractCollector
{
    /**
     * @var StorageInterface
     */
    protected StorageInterface $storage;

    /**
     * @var string
     */
    protected string $name;

    /**
     * @var string
     */
    protected string $help = '';

    /**
     * @var array
     */
    protected array $labels = [];

    /**
     * @param StorageInterface $storage
     * @param string $name
     * @param string $help
     * @param array $labels
     */
    public function __construct(StorageInterface $storage, string $name, string $help = '', array $labels = [])
    {
        $this->storage = $storage;
        $this->name = $name;
        $this->help = $help;
        $this->labels = $labels;

        $this->storage->registerCollector($this);
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
     * One of the Configuration::METRIC_TYPE_* constant values
     *
     * @return string
     */
    abstract public function getType(): string;

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return implode(':', [$this->storage->getKeyPrefix(), static::TYPE, $this->name]);
    }
}
