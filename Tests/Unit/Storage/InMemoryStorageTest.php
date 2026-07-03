<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit\Storage;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Storage\InMemoryStorage;

class InMemoryStorageTest extends AbstractStorageTestBase
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
