<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class TokenHelper
{
    public static function getUser(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            return null;
        }

        $client = $accessToken->tokenable;

        // استخراج الشركة من التوكن
        $company = null;
        foreach ($accessToken->abilities as $ability) {
            if (str_starts_with($ability, 'company:')) {
                $company = explode(':', $ability)[1];
                break;
            }
        }

        // نعدل قيمة الشركة عند العميل عشان تتماشى مع التوكن
        if ($company) {
            $client->CliCls = $company;
        }

        return $client; // يرجع العميل مع cls الصحيح
    }
}
