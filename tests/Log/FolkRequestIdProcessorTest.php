<?php

declare(strict_types=1);

namespace {
    // Stub the native folk_request_id() exposed by the Folk extension so the
    // processor can be exercised without the extension loaded. Driven by a global.
    if (!\function_exists('folk_request_id')) {
        function folk_request_id(): int
        {
            return (int) ($GLOBALS['__folk_test_request_id'] ?? 0);
        }
    }
}

namespace Folk\Symfony\Tests\Log {

    use Folk\Symfony\Log\FolkRequestIdProcessor;
    use Monolog\Level;
    use Monolog\LogRecord;
    use PHPUnit\Framework\TestCase;

    final class FolkRequestIdProcessorTest extends TestCase
    {
        private function record(): LogRecord
        {
            return new LogRecord(
                datetime: new \DateTimeImmutable('@0'),
                channel: 'test',
                level: Level::Info,
                message: 'hello',
            );
        }

        public function testAddsRequestIdWhenPresent(): void
        {
            $GLOBALS['__folk_test_request_id'] = 42;

            $record = (new FolkRequestIdProcessor())($this->record());

            self::assertInstanceOf(LogRecord::class, $record);
            self::assertArrayHasKey('request_id', $record->extra);
            self::assertSame(42, $record->extra['request_id']);
        }

        public function testOmitsRequestIdWhenZero(): void
        {
            $GLOBALS['__folk_test_request_id'] = 0;

            $record = (new FolkRequestIdProcessor())($this->record());

            self::assertInstanceOf(LogRecord::class, $record);
            self::assertArrayNotHasKey('request_id', $record->extra);
        }

        public function testPreservesExistingExtra(): void
        {
            $GLOBALS['__folk_test_request_id'] = 7;

            $base = $this->record()->with(extra: ['foo' => 'bar']);
            $record = (new FolkRequestIdProcessor())($base);

            self::assertInstanceOf(LogRecord::class, $record);
            self::assertSame('bar', $record->extra['foo']);
            self::assertSame(7, $record->extra['request_id']);
        }
    }
}
