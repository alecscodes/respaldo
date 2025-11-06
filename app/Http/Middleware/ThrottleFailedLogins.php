<?php

namespace App\Http\Middleware;

use App\Models\FailedLoginAttempt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThrottleFailedLogins
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('login') || ! $request->isMethod('post')) {
            return $next($request);
        }

        $response = $next($request);
        $ip = $request->ip();
        $email = $request->input('email');

        if (! $email) {
            return $response;
        }

        // Check multiple indicators of successful login:
        // 1. User is authenticated
        // 2. Redirect location indicates success (dashboard or two-factor)
        $isAuthenticated = auth()->check();
        $status = $response->getStatusCode();
        $location = strtolower($response->headers->get('Location', ''));

        $redirectsToSuccess = $status === 302 && (
            str_contains($location, 'dashboard') ||
            str_contains($location, 'two-factor') ||
            str_contains($location, route('dashboard', absolute: false))
        );

        if ($isAuthenticated || $redirectsToSuccess) {
            // Successful login - reset any failed attempts
            FailedLoginAttempt::resetAttempts($ip, $email);

            return $response;
        }

        // Not authenticated and not redirecting to success = failed login
        // Only record if we got a password (to avoid counting empty form submissions)
        if ($request->filled('password')) {
            FailedLoginAttempt::recordFailure($ip, $email);
        }

        return $response;
    }
}
