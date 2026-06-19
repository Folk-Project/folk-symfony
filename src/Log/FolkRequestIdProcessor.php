<?php

declare(strict_types=1);

namespace Folk\Symfony\Log;

use Folk\Sdk\Folk;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that stamps the current Folk request id onto every log record.
 *
 * Reads \Folk\Sdk\Folk::requestId() at log time (not at request start), so it is
 * stateless and cannot leak a stale id between requests on a recycled worker.
 * The id is added under extra.request_id and omitted entirely when 0 (no request
 * in flight, or the Folk extension is not loaded).
 *
 * folk/symfony does not hard-depend on Monolog: this class is only instantiated
 * when a Monolog logger is present (see FolkBootstrap), so referencing Monolog
 * types here is safe.
 */
final class FolkRequestIdProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $id = Folk::requestId();
        if ($id === 0) {
            return $record;
        }

        return $record->with(extra: [...$record->extra, 'request_id' => $id]);
    }
}
