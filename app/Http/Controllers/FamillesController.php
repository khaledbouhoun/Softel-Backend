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

        $familles = Familles::where('FamCls', $client->CliCls)
            ->select('FamNo', 'FamNom', 'FamImg')
            ->get();

        if ($familles->isEmpty()) {
            return response()->json(['message' => 'No families found'], 404);
        }

        $data = $familles->map(function ($famille) {
            return [
                'FamNo'  => $famille->FamNo,
                'FamNom' => $famille->FamNom,
                'FamImg' => $famille->FamImg
                    ? url('images/familles/' . $famille->FamImg)
                    : url('images/default.png'),
            ];
        });

        return response()->json($data, 200);
    }

    public function soufamilles(Request $request, $FamNo)
    {
        $client = TokenHelper::getUser($request);

        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $soufamilles = SouFamilles::where('SouCls', $client->CliCls)
            ->where('SouFam', $FamNo)
            ->select('SouFam', 'SouNo', 'SouNom', 'SouImg')
            ->get();

        if ($soufamilles->isEmpty()) {
            return response()->json([
                'message' => 'This family does not have any sub-family'
            ], 404);
        }

        $data = $soufamilles->map(function ($item) {
            return [
                'SouFam' => $item->SouFam,
                'SouNo'  => $item->SouNo,
                'SouNom' => $item->SouNom,
                'SouImg' => $item->SouImg
                    ? url('images/familles/' . $item->SouImg)
                    : url('images/default.png'),
            ];
        });

        return response()->json($data, 200);
    }
}
