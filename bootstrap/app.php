<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $errorStatus = static function (Throwable $exception): int {
            return match (true) {
                $exception instanceof ValidationException => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
                $exception instanceof AuthenticationException => HttpResponse::HTTP_UNAUTHORIZED,
                $exception instanceof AuthorizationException => HttpResponse::HTTP_FORBIDDEN,
                $exception instanceof ModelNotFoundException => HttpResponse::HTTP_NOT_FOUND,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
            };
        };

        $errorTitle = static function (int $status): string {
            return match ($status) {
                HttpResponse::HTTP_UNAUTHORIZED => __('Unauthorized'),
                HttpResponse::HTTP_FORBIDDEN => __('Forbidden'),
                HttpResponse::HTTP_NOT_FOUND => __('Not Found'),
                HttpResponse::HTTP_METHOD_NOT_ALLOWED => __('Method Not Allowed'),
                HttpResponse::HTTP_TOO_MANY_REQUESTS => __('Too Many Requests'),
                HttpResponse::HTTP_UNPROCESSABLE_ENTITY => __('Validation Error'),
                default => $status >= 500 ? __('Server Error') : __('Request Error'),
            };
        };

        $errorDetail = static function (Throwable $exception, int $status): string {
            if ($exception instanceof HttpExceptionInterface && $status < HttpResponse::HTTP_INTERNAL_SERVER_ERROR && $exception->getMessage() !== '') {
                return $exception->getMessage();
            }

            return match ($status) {
                HttpResponse::HTTP_UNAUTHORIZED => __('Authentication is required.'),
                HttpResponse::HTTP_FORBIDDEN => __('This action is unauthorized.'),
                HttpResponse::HTTP_NOT_FOUND => __('The requested resource was not found.'),
                HttpResponse::HTTP_METHOD_NOT_ALLOWED => __('Method Not Allowed'),
                HttpResponse::HTTP_TOO_MANY_REQUESTS => __('Too Many Requests'),
                HttpResponse::HTTP_UNPROCESSABLE_ENTITY => __('The given data was invalid.'),
                default => __('An unexpected error occurred.'),
            };
        };

        $errorObjects = static function (Throwable $exception) use ($errorStatus, $errorTitle, $errorDetail): array {
            if ($exception instanceof ValidationException) {
                return collect($exception->errors())
                    ->flatMap(fn (array $messages, string $field): array => array_map(
                        fn (string $message): array => [
                            'status' => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
                            'title' => __('Validation Error'),
                            'detail' => $message,
                            'source' => ['pointer' => "/data/attributes/{$field}"],
                        ],
                        $messages,
                    ))
                    ->values()
                    ->all();
            }

            $status = $errorStatus($exception);

            return [[
                'status' => (string) $status,
                'title' => $errorTitle($status),
                'detail' => $errorDetail($exception, $status),
            ]];
        };

        $exceptions->render(function (Throwable $exception, Request $request) use ($errorObjects, $errorStatus) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'errors' => $errorObjects($exception),
            ], $errorStatus($exception));
        });
    })->create();
