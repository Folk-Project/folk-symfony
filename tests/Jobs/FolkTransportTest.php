<?php

declare(strict_types=1);

namespace {
    // Stub the native folk_call() exposed by the Folk extension so the transport
    // can be exercised without the extension loaded. Captures the last call.
    if (!\function_exists('folk_call')) {
        function folk_call(string $method, string $payload): string
        {
            $GLOBALS['__folk_test_calls'][] = ['method' => $method, 'payload' => $payload];

            return '{"status":"ok"}';
        }
    }
}

namespace Folk\Symfony\Tests\Jobs {

    use Folk\Symfony\Jobs\FolkTransport;
    use Folk\Symfony\Jobs\FolkTransportFactory;
    use PHPUnit\Framework\TestCase;
    use Symfony\Component\Messenger\Envelope;
    use Symfony\Component\Messenger\Stamp\DelayStamp;
    use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

    final class SampleMessage
    {
        public function __construct(public readonly string $text = 'hello') {}
    }

    final class FolkTransportTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['__folk_test_calls'] = [];
        }

        public function testSendPushesEncodedEnvelopeToFolk(): void
        {
            $transport = new FolkTransport('redis.emails', new PhpSerializer());

            $transport->send(new Envelope(new SampleMessage('hi'), [new DelayStamp(2500)]));

            self::assertCount(1, $GLOBALS['__folk_test_calls']);
            $call = $GLOBALS['__folk_test_calls'][0];
            self::assertSame('jobs.push', $call['method']);

            $outer = \json_decode($call['payload'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('redis.emails', $outer['queue']);
            // 2500 ms rounds up to 3 s.
            self::assertSame(3, $outer['delay']);

            $inner = \json_decode($outer['payload'], true, 512, JSON_THROW_ON_ERROR);
            self::assertArrayHasKey('body', $inner);
            self::assertArrayHasKey('headers', $inner);
            self::assertNotSame('', $inner['body']);
        }

        public function testSendWithoutDelayStampSendsZeroDelay(): void
        {
            $transport = new FolkTransport('default', new PhpSerializer());

            $transport->send(new Envelope(new SampleMessage()));

            $outer = \json_decode($GLOBALS['__folk_test_calls'][0]['payload'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(0, $outer['delay']);
            self::assertSame('default', $outer['queue']);
        }

        public function testGetAckRejectAreInert(): void
        {
            $transport = new FolkTransport('default', new PhpSerializer());

            self::assertSame([], \iterator_to_array((function () use ($transport) {
                yield from $transport->get();
            })()));

            $envelope = new Envelope(new SampleMessage());
            $transport->ack($envelope);
            $transport->reject($envelope);

            self::assertSame([], $GLOBALS['__folk_test_calls']);
        }

        public function testFactorySupportsFolkDsnOnly(): void
        {
            $factory = new FolkTransportFactory();

            self::assertTrue($factory->supports('folk://redis.emails', []));
            self::assertFalse($factory->supports('doctrine://default', []));
        }

        public function testFactoryParsesQueueFromDsn(): void
        {
            $factory = new FolkTransportFactory();
            $serializer = new PhpSerializer();

            $transport = $factory->createTransport('folk://redis.emails', [], $serializer);
            $transport->send(new Envelope(new SampleMessage()));

            $outer = \json_decode($GLOBALS['__folk_test_calls'][0]['payload'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('redis.emails', $outer['queue']);
        }

        public function testFactoryDefaultsEmptyDsnToDefaultQueue(): void
        {
            $factory = new FolkTransportFactory();

            $transport = $factory->createTransport('folk://', [], new PhpSerializer());
            $transport->send(new Envelope(new SampleMessage()));

            $outer = \json_decode($GLOBALS['__folk_test_calls'][0]['payload'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('default', $outer['queue']);
        }
    }
}
