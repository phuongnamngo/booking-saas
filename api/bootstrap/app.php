<?php

use App\Exceptions\SlotUnavailableException;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\ResolveAuthenticatedTenant;
use App\Http\Middleware\ResolvePublicTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'tenant.public' => ResolvePublicTenant::class,
            'tenant.auth' => ResolveAuthenticatedTenant::class,
            'super.admin' => EnsureSuperAdmin::class,
        ]);
        $middleware->prependToPriorityList(
            ThrottleRequests::class,
            ResolvePublicTenant::class,
        );
        $middleware->prependToPriorityList(
            ThrottleRequests::class,
            ResolveAuthenticatedTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (SlotUnavailableException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Khung giờ không còn trống.',
                    'code' => 'slot_unavailable',
                ], 409);
            }
        });
    })->create();
