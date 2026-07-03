<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureRestaurantPermission;
use App\Http\Middleware\EnsureInstallationCompleted;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use App\Console\Commands\CheckPayoutBalance;
use App\Console\Commands\GeneratePayouts;
use App\Console\Commands\ProcessScheduledPayouts;
use App\Console\Commands\RetryFailedPayouts;
use App\Console\Commands\RebuildSearchIndex;
use App\Console\Commands\SyncPayoutStatus;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        GeneratePayouts::class,
        ProcessScheduledPayouts::class,
        RetryFailedPayouts::class,
        RebuildSearchIndex::class,
        SyncPayoutStatus::class,
        CheckPayoutBalance::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
            // Register middleware aliases
            $middleware->alias([
                'role' => CheckRole::class,
                'restaurant.permission' => EnsureRestaurantPermission::class,
                'purchase.verified' => \App\Http\Middleware\EnsurePurchaseCodeVerified::class,
            ]);
        $middleware->append(EnsureInstallationCompleted::class);
        $middleware->validateCsrfTokens(except: [
            'webhooks/razorpay/payout',
            'webhooks/stripe/payout',
            'webhooks/cashfree/payout',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return JSON for API routes
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*')) {
                $status = 500;

                if ($e instanceof AuthenticationException) {
                    $status = 401;
                } elseif ($e instanceof AuthorizationException) {
                    $status = 403;
                } elseif ($e instanceof ValidationException) {
                    $status = 422;
                } elseif (method_exists($e, 'getStatusCode')) {
                    $status = $e->getStatusCode();
                }

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'An error occurred',
                    'error' => class_basename($e),
                ], $status);
            }
        });
    })->create();
