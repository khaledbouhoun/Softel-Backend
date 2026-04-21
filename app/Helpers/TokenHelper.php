<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class TokenHelper
{
    public static function getUser(Request $request)
    {
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

        // Extract company from token abilities
        $company = collect($accessToken->abilities)
            ->first(fn($ability) => str_starts_with($ability, 'company:'));

        if ($company) {
            $companyId = explode(':', $company)[1] ?? null;
            if ($companyId) {
                $client->CliCls = $companyId;
            }
        }

        return $client;
    }
}
