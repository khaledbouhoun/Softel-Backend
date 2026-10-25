<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class TokenHelper
{
    public static function getUser(Request $request)
    {
        // Reuse existing authenticated client if already resolved for this request
        if ($request->attributes->has('auth_user')) {
            $cachedUser = $request->attributes->get('auth_user');
            if ($cachedUser !== null) {
                return $cachedUser;
            }
        }

        // Accepts BOTH Authorization header AND custom X-Auth-Token header
        $token = $request->header('X-Auth-Token')
            ?? $request->bearerToken();

        if (!$token) {
            return null;
        }

        // Validate token with Sanctum
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            return null;
        }

        // Get user/client
        $client = $accessToken->tokenable;

        if ($client) {
            $client->withAccessToken($accessToken);
        }

        // Extract company from token abilities
        $company = collect($accessToken->abilities)
            ->first(fn($ability) => str_starts_with($ability, 'company:'));

        if ($company) {
            $companyId = explode(':', $company)[1] ?? null;
            if ($companyId) {
                $client->CliCls = $companyId;
            }
        }

        if ($client) {
            $request->attributes->set('auth_user', $client);
        }

        return $client;
    }
}
