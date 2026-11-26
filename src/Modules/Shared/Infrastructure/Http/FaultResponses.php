<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Http;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Shared\Domain\BusinessRuleViolation;
use Modules\Shared\Domain\Exceptions\Conflict;
use Modules\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Modules\Shared\Domain\Exceptions\NotFound;

/**
 * Maps expected errors to JSON responses. Runs after the exception has left the
 * command bus, so the transaction is already rolled back. Everything else is a 500.
 */
final class FaultResponses
{
    public static function register(Exceptions $exceptions): void
    {
        // JSON for /api/* even without an Accept header, otherwise validation errors redirect
        $exceptions->shouldRenderJsonWhen(static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson());
        $exceptions->dontReport([BusinessRuleViolation::class, NotFound::class, Conflict::class]);

        $exceptions->render(static fn (BusinessRuleViolation $violation): JsonResponse => self::error(
            status: 422,
            code: $violation->rule === null
                ? 'INVALID_VALUE'
                : Str::upper(Str::snake(class_basename($violation->rule))),
            message: $violation->getMessage(),
        ));
        $exceptions->render(static fn (NotFound $fault): JsonResponse => self::error(
            status: 404,
            code: $fault->errorCode,
            message: $fault->getMessage(),
        ));
        $exceptions->render(static fn (Conflict $fault): JsonResponse => self::error(
            status: 409,
            code: $fault->errorCode,
            message: $fault->getMessage(),
        ));
        // reported on purpose, the provider error should end up in the logs
        $exceptions->render(static fn (ExternalServiceUnavailable $fault): JsonResponse => self::error(
            status: 503,
            code: $fault->errorCode,
            message: $fault->getMessage(),
        ));
    }

    private static function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
