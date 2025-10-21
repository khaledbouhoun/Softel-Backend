<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CliEmail' => 'required|email|unique:clients,CliEmail',
            'CliPass' => 'required|min:6',
            'company_id' => 'required|exists:companies,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $client = Client::create([
            'CliEmail' => $request->CliEmail,
            'CliPass' => Hash::make($request->CliPass),
            'company_id' => $request->company_id
        ]);

        return response()->json(['message' => 'تم التسجيل بنجاح', 'client' => $client]);
    }
}
