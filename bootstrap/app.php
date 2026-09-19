<?php

use App\Domain\Orders\Exceptions\InsufficientStockException;
use App\Domain\Orders\Exceptions\ProductUnavailableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn(Request $request) => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (
            InsufficientStockException $e,
            Request $request
        ): ?JsonResponse {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        });

        $exceptions->render(function (
            ProductUnavailableException $e,
            Request $request
        ): ?JsonResponse {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        });

        $exceptions->render(function (DomainException $e, Request $request): ?JsonResponse {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        });
    })
    ->create();
