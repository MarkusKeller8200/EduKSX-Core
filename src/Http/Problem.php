<?php

namespace EduKSX\Core\Http;

use EduKSX\Core\Eloquent\StaleModel;
use EduKSX\Core\Http\Middleware\AssignRequestId;
use EduKSX\Core\Module;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Fehler als application/problem+json nach RFC 9457 (API-Standard A6). Ein zentraler Handler für alle Fehler unter
 * den API-Pfaden – auch 401, 403, 404, 429 und Fehler aus Middleware und Framework. Nie Stacktraces, SQL, Pfade oder
 * Tokens; `detail` bei 500 nur mit app.debug.
 *
 * Einbinden im Modul (bootstrap/app.php):
 *     ->withExceptions(fn (Exceptions $e) => Problem::register($e))
 */
final class Problem
{
    /** Gemeinsame Fehlerschlüssel → [Status, englischer Code]. Modul-eigene Codes über ProblemException. */
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        412 => 'precondition_failed',
        413 => 'payload_too_large',
        415 => 'unsupported_media_type',
        422 => 'validation_failed',
        428 => 'precondition_required',
        429 => 'too_many_requests',
        500 => 'internal_error',
        503 => 'service_unavailable',
    ];

    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(fn (Throwable $e, Request $request) => self::handles($request) ? self::fromThrowable($e, $request) : null);
    }

    public static function handles(Request $request): bool
    {
        /** @var list<string> $paths */
        $paths = (array) config('eduksx.api_paths', ['api/*']);

        return $request->is(...$paths);
    }

    public static function fromThrowable(Throwable $e, Request $request): JsonResponse
    {
        $errors = null;
        $headers = [];
        $code = null;
        $moduleCode = false;
        $detail = null;

        if ($e instanceof ProblemException) {
            $status = $e->status;
            $code = $e->problemCode;
            $moduleCode = ! $e->common;
            $detail = $e->getMessage() !== '' ? $e->getMessage() : null;
            $headers = $e->headers;
        } elseif ($e instanceof StaleModel) {
            $status = 409;
            $detail = $e->getMessage();
        } elseif ($e instanceof ValidationException) {
            $status = 422;
            $detail = __('Die Anfrage enthält ungültige Werte.');
            $errors = $e->errors();
        } elseif ($e instanceof AuthenticationException) {
            $status = 401;
        } elseif ($e instanceof AuthorizationException) {
            $status = 403;
        } elseif ($e instanceof ModelNotFoundException) {
            $status = 404;
        } elseif ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $headers = $e->getHeaders();
            $detail = $status < 500 && $e->getMessage() !== '' ? $e->getMessage() : null;
        } else {
            $status = 500;
            $detail = config('app.debug') ? $e->getMessage() : null;
        }

        return self::response($request, $status, $code, $detail, $errors, $headers, $moduleCode);
    }

    /**
     * @param  array<string, list<string>>|null  $errors
     * @param  array<string, string>  $headers
     */
    public static function response(
        Request $request,
        int $status,
        ?string $code = null,
        ?string $detail = null,
        ?array $errors = null,
        array $headers = [],
        bool $moduleCode = false,
    ): JsonResponse {
        $code ??= self::CODES[$status] ?? ($status >= 500 ? 'internal_error' : 'bad_request');
        $base = rtrim((string) config('eduksx.problem_base_url'), '/');
        $type = $moduleCode ? $base.'/'.Module::key().'/'.$code : $base.'/'.$code;

        $body = array_filter([
            'type' => $type,
            'title' => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status,
            'detail' => $detail,
            'code' => $code,
            'instance' => $request->getPathInfo(),
            'request_id' => AssignRequestId::forRequest($request),
            'errors' => $errors,
        ], fn ($v) => $v !== null);

        return new JsonResponse($body, $status, [
            'Content-Type' => 'application/problem+json',
            AssignRequestId::HEADER => $body['request_id'],
        ] + $headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
