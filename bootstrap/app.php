<?php

use App\Http\Middleware\AllowLargeUploads;
use App\Http\Middleware\BlockBots;
use App\Http\Middleware\CheckBannedIp;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ThrottleFailedLogins;
use App\Services\IpBanService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $middleware->remove(ValidatePostSize::class);

        // Block bots and crawlers first
        $middleware->web(prepend: [
            BlockBots::class,
            AllowLargeUploads::class,
            CheckBannedIp::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ThrottleFailedLogins::class,
        ]);

        $middleware->api(prepend: [
            BlockBots::class,
            AllowLargeUploads::class,
            CheckBannedIp::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ensure API requests return JSON errors
        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                $statusCode = $e instanceof HttpException ? $e->getStatusCode() : 500;

                $level = $statusCode >= 500 ? 'error' : 'warning';
                Log::channel('database')->{$level}('API exception', [
                    'category' => 'api',
                    'exception' => class_basename($e),
                    'message' => $e->getMessage(),
                    'status_code' => $statusCode,
                    'path' => $request->path(),
                ]);

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
                    Log::channel('database')->warning('Suspicious path access attempt', [
                        'category' => 'security',
                        'path' => $path,
                        'type' => 'non-existent storage file',
                    ]);
                    $service->ban($request, 'Non-existent storage file: '.$path);

                    return response('Access denied', 403);
                }
            } elseif ($e instanceof NotFoundHttpException && ! $request->route() && $service->shouldBanPath($path)) {
                Log::channel('database')->warning('Suspicious route access attempt', [
                    'category' => 'security',
                    'path' => $path,
                    'type' => 'non-existent route',
                ]);
                $service->ban($request, 'Non-existent route: '.$path);

                return response('Access denied', 403);
            }

            return null;
        });
    })->create();
