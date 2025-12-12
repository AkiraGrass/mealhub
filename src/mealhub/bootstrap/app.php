<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Helper\ApiResponse;
use App\Helper\Status;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php'
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.jwt' => App\Http\Middleware\AuthenticateJwt::class,
            'check.api.access' => App\Http\Middleware\CheckApiAccess::class,
            'http.log' => App\Http\Middleware\HttpAccessLog::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $verbose = (bool) config('app.error_verbose');
        // Validation errors -> 9999 with 422 (always JSON)
        $exceptions->render(function (ValidationException $e, $request) {
            return ApiResponse::validationError($e->errors());
        });

        // Authentication -> 9999 with 401 (always JSON)
        $exceptions->render(function (AuthenticationException $e, $request) {
            return ApiResponse::error(Status::FAILURE, 'unauthorized');
        });

        // Authorization -> 9999 with 403 (always JSON)
        $exceptions->render(function (AuthorizationException $e, $request) {
            return ApiResponse::error(Status::FAILURE, 'forbidden');
        });

        // 404 Not Found -> 9999 with 404 (always JSON)
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            return ApiResponse::error(Status::FAILURE, 'notFound');
        });

        // Generic HTTP exceptions -> 9999 with mapped http (fallback 500)
        $exceptions->render(function (HttpExceptionInterface $e, $request) use ($verbose) {
            $statusCode = $e->getStatusCode();
            $meta = $verbose || config('app.debug') ? ['http_status' => $statusCode] : null;

            // Map common HTTP status codes to message keys
            $messageKey = match ($statusCode) {
                400 => 'badRequest',
                401 => 'unauthorized',
                403 => 'forbidden',
                404 => 'notFound',
                405 => 'methodNotAllowed',
                409 => 'conflict',
                422 => 'validationError',
                429 => 'tooManyRequests',
                default => $statusCode >= 500 ? 'serverError' : 'failure',
            };

            return ApiResponse::error(Status::FAILURE, $messageKey, null, null, $meta);
        });

        // Fallback: any other Throwable -> 9999 with 500
        $exceptions->render(function (Throwable $e, $request) use ($verbose) {
            $meta = $verbose || config('app.debug') ? [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
            ] : null;
            return ApiResponse::error(Status::FAILURE, 'serverError', null, null, $meta);
        });
    })->create();
