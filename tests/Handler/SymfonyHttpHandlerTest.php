<?php

declare(strict_types=1);

namespace Folk\Symfony\Tests\Handler;

use Folk\Sdk\Http\HttpRequest;
use Folk\Symfony\Handler\SymfonyHttpHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SymfonyHttpHandlerTest extends TestCase
{
    public function testHandlesGetRequest(): void
    {
        $kernel = $this->mockKernel(new Response('ok', 200));
        $handler = new SymfonyHttpHandler($kernel);

        $response = $handler->handle(new HttpRequest('GET', '/', [], ''));

        self::assertSame(200, $response->status);
        self::assertSame('ok', $response->body);
    }

    public function testHandlesPostRequestWithBody(): void
    {
        $kernel = $this->mockKernel(new Response('created', 201));
        $handler = new SymfonyHttpHandler($kernel);

        $response = $handler->handle(new HttpRequest('POST', '/items', ['content-type' => 'application/json'], '{"x":1}'));

        self::assertSame(201, $response->status);
        self::assertSame('created', $response->body);
    }

    public function testReturns404Response(): void
    {
        $kernel = $this->mockKernel(new Response('', 404));
        $handler = new SymfonyHttpHandler($kernel);

        $response = $handler->handle(new HttpRequest('GET', '/missing', [], ''));

        self::assertSame(404, $response->status);
    }

    public function testForwardsResponseHeaders(): void
    {
        $symfonyResponse = new Response('ok', 200, ['X-Folk' => 'test', 'Content-Type' => 'text/plain']);
        $kernel = $this->mockKernel($symfonyResponse);
        $handler = new SymfonyHttpHandler($kernel);

        $response = $handler->handle(new HttpRequest('GET', '/', [], ''));

        self::assertArrayHasKey('x-folk', $response->headers);
        self::assertSame('test', $response->headers['x-folk']);
    }

    public function testForwardsRequestHeadersToKernel(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $kernel->expects(self::once())
            ->method('handle')
            ->with(self::callback(fn($req) => $req->headers->get('x-request-id') === 'abc123'))
            ->willReturn(new Response('ok'));

        $handler = new SymfonyHttpHandler($kernel);
        $handler->handle(new HttpRequest('GET', '/', ['x-request-id' => 'abc123'], ''));
    }

    private function mockKernel(Response $response): HttpKernelInterface
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn($response);
        return $kernel;
    }
}
