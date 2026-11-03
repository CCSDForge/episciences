<?php

declare(strict_types=1);

namespace unit\library\Episciences\Security;

use Episciences\Security\AccessLog;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Psr\Log\NullLogger;

/**
 * Captures the security log (AccessLog) of each test: the records are kept in memory, and the default
 * logger is never opened.
 */
trait SecurityLogCaptureTrait
{
    private TestHandler $logHandler;

    #[Before]
    protected function captureSecurityLog(): void
    {
        $this->logHandler = new TestHandler();
        AccessLog::setLogger(new Logger(AccessLog::CHANNEL, [$this->logHandler]));
        AccessLog::reset();
    }

    #[After]
    protected function releaseSecurityLog(): void
    {
        AccessLog::setLogger(new NullLogger());
        AccessLog::reset();
    }

    /**
     * @return list<LogRecord>
     */
    private function securityLogRecords(): array
    {
        return array_values($this->logHandler->getRecords());
    }

    /**
     * The event of each record, followed by the requested fields: "level" for the name of the level, any other
     * name for the context value of that key ("reason" when no field is given). A missing reason is an empty
     * string, as AccessLog leaves it out when empty; other missing values are null.
     *
     * @return list<list<mixed>>
     */
    private function loggedEvents(string ...$fields): array
    {
        $fields = $fields ?: ['reason'];

        return array_map(
            static function (LogRecord $record) use ($fields): array {
                $event = [$record->message];
                foreach ($fields as $field) {
                    $event[] = match ($field) {
                        'level' => $record->level->getName(),
                        'reason' => $record->context['reason'] ?? '',
                        default => $record->context[$field] ?? null,
                    };
                }

                return $event;
            },
            $this->securityLogRecords()
        );
    }
}
