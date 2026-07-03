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
            'port' => getenv('REDIS_PORT') ?: '6379',
            'username' => getenv('REDIS_USERNAME') ?: '',
            'password' => getenv('REDIS_PASSWORD') ?: '',
        ]);

        $this->storage->flush();
    }

    /**
     * @test
     */
    public function keyPrefixIsUsedVerbatimByDefault(): void
    {
        $storage = new RedisStorage(['keyPrefix' => 'my-app']);
        self::assertSame('my-app', $storage->getKeyPrefix());
    }

    /**
     * @test
     */
    public function keyPrefixIsHashedIfConfigured(): void
    {
        $storage = new RedisStorage(['keyPrefix' => 'my-app', 'hashKeyPrefix' => true]);
        self::assertSame(md5('my-app'), $storage->getKeyPrefix());
    }

    /**
     * @test
     */
    public function keyPrefixHashingDoesNotDependOnOptionsOrder(): void
    {
        $storage = new RedisStorage(['hashKeyPrefix' => true, 'keyPrefix' => 'my-app']);
        self::assertSame(md5('my-app'), $storage->getKeyPrefix());
    }

    /**
     * @test
     */
    public function defaultKeyPrefixCanBeHashed(): void
    {
        $storage = new RedisStorage(['hashKeyPrefix' => true]);
        self::assertSame(md5('flownative_prometheus'), $storage->getKeyPrefix());
    }

    /**
     * @test
     */
    public function usernameIsAcceptedAsOption(): void
    {
        $storage = new RedisStorage(['username' => 'prometheus']);
        self::assertInstanceOf(RedisStorage::class, $storage);
    }
}
