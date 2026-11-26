<?php

namespace EduKSX\Core\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * «tap» für einen Protokollkanal (KONZEPT-PROTOKOLLIERUNG §6, §7): JSON je Zeile mit festen Feldern und
 * Maskierung von Personendaten. In config/logging.php des Moduls:
 *
 *     'stderr' => [..., 'tap' => [\EduKSX\Core\Logging\UseEduksxFormat::class]],
 *
 * Felder: time (UTC, ISO 8601), level, message, module, request_id, trace_id, context. Der Betrieb sammelt die
 * Ausgabe von stdout/stderr bzw. per OTLP (Plattform 7.3a). Einträge unter der Stufe des Kanals kommen nur durch,
 * solange für die Organisation der Anfrage befristetes Debug läuft (OrganizationDebug, §5).
 */
final class UseEduksxFormat
{
    public function __invoke(Logger $logger): void
    {
        /** @var list<string> $extraKeys */
        $extraKeys = (array) config('eduksx.logging.mask_keys', []);
        $mask = new MaskPersonalData($extraKeys);
        $module = (string) (config('eduksx.module') ?? '');

        $monolog = $logger->getLogger();
        if (! $monolog instanceof \Monolog\Logger) {
            return;
        }
        $handlers = [];
        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof ProcessableHandlerInterface) {
                // Reihenfolge: zuerst Felder ergänzen, dann maskieren (pushProcessor legt vorne an)
                $handler->pushProcessor($mask);
                $handler->pushProcessor(static fn (LogRecord $record): LogRecord => self::shape($record, $module));
            }
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, true, true));
            }
            // Stufe des Kanals in die Schranke verlegen, damit befristetes Debug je Organisation durchkommt
            if ($handler instanceof AbstractHandler && ! $handler instanceof DebugGate) {
                $level = $handler->getLevel();
                $handler->setLevel(Level::Debug);
                $handler = new DebugGate($handler, $level);
            }
            $handlers[] = $handler;
        }
        $monolog->setHandlers($handlers);
    }

    /** Einheitlicher Aufbau; request_id und trace_id aus dem Laravel-Context (extra) bzw. dem Kontext. */
    public static function shape(LogRecord $record, string $module): LogRecord
    {
        $context = $record->context;
        $extra = $record->extra;
        $requestId = $context['request_id'] ?? $extra['request_id'] ?? null;
        $traceId = $context['trace_id'] ?? $extra['trace_id'] ?? null;
        unset($context['request_id'], $context['trace_id'], $extra['request_id'], $extra['trace_id']);

        return $record->with(
            context: $context,
            extra: array_filter([
                'time' => $record->datetime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
                'module' => $module !== '' ? $module : null,
                'request_id' => $requestId,
                'trace_id' => $traceId,
            ] + $extra, fn (mixed $v): bool => $v !== null),
        );
    }
}
