<?php

declare(strict_types=1);

namespace Folk\Symfony\Jobs;

/**
 * Push jobs to Folk's jobs plugin via folk_call().
 */
final class FolkQueue
{
    /**
     * @param array<string, mixed> $payload
     */
    public function push(string $queue, string $jobClass, array $payload = [], int $delay = 0): void
    {
        folk_call('jobs.push', \json_encode([
            'queue' => $queue,
            'payload' => \json_encode([
                'job' => $jobClass,
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR),
            'delay' => $delay,
        ], JSON_THROW_ON_ERROR));
    }
}
