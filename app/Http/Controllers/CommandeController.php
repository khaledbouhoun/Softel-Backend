<?php

namespace App\Http\Controllers;

use App\Helpers\TokenHelper;
use Illuminate\Http\Request;
use App\Models\CommandeHead;
use App\Models\CommandeDetail;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;

class CommandeController extends Controller
{
    public function index(Request $request)
    {
        $client = TokenHelper::getUser($request);
        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }



        $commandes = CommandeHead::where('CdeCls', $client->CliCls)->where('CdeCli', $client->CliNo)->whereNot('CdeStatus', 0)
            ->get();

        if ($commandes->isEmpty()) {
            return response()->json(['message' => 'No commandes found'], 404);
        } else {
            $formatted = $commandes->map(function ($commande) use ($client) {
                return [
                    'CdeID' => $commande->CdeID,
                    'CdeCls' => $commande->CdeCls,
                    'CdeCli' => $commande->CdeCli,
                    'CdeDate' => $commande->CdeDate->format('Y-m-d H:i:s'),
                    'CdeStatus' => $commande->CdeStatus,
                    'CdeMontant' => (float) $commande->CdeMontant,
                ];
            });
            return response()->json($formatted, 200);
        }
    }

    public function details(Request $request, $id)
    {
        $client = TokenHelper::getUser($request);
        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }



        $commandes = CommandeHead::where('CdeCli', $client->CliNo)->where('CdeID', $id)->first();

        if (!$commandes) {
            return response()->json(['message' => 'cette commande n\'existe '], 404);
        } else {
            $commandeDetails = CommandeDetail::where('CddID', $commandes->CdeID)->get();

            if ($commandeDetails->isEmpty()) {
                return response()->json(['message' => 'No details found for this commande'], 404);
            } else {
                // Format the details if needed
                $formattedDetails = $commandeDetails->map(function ($detail) use ($client) {
                    // Assuming the product relationship is defined in CommandeDetail
                    $product = $detail->product($client->CliCls)->first();
                    return [
                        'CddID'           => $detail->CddID,
                        'Cls'             => $client->CliCls,
                        'CddArtNo'        => $detail->CddArt,
                        'CddArtNom'       => optional($product)->ArtNom,
                        'CddArtFam'       => optional(optional($product)->familles($client->CliCls)->first())->FamNom,
                        'CddArtSFam'      => optional(optional($product)->sousfamilles($product->ArtFam, $client->CliCls)->first())->SouNom,
                        'CddArtUni'       => optional(optional($product)->unite($client->CliCls)->first())->UniABV,
                        'CddColisage'     => $detail->CddColisage,
                        'CddColisageNom'  => optional(optional($product)->cloisage($client->CliCls)->first())->ColABV,
                        'CddColis'        => (float) $detail->CddColis,
                        'CddUntCol'       => (float) $detail->CddUntCol,
                        'CddQte'          => (float) $detail->CddQte,
                        'CddPrix'         => (float) $detail->CddPrix,
                        'CddMontant'      => (float) $detail->CddMontant,
                    ];
                });
                return response()->json($formattedDetails, 200);
            }
        }
    }


    public function confirmCommande(Request $request)
    {
        $client = TokenHelper::getUser($request);
        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }



        $commandeHead = CommandeHead::where('CdeCli', $client->CliNo)
            ->where('CdeStatus', 0)
            ->first();

        if (!$commandeHead) {
            return response()->json(['message' => 'No active commande found'], 404);
        } else {
            $commandeHead->update(['CdeStatus' => 1]); // Mark as confirmed
            return response()->json(['message' => 'Commande confirmed successfully', 'commande' => $commandeHead], 200);
        }
    }

    public function cart(Request $request)
    {
        $client = TokenHelper::getUser($request);
        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }


        $commandeHead = CommandeHead::where('CdeCli', $client->CliNo)
            ->where('CdeCls', $client->CliCls)
            ->where('CdeStatus', 0)
            ->first();
        if (!$commandeHead) {
            return response()->json(['message' => 'No active commande found'], 404);
        }

        $commandeDetails = CommandeDetail::where('CddID', $commandeHead->CdeID)->get();

        if ($commandeDetails->isEmpty()) {
            return response()->json(['message' => 'No details found for this commande'], 404);
        } else {
            // Format the details if needed
            $formattedDetails = $commandeDetails->map(function ($detail) use ($client) {
                // Assuming the product relationship is defined in CommandeDetail
                $product = $detail->product($client->CliCls)->first();
                return [
                    'CddID'          => $detail->CddID,
                    'Cls'            => $client->CliCls,
                    'CddArtNo'       => $detail->CddArt,
                    'CddArtNom'      => optional($product)->ArtNom,
                    'CddArtFam'      => optional(optional($product)->familles($client->CliCls)->first())->FamNom,
                    'CddArtSFam'     => optional(optional($product)->sousfamilles($product->ArtFam, $client->CliCls)->first())->SouNom,
                    'CddArtUni'      => optional(optional($product)->unite($client->CliCls)->first())->UniABV,
                    'CddColisage'    => $detail->CddColisage,
                    'CddColisageNom' => optional(optional($product)->cloisage($client->CliCls)->first())->ColABV,
                    'CddColis'       => (float) $detail->CddColis,
                    'CddUntCol'      => (float) $detail->CddUntCol,
                    'CddQte'         => (float) $detail->CddQte,
                    'CddPrix'        => (float) $detail->CddPrix,
                    'CddMontant'     => (float) $detail->CddMontant,
                ];
            });
            return response()->json($formattedDetails, 200);
        }
    }
    public function cartTrackingDetails(Request $request, $CdeID)
    {
        $client = TokenHelper::getUser($request);
        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }


        $commandeHead = CommandeHead::where('CdeCli', $client->CliNo)
            ->where('CdeCls', $client->CliCls)
            ->where('CdeID', $CdeID)
            ->first();
        if (!$commandeHead) {
            return response()->json(['message' => 'No active commande found'], 404);
        }

        $commandeDetails = CommandeDetail::where('CddID', $commandeHead->CdeID)->get();

        if ($commandeDetails->isEmpty()) {
            return response()->json(['message' => 'No details found for this commande'], 404);
        } else {
            // Format the details if needed
            $formattedDetails = $commandeDetails->map(function ($detail) use ($client) {
                // Assuming the product relationship is defined in CommandeDetail
                $product = $detail->product($client->CliCls)->first();
                return [
                    'CddID'          => $detail->CddID,
                    'Cls'            => $client->CliCls,
                    'CddArtNo'       => $detail->CddArt,
                    'CddArtNom'      => optional($product)->ArtNom,
                    'CddArtFam'      => data_get($product?->familles($client->CliCls)->first(), 'FamNom'),
                    'CddArtSFam'     => data_get($product?->sousfamilles($product->ArtFam, $client->CliCls)->first(), 'SouNom'),
                    'CddArtUni'      => data_get($product?->unite($client->CliCls)->first(), 'UniABV'),
                    'CddColisage'    => $detail->CddColisage,
                    'CddColisageNom' => data_get($product?->cloisage($client->CliCls)->first(), 'ColABV'),
                    'CddColis'       => (float) $detail->CddColis,
                    'CddUntCol'      => (float) $detail->CddUntCol,
                    'CddQte'         => (float) $detail->CddQte,
                    'CddPrix'        => (float) $detail->CddPrix,
                    'CddMontant'     => (float) $detail->CddMontant,
                ];
            });
            return response()->json($formattedDetails, 200);
        }
    }

    public function deleteDetailsById(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'CddID'    => 'required|integer',
            'CddArtNo' => 'required|string|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $CddID    = $request->CddID;
        $CddArtNo = $request->CddArtNo;

        $commandeDetails = CommandeDetail::where('CddArt', $CddArtNo)
            ->where('CddID', $CddID)
            ->first();

        if (!$commandeDetails) {
            return response()->json(['message' => 'This detail does not exist'], 404);
        }

        // نخزن نسخة قبل الحذف
        $deletedData = $commandeDetails->toArray();

        $commandeDetails->delete();

        return response()->json([
            'message' => 'Details deleted successfully',
            'deleted' => $deletedData
        ], 200);
    }


    public function store(Request $request)
    {
        // exemple of json request body
        // {
        //             "CddArt": "000003",      
        //             "CddColisage": "01",
        //             "CddColis": 2.00,
        //             "CddUntCol": 5.00,
        //             "CddQte": 20.00,
        //             "CddPrix": 5.00,
        //             "CddMontant": 100.00
        // }

        $validator = Validator::make($request->all(), [
            'CddArt' => 'required|string|max:6',
            'CddColisage' => 'required|string|max:2',
            'CddColis' => 'nullable|numeric',
            'CddUntCol' => 'nullable|numeric',
            'CddQte' => 'required|numeric',
            'CddPrix' => 'required|numeric',
            'CddMontant' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        } else {
            $client = TokenHelper::getUser($request);
            if (!$client) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            DB::beginTransaction();
            try {
                // check if the commande head already exists with the same client
                $commandeHead = CommandeHead::where('CdeCls', $client->CliCls)
                    ->where('CdeCli', $client->CliNo)
                    ->where('CdeStatus', 0)
                    ->first();
                if ($commandeHead) {
                    $commandeHead->update([
                        'CdeDate' => now(),
                    ]);
                } else {
                    $commandeHead = CommandeHead::create([
                        'CdeCls' => $client->CliCls,
                        'CdeCli' => $client->CliNo,
                        'CdeDate' => now(),
                        'CdeStatus' => 0,
                        'CdeMontant' => 0,
                    ]);
                }
                // Update or create detail
                $this->storeDetail($request->all(), $commandeHead->CdeID);
                // Calculate total amount for the commande
                $totalAmount = CommandeDetail::where('CddID', $commandeHead->CdeID)
                    ->sum('CddMontant');
                $commandeHead->update(['CdeMontant' => $totalAmount]);
                DB::commit();
                return response()->json(['message' => 'Commande créée avec succès', 'commande' => $commandeHead, 'detail' => $request->all()], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['error' => 'Erreur lors de la création de la commande', 'details' => $e->getMessage()], 500);
            }
        }
    }

    public function storeDetail($detail, $commandeHeadId)
    {
        // Exemple de corps de requête JSON
        // {
        //     "CddArt": "ART001",
        //     "CddColisage": 10.00,
        //     "CddColis": 2.00,
        //     "CddUntCol": 5.00,
        //     "CddQte": 20.00,
        //     "CddPrix": 5.00,
        //     "CddMontant": 100.00
        // }
        try {
            if (CommandeDetail::where('CddArt', $detail['CddArt'])->where('CddID', $commandeHeadId)->exists()) {
                CommandeDetail::where('CddArt', $detail['CddArt'])->where('CddID', $commandeHeadId)->update([
                    'CddColisage' => $detail['CddColisage'],
                    'CddColis' => $detail['CddColis'] ?? null,
                    'CddUntCol' => $detail['CddUntCol'] ?? null,
                    'CddQte' => $detail['CddQte'],
                    'CddPrix' => $detail['CddPrix'],
                    'CddMontant' => $detail['CddMontant'],
                ]);
            } else {
                CommandeDetail::create([
                    'CddID' => $commandeHeadId,
                    'CddArt' => $detail['CddArt'],
                    'CddColisage' => $detail['CddColisage'],
                    'CddColis' => $detail['CddColis'] ?? null,
                    'CddUntCol' => $detail['CddUntCol'] ?? null,
                    'CddQte' => $detail['CddQte'],
                    'CddPrix' => $detail['CddPrix'],
                    'CddMontant' => $detail['CddMontant'],
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la création du détail de commande', 'details' => $e->getMessage()], 500);
            // Optionally log the error
        }
    }
}
