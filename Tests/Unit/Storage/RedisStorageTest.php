<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Storage\RedisStorage;

class RedisStorageTest extends AbstractStorageTest
{

    /**
     * @return void
     * @throws
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->storage = new RedisStorage([
            'hostname' => getenv('REDIS_HOST') ?? '127.0.0.1',
            'port' => getenv('REDIS_PORT') ?? '6379',
            'password' => getenv('REDIS_PASSWORD') ?? '',
        ]);

        $this->storage->flush();
    }
}
