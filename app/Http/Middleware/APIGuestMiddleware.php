<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class APIGuestMiddleware
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (auth('api')->check()) {
            $request->merge(['user' => auth('api')->user()]);
            return $next($request);
        }
        if ($request->header('Authorization') && app('auth')->guard('api')->user()) {
            $request->merge(['user' => auth('api')->user()]);
            return $next($request);
        }
        if ($request->guest_id) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
