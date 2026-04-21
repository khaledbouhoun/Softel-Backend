<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\TokenHelper;

class AuthenticateWithCustomToken
{
    public function handle(Request $request, Closure $next)
    {
        $user = TokenHelper::getUser($request);

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        // Attach user to request
        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}
