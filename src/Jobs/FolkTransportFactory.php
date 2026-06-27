<?php

declare(strict_types=1);

namespace Folk\Symfony\Jobs;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Creates {@see FolkTransport} from a `folk://` DSN.
 *
 * DSN form: `folk://[<connection>.]<queue>` — e.g. `folk://redis.emails` or
 * `folk://default`. Everything after the scheme is passed verbatim as the Folk
 * queue address; prefix routing (`<connection>.<queue>`) is resolved Rust-side
 * by folk-plugin-jobs.
 *
 * Register as a Messenger transport factory (the app is not a bundle, so wire
 * it explicitly):
 *
 *   Folk\Symfony\Jobs\FolkTransportFactory:
 *       tags: ['messenger.transport_factory']
 *
 * @implements TransportFactoryInterface<FolkTransport>
 */
final class FolkTransportFactory implements TransportFactoryInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function createTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $queue = \trim(\substr($dsn, \strlen('folk://')), '/');
        if ($queue === '') {
            $queue = 'default';
        }

        return new FolkTransport($queue, $serializer);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function supports(#[\SensitiveParameter] string $dsn, array $options): bool
    {
        return \str_starts_with($dsn, 'folk://');
    }
}
