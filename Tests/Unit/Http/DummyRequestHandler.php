<?php
declare(strict_types=1);
namespace Flownative\Prometheus\Tests\Unit\Http;

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DummyRequestHandler implements RequestHandlerInterface
{
    /**
     * @var bool
     */
    protected $handleCalled = false;

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->handleCalled = true;
        return new Response(200, ['X-Dummy-Request-Handler' => 'handled']);
    }

    /**
     * @return bool
     */
    public function isHandleCalled(): bool
    {
        return $this->handleCalled;
    }
}
