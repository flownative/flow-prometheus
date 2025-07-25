<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit\Storage;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use Flownative\Prometheus\Storage\RedisStorage;


class RedisStorageTest extends AbstractStorageTestBase
{

    /**
     * @return void
     * @throws
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->storage = new RedisStorage([
            'hostname' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: '6379'),
            'username' => getenv('REDIS_USERNAME') ?: 'prometheus',
            'password' => getenv('REDIS_PASSWORD') ?: '',
            'keyPrefix' => getenv('REDIS_PREFIX') ?: 'my-app',
            'hashKeyPrefix' => true
        ]);

        $this->storage->flush();
    }
}
