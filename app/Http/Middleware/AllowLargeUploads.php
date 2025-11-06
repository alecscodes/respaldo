<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowLargeUploads
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set PHP configuration for unlimited file uploads
        // Note: These settings only apply if PHP allows them (can't override php.ini restrictions)
        // Using -1 for unlimited (or very large value if -1 not supported)
        @ini_set('upload_max_filesize', '-1');
        @ini_set('post_max_size', '-1');
        @ini_set('memory_limit', '-1');
        @ini_set('max_execution_time', '0');
        @ini_set('max_input_time', '0');

        return $next($request);
    }
}
