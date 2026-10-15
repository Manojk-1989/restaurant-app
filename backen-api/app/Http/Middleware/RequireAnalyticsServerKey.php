<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAnalyticsServerKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('analytics.server_key', '');
        $provided = (string) $request->header('X-Analytics-Key', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}