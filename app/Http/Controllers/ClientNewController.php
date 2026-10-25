<?php

namespace App\Http\Controllers;

use App\Models\ClientsNew;
use App\Models\Commune;
use App\Models\Wilaya;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class ClientNewController extends Controller
{
    public function signup(Request $request)
    {
        // -------------------------
        // VALIDATION (cleaner)
        // -------------------------
        $data = $request->validate([
            'CliCls' => 'required|max:6|exists:vte_clients_softel,ClsNo',
            'CliNom' => 'required|string|max:100',

            'CliTel' => [
                'required',
                'string',
                'size:10',
                'unique:vte_clients_new,CliTel',
                'regex:/^(05|06|07)[0-9]{8}$/',
            ],

            'CliEmail' => 'required|email|max:255',
            'CliWilaya' => 'required|string|max:255',
            'CliCommune' => 'required|string|max:255',
            'CliGoogleId' => 'required|string|max:500',
        ]);

        // -------------------------
        // ADD SYSTEM FIELDS
        // -------------------------
        $data['CliDate'] = now();
        $data['CliActif'] = false;

        // -------------------------
        // CREATE
        // -------------------------
        $clientNew = ClientsNew::create($data);

        return response()->json([
            'message' => 'Client created successfully',
            'data' => $clientNew
        ], 201); // ✅ fixed
    }

    public function wilayas()
    {
        $wilayas = Wilaya::all();
        return response()->json($wilayas);
    }

    public function communes($wilaya_id)
    {
        $communes = Commune::where('VilTerr', $wilaya_id)->get();
        return response()->json($communes);
    }
}
