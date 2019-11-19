<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Storage\InMemoryStorage;

class InMemoryStorageTest extends AbstractStorageTest
{

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->storage = new InMemoryStorage();
    }
}
