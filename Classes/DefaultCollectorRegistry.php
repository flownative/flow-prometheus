<?php
declare(strict_types=1);
namespace Flownative\Prometheus;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
final class DefaultCollectorRegistry extends CollectorRegistry
{

    /**
     * @Flow\InjectConfiguration(path="metrics")
     * @var array
     */
    protected $settings;

    /**
     *
     */
    public function initializeObject(): void
    {
        try {
            $this->registerMany($this->settings);
        } catch (Exception\InvalidCollectorTypeException $e) {
        }
    }
}
