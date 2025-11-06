<?php

namespace App\Http\Middleware;

use App\Services\IpBanService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBannedIp
{
    public function __construct(
        private IpBanService $ipBanService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->ipBanService->isBanned($request)) {
            abort(403, 'Access denied');
        }

        return $next($request);
    }
}
