<?php

namespace EduKSX\Core\Logging;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\HandlerWrapper;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Lässt Einträge unter der eingestellten Stufe nur durch, wenn für die Organisation der Anfrage befristetes Debug läuft
 * (OrganizationDebug). Die Stufe des Kanals (z. B. LOG_LEVEL=info) gilt sonst unverändert.
 */
final class DebugGate extends HandlerWrapper
{
    public function __construct(HandlerInterface $handler, private readonly Level $level)
    {
        parent::__construct($handler);
    }

    public function isHandling(LogRecord $record): bool
    {
        return $this->allows($record) && $this->handler->isHandling($record);
    }

    public function handle(LogRecord $record): bool
    {
        if (! $this->allows($record)) {
            return false;
        }

        return $this->handler->handle($record);
    }

    /** @param  array<LogRecord>  $records */
    public function handleBatch(array $records): void
    {
        $this->handler->handleBatch(array_values(array_filter($records, $this->allows(...))));
    }

    public function level(): Level
    {
        return $this->level;
    }

    private function allows(LogRecord $record): bool
    {
        return $this->level->includes($record->level) || OrganizationDebug::active();
    }
}
