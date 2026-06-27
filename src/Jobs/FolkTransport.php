<?php

declare(strict_types=1);

namespace Folk\Symfony\Jobs;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Symfony Messenger transport backed by Folk's jobs plugin.
 *
 * `send()` encodes the envelope with the Messenger serializer and pushes the
 * opaque {body, headers} blob to folk-plugin-jobs via folk_call(). Folk drives
 * consumption itself (the worker calls jobs.process, routed back into the bus
 * by {@see FolkMessengerJobHandler}), so the pull-side methods (get/ack/reject)
 * are inert — there is no `messenger:consume` loop in Folk mode.
 */
final class FolkTransport implements TransportInterface
{
    public function __construct(
        private readonly string $queue,
        private readonly SerializerInterface $serializer,
    ) {}

    public function send(Envelope $envelope): Envelope
    {
        $delayStamp = $envelope->last(DelayStamp::class);
        $delaySeconds = $delayStamp instanceof DelayStamp
            ? (int) \ceil($delayStamp->getDelay() / 1000)
            : 0;

        $encoded = $this->serializer->encode($envelope);

        \folk_call('jobs.push', \json_encode([
            'queue' => $this->queue,
            'payload' => \json_encode([
                'body' => $encoded['body'],
                'headers' => $encoded['headers'] ?? [],
            ], JSON_THROW_ON_ERROR),
            'delay' => $delaySeconds,
        ], JSON_THROW_ON_ERROR));

        return $envelope;
    }

    /**
     * @return iterable<Envelope>
     */
    public function get(): iterable
    {
        // Folk drives consumption (worker → jobs.process); nothing to pull.
        return [];
    }

    public function ack(Envelope $envelope): void
    {
        // No-op: acknowledgement is implicit once jobs.process returns.
    }

    public function reject(Envelope $envelope): void
    {
        // No-op: retry/DLQ is handled by Folk's jobs machinery.
    }
}
