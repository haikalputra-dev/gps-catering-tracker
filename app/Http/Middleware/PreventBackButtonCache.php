<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent browsers from serving authenticated pages from bfcache
 * or the disk cache after logout. Without these headers, hitting
 * the browser back button after signing out re-paints the last
 * authenticated view even though the session has been invalidated
 * on the server. Applying `no-store` forces the browser to always
 * fetch fresh, which triggers the `auth` middleware redirect.
 */
class PreventBackButtonCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
