<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OverrideValidatePostSize
{
    /**
     * Handle an incoming request.
     *
     * This middleware overrides Laravel's ValidatePostSize by ensuring
     * post_max_size is set to unlimited before the validation check.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force unlimited post size for this request
        // This must be done before Laravel's ValidatePostSize middleware runs
        @ini_set('post_max_size', '-1');
        @ini_set('upload_max_filesize', '-1');

        return $next($request);
    }
}
