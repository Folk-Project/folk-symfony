<?php

declare(strict_types=1);

namespace Folk\Symfony\Handler;

use Folk\Sdk\Folk;
use Folk\Sdk\Http\HttpModeHandler;
use Folk\Sdk\Http\HttpRequest as FolkRequest;
use Folk\Sdk\Http\HttpResponse as FolkResponse;
use Folk\Sdk\Http\StreamedBody;
use Folk\Sdk\Http\StreamLimitExceededException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

final class SymfonyHttpHandler implements HttpModeHandler
{
    public function __construct(
        private readonly HttpKernelInterface $kernel,
        /** Default streamed-body size limit in bytes (0 = unlimited). */
        private readonly int $maxRequestBytes = 0,
        /** @var array<string, int> Per-path streamed-body limits. */
        private readonly array $pathLimits = [],
    ) {}

    public function handle(FolkRequest $folkRequest): FolkResponse
    {
        $streamed = null;
        try {
            if ($folkRequest->multipart) {
                $streamed = new StreamedBody($this->limitFor($folkRequest->uri));
                $streamed->drainMultipart();
                $request = $this->buildRequest($folkRequest, $streamed);
            } elseif ($folkRequest->bodyStream) {
                $streamed = new StreamedBody($this->limitFor($folkRequest->uri));
                $request = $this->buildRequest($folkRequest, content: $streamed->readRaw());
            } else {
                $request = $this->buildRequest($folkRequest, content: $folkRequest->body);
            }

            $response = $this->kernel->handle($request);

            $folkResponse = $this->toFolkResponse($response);

            if ($this->kernel instanceof TerminableInterface) {
                $this->kernel->terminate($request, $response);
            }

            return $folkResponse;
        } catch (StreamLimitExceededException $e) {
            return new FolkResponse(
                status: 413,
                headers: ['Content-Type' => 'application/json'],
                body: json_encode(['error' => $e->getMessage()]) ?: '{}',
            );
        } finally {
            $streamed?->cleanup();
        }
    }

    private function buildRequest(
        FolkRequest $folkRequest,
        ?StreamedBody $streamed = null,
        ?string $content = null,
    ): Request {
        $parameters = $streamed !== null
            ? $streamed->post
            : $this->parseFormBody($folkRequest, $content);
        $files = [];
        if ($streamed !== null) {
            foreach ($streamed->files as $file) {
                if ($file->field === null) {
                    continue;
                }
                $files[$file->field] = new UploadedFile(
                    $file->tmpPath,
                    $file->originalName ?? 'file',
                    $file->contentType,
                    null,
                    true, // test mode: file was not uploaded via SAPI
                );
            }
        }

        $request = Request::create(
            uri: $folkRequest->uri,
            method: $folkRequest->method,
            parameters: $parameters,
            files: $files,
            server: $this->buildServerBag($folkRequest->headers),
            content: $streamed !== null ? null : ($content ?? ''),
        );
        foreach ($folkRequest->headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    private function toFolkResponse(Response $response): FolkResponse
    {
        if ($response instanceof StreamedResponse) {
            Folk::writeHead($response->getStatusCode(), $this->extractHeaders($response));
            ob_start(static function (string $chunk): string {
                if ($chunk !== '') {
                    Folk::write($chunk);
                }
                return '';
            }, 65536);
            $response->sendContent();
            ob_end_flush();
            Folk::end();

            return FolkResponse::alreadyStreamed();
        }

        return new FolkResponse(
            status: $response->getStatusCode(),
            headers: $this->extractHeaders($response),
            body: $response->getContent() ?: '',
        );
    }

    private function limitFor(string $uri): int
    {
        return StreamedBody::resolveLimit($uri, $this->maxRequestBytes, $this->pathLimits);
    }

    /**
     * Parse a urlencoded body into POST parameters (Symfony's Request::create
     * does not). Returns [] for non-form bodies.
     *
     * @return array<array-key, mixed>
     */
    private function parseFormBody(FolkRequest $folkRequest, ?string $content): array
    {
        if ($content === null || $content === '') {
            return [];
        }
        if (!\in_array(\strtoupper($folkRequest->method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return [];
        }
        $contentType = '';
        foreach ($folkRequest->headers as $name => $value) {
            if (\strcasecmp($name, 'content-type') === 0) {
                $contentType = $value;
                break;
            }
        }
        if (!\str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
            return [];
        }
        \parse_str($content, $parsed);

        return $parsed;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function buildServerBag(array $headers): array
    {
        $server = ['SCRIPT_NAME' => 'folk-worker'];
        foreach ($headers as $name => $value) {
            $normalized = \strtoupper(\str_replace('-', '_', $name));
            if ($normalized === 'CONTENT_TYPE' || $normalized === 'CONTENT_LENGTH') {
                $server[$normalized] = $value;
            } else {
                $server['HTTP_' . $normalized] = $value;
            }
        }

        return $server;
    }

    /**
     * @return array<string, string>
     */
    private function extractHeaders(Response $response): array
    {
        $headers = [];
        foreach ($response->headers->all() as $name => $values) {
            $headers[$name] = \implode(', ', $values);
        }

        return $headers;
    }
}
