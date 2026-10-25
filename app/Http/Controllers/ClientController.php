<?php

namespace App\Http\Controllers;

use App\Helpers\TokenHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Client;
use App\Models\ClientsNew;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;


class ClientController extends Controller
{
  public function login(Request $request)
  {
    $request->validate([
      'ClsNo' => 'required|string|max:6',
      'CliGoogleId' => 'required|string|max:500',
    ]);

    // Find client specific to this company
    $client = Client::where('CliCls', $request->ClsNo)
      ->where('CliGoogleId', $request->CliGoogleId)
      ->first();

    // Find in ClientsNew if not in Clients
    $clientNew = ClientsNew::where('CliCls', $request->ClsNo)
      ->where('CliGoogleId', $request->CliGoogleId)
      ->first();

    if (!$client && !$clientNew) {
      return response()->json([
        'status' => false,
        'message' => 'Client not found'
      ], 404);
    } else if (!$client && $clientNew) {
      // If found in ClientsNew show message for wait to confirm from your admin Company
      return response()->json('', 401);
    } else if ($client) {
      // enter the store

      $tokenName = 'auth_token_' . $request->ClsNo . '_' . $client->CliNo;
      // Delete any existing token with this name
      $client->tokens()->where('name', $tokenName)->delete();
      // Create a new token
      $token = $client->createToken($tokenName, ['company:' . $request->ClsNo])->plainTextToken;

      return response()->json([
        'status' => true,
        'message' => 'Login successful',
        'token' => $token,
        'client' => $client
      ], 200);
    }
  }




  public function me(Request $request)
  {
    return response()->json([
      'data' => $request->attributes->get('auth_user')
    ]);
  }

  public function logout(Request $request)
  {
    $client = $request->attributes->get('auth_user') ?? TokenHelper::getUser($request);

    if (!$client) {
      return response()->json(['message' => 'المستخدم غير مصادق'], 401);
    }

    $currentToken = $client->currentAccessToken();

    if ($currentToken) {
      $currentToken->delete();
    } else {
      $token = $request->header('X-Auth-Token') ?? $request->bearerToken();
      if ($token) {
        $accessToken = PersonalAccessToken::findToken($token);
        if ($accessToken && (string) $accessToken->tokenable_id === (string) $client->CliNo) {
          $accessToken->delete();
        }
      }
    }

    return response()->json(['message' => 'تم تسجيل الخروج'], 200);
  }
}
