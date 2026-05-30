<?php

declare(strict_types=1);

namespace Folk\Symfony\Handler;

use Folk\Sdk\Http\HttpModeHandler;
use Folk\Sdk\Http\HttpRequest as FolkRequest;
use Folk\Sdk\Http\HttpResponse as FolkResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SymfonyHttpHandler implements HttpModeHandler
{
    public function __construct(
        private readonly HttpKernelInterface $kernel,
    ) {}

    public function handle(FolkRequest $folkRequest): FolkResponse
    {
        $request = Request::create(
            uri: $folkRequest->uri,
            method: $folkRequest->method,
            server: $this->buildServerBag($folkRequest->headers),
            content: $folkRequest->body,
        );
        foreach ($folkRequest->headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $response = $this->kernel->handle($request);

        if ($this->kernel instanceof \Symfony\Component\HttpKernel\TerminableInterface) {
            $this->kernel->terminate($request, $response);
        }

        return new FolkResponse(
            status: $response->getStatusCode(),
            headers: $this->extractHeaders($response),
            body: $response->getContent() ?: '',
        );
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function buildServerBag(array $headers): array
    {
        $server = ['SCRIPT_NAME' => 'folk-worker'];
        foreach ($headers as $name => $value) {
            $key = 'HTTP_' . \strtoupper(\str_replace('-', '_', $name));
            $server[$key] = $value;
        }

        return $server;
    }

    /**
     * @return array<string, string>
     */
    private function extractHeaders(\Symfony\Component\HttpFoundation\Response $response): array
    {
        $headers = [];
        foreach ($response->headers->all() as $name => $values) {
            $headers[$name] = \implode(', ', $values);
        }

        return $headers;
    }
}
