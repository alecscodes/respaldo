<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowLargeUploads
{
    /**
     * Handle an incoming request.
     *
     * This middleware overrides Laravel's ValidatePostSize by ensuring
     * post_max_size and upload_max_filesize are set to unlimited (-1)
     * before the validation check. This must run early in the middleware stack.
     *
     * Note: ini_set() cannot change post_max_size/upload_max_filesize (PHP_INI_PERDIR),
     * but we set them here as a fallback. The actual configuration should be set in php.ini.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set PHP configuration for unlimited file uploads
        // Using -1 for unlimited (subject to system limits)
        @ini_set('upload_max_filesize', '-1');
        @ini_set('post_max_size', '-1');
        @ini_set('memory_limit', '-1');
        @ini_set('max_execution_time', '0');
        @ini_set('max_input_time', '0');

        return $next($request);
    }
}
