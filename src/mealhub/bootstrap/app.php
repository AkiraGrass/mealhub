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
        // Validation errors -> 9999 with 422
        $exceptions->render(function (ValidationException $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }
            return ApiResponse::validationError($e->errors());
        });

        // Authentication -> 9999 with 401
        $exceptions->render(function (AuthenticationException $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }
            return ApiResponse::error(Status::FAILURE, 'unauthorized');
        });

        // Authorization -> 9999 with 403
        $exceptions->render(function (AuthorizationException $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }
            return ApiResponse::error(Status::FAILURE, 'forbidden');
        });

        // 404 Not Found -> 9999 with 404
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }
            return ApiResponse::error(Status::FAILURE, 'notFound');
        });

        // Generic HTTP exceptions -> 9999 with mapped http (fallback 500)
        $exceptions->render(function (HttpExceptionInterface $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }
            $meta = null;
            if (config('app.debug')) {
                $meta = ['http_status' => $e->getStatusCode()];
            }
            return ApiResponse::error(Status::FAILURE, 'serverError', null, null, $meta);
        });

        // Fallback: any other Throwable -> 9999 with 500
        $exceptions->render(function (Throwable $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }
            $meta = null;
            if (config('app.debug')) {
                $meta = [
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                ];
            }
            return ApiResponse::error(Status::FAILURE, 'serverError', null, null, $meta);
        });
    })->create();
