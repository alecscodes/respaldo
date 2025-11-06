<?php

use App\Http\Middleware\CheckBannedIp;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ThrottleFailedLogins;
use App\Services\IpBanService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// Set PHP configuration for unlimited file uploads
@ini_set('upload_max_filesize', '-1');
@ini_set('post_max_size', '-1');
@ini_set('memory_limit', '-1');
@ini_set('max_execution_time', '0');
@ini_set('max_input_time', '0');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Remove ValidatePostSize middleware to allow unlimited uploads
        $middleware->remove(\Illuminate\Http\Middleware\ValidatePostSize::class);

        // Override post size settings before any validation
        $middleware->web(prepend: [
            \App\Http\Middleware\OverrideValidatePostSize::class,
            CheckBannedIp::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\AllowLargeUploads::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ThrottleFailedLogins::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\OverrideValidatePostSize::class,
            CheckBannedIp::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\AllowLargeUploads::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ensure API requests return JSON errors
        $exceptions->renderable(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                $statusCode = 500;
                if ($e instanceof HttpException) {
                    $statusCode = $e->getStatusCode();
                }

                return response()->json([
                    'error' => class_basename($e),
                    'message' => $e->getMessage(),
                ], $statusCode);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            $service = app(IpBanService::class);

            // If IP is banned, always return 403, even for 404 errors
            if ($service->isBanned($request)) {
                return response('Access denied', 403);
            }

            $path = $request->path();

            if (str_starts_with($path, 'storage/')) {
                $filePath = storage_path('app/public/'.ltrim(substr($path, 8), '/'));
                if (! file_exists($filePath) && $service->shouldBanPath($path)) {
                    $service->ban($request, 'Non-existent storage file: '.$path);
                }
            } elseif ($e instanceof NotFoundHttpException && ! $request->route() && $service->shouldBanPath($path)) {
                $service->ban($request, 'Non-existent route: '.$path);
            }

            return null;
        });
    })->create();
