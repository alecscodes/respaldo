<?php

namespace App\Http\Middleware;

use App\Services\IpBanService;
use App\Services\LogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBannedIp
{
    public function __construct(
        private IpBanService $ipBanService,
        private LogService $logService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->ipBanService->isBanned($request)) {
            $this->logService->warning('security', 'Banned IP access attempt', [
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
            abort(403, 'Access denied');
        }

        return $next($request);
    }
}
