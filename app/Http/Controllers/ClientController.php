<?php

namespace App\Http\Controllers;

use App\Helpers\TokenHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Client;
use App\Models\ClientsNew;
use Illuminate\Support\Facades\Validator;


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
            // If found in ClientsNew, create in Clients
            return response()->json('', 401);
        } else if ($client) {

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

        $client = TokenHelper::getUser($request);
        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return response()->json($client);
    }

    public function logout(Request $request)
    {
        $client = TokenHelper::getUser($request);

        if (!$client || !$client) {
            return response()->json(['message' => 'المستخدم غير مصادق'], 401);
        }

        $client->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج']);
    }
}
