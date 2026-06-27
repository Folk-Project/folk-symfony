<?php

declare(strict_types=1);

namespace Folk\Symfony\Jobs;

use Folk\Sdk\Jobs\JobsModeHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Consume side of the Folk ↔ Messenger bridge.
 *
 * folk-plugin-jobs delivers {queue, payload} to jobs.process; payload carries
 * the serialized envelope ({body, headers}) produced by {@see FolkTransport}.
 * We decode it with the same Messenger serializer and re-dispatch through the
 * bus with a ReceivedStamp so HandleMessageMiddleware runs the
 * `#[AsMessageHandler]` (and SendMessageMiddleware does not route it back to
 * the transport, avoiding an infinite loop).
 *
 * Wire as a public service so {@see \Folk\Symfony\FolkBootstrap} can fetch it:
 *
 *   Folk\Symfony\Jobs\FolkMessengerJobHandler:
 *       public: true
 *       arguments:
 *           $bus: '@messenger.bus.default'
 *           $serializer: '@messenger.transport.native_php_serializer'
 */
final class FolkMessengerJobHandler implements JobsModeHandler
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly SerializerInterface $serializer,
        private readonly string $transportName = 'folk',
    ) {}

    public function process(mixed $payload): mixed
    {
        $body = $this->unwrapBody($payload);

        /** @var array{body?: string, headers?: array<string, string>} $decoded */
        $decoded = \json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $envelope = $this->serializer->decode([
            'body' => $decoded['body'] ?? '',
            'headers' => $decoded['headers'] ?? [],
        ]);

        $this->bus->dispatch($envelope->with(
            new ReceivedStamp($this->transportName),
            new ConsumedByWorkerStamp(),
        ));

        return ['status' => 'ok'];
    }

    /**
     * Extract the opaque serialized-envelope string from the jobs.process
     * payload, tolerating both the {queue, payload} wrapper and a bare string.
     */
    private function unwrapBody(mixed $payload): string
    {
        if (\is_array($payload)) {
            return isset($payload['payload']) ? (string) $payload['payload'] : '';
        }

        return (string) $payload;
    }
}
