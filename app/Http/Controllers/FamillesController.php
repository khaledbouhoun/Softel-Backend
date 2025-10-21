<?php

namespace App\Http\Controllers;

use App\Helpers\TokenHelper;
use App\Models\Familles;
use App\Models\SouFamilles;
use Illuminate\Http\Request;

class FamillesController extends Controller
{
    public function index(Request $request)
    {
        $client = TokenHelper::getUser($request);
        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }


        // Get just the families name and id
        $familles = Familles::where('FamCls', $client->CliCls)
            ->get(['FamNo', 'FamNom', 'FamImg']);
        if ($familles->isEmpty()) {
            return response()->json(['message' => 'No families found'], 404);
        } else {
            return response()->json($familles);
        }
    }

    public function soufamilles(Request $request, $FamNo)
    {
        $client = TokenHelper::getUser($request);
        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $famille = SouFamilles::where('SouCls', $client->CliCls)
            ->where('SouFam', $FamNo)
            ->get(['SouFam', 'SouNo', 'SouNom', 'SouImg']);

        if ($famille->isEmpty()) {
            return response()->json(['message' => 'This family does not have any sub-family'], 404);
        } else {
            return response()->json($famille);
        }
    }
}
