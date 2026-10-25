<?php

namespace App\Http\Controllers;

use App\Helpers\TokenHelper;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\CommandeHead;
use App\Models\CommandeDetail;
use App\Models\Product;
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

    $limit = min((int) $request->query('limit', 15), 50);
    $offset = max((int) $request->query('offset', 0), 0);

    $commandes = CommandeHead::where('CdeCls', $client->CliCls)
      ->where('CdeCli', $client->CliNo)
      ->where('CdeStatus', '!=', 0) // ✅ FIX
      ->orderByDesc('CdeDate')      // ✅ cleaner
      ->skip($offset)               // ✅ pagination
      ->take($limit)
      ->get([
        'CdeID',
        'CdeCls',
        'CdeCli',
        'CdeDate',
        'CdeStatus',
        'CdeMontant'
      ]);

    if ($commandes->isEmpty()) {
      return response()->json(['message' => 'No commandes found'], 404);
    }

    $data = $commandes->map(function ($commande) {
      return [
        'CdeID' => $commande->CdeID,
        'CdeCls' => $commande->CdeCls,
        'CdeCli' => $commande->CdeCli,
        'CdeDate' => $commande->CdeDate?->format('Y-m-d'),
        'CdeStatus' => $commande->CdeStatus,
        'CdeMontant' => (float) $commande->CdeMontant,
      ];
    });

    return response()->json($data, 200);
  }

  public function details(Request $request, $id)
  {
    $client = TokenHelper::getUser($request);

    if (!$client) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    // -------------------------
    // Load commande (secure)
    // -------------------------
    $commande = CommandeHead::where('CdeCls', $client->CliCls)
      ->where('CdeCli', $client->CliNo)
      ->where('CdeID', $id)
      ->first();

    if (!$commande) {
      return response()->json(['message' => 'Commande not found'], 404);
    }

    // -------------------------
    // Load details
    // -------------------------
    $details = CommandeDetail::where('CddID', $commande->CdeID)->get();

    if ($details->isEmpty()) {
      return response()->json(['message' => 'No details found for this commande'], 404);
    }

    // -------------------------
    // Load ALL products ONCE (KEY FIX 🔥)
    // -------------------------
    $productIds = $details->pluck('CddArt')->unique();

    $products = Product::forClient($client)
      ->whereIn('ArtNo', $productIds)
      ->withClientRelations($client)
      ->get()
      ->keyBy('ArtNo'); // ⚡ O(1) lookup

    // -------------------------
    // FORMAT (NO DB CALLS)
    // -------------------------
    $formatted = $details->map(function ($detail) use ($client, $products) {

      $product = $products[$detail->CddArt] ?? null;

      return [
        'CddID' => $detail->CddID,
        'Cls' => $client->CliCls,
        'CddArtNo' => $detail->CddArt,

        'CddArtNom' => optional($product)->ArtNom,
        'CddArtFam' => optional(optional($product)->familles)->FamNom,
        'CddArtSFam' => optional(optional($product)->sousfamilles)->SouNom,
        'CddArtUni' => optional(optional($product)->unite)->UniABV,

        'CddColisage' => $detail->CddColisage,
        'CddColisageNom' => optional(optional($product)->cloisage)->ColABV,

        'CddColis' => (float) $detail->CddColis,
        'CddUntCol' => (float) $detail->CddUntCol,
        'CddQte' => (float) $detail->CddQte,
        'CddPrix' => (float) $detail->CddPrix,
        'CddMontant' => (float) $detail->CddMontant,

        // ✅ already eager loaded
        'CddImages' => $product
          ? $product->imagesarticles->map(fn($img) => [
            'ImgNom' => $img->ImgNom
              ? url('images/articles/' . $img->ImgNom)
              : url('images/default.png'),
          ])
          : [],
      ];
    });

    return response()->json($formatted, 200);
  }


  public function confirmCommande(Request $request)
  {
    $client = TokenHelper::getUser($request);

    if (!$client) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    return DB::transaction(function () use ($request, $client) {
      $query = CommandeHead::where('CdeCli', $client->CliNo)
        ->where('CdeCls', $client->CliCls)
        ->where('CdeStatus', 0);

      if ($request->filled('CdeID')) {
        $query->where('CdeID', $request->input('CdeID'));
      }

      $commandeHead = $query->lockForUpdate()->first();

      if (!$commandeHead) {
        return response()->json(['message' => 'No active commande found'], 404);
      }

      // Verify cart contains at least one item
      $hasItems = CommandeDetail::where('CddID', $commandeHead->CdeID)->exists();

      if (!$hasItems) {
        return response()->json(['message' => 'Cannot confirm an empty cart'], 422);
      }

      // Authoritatively recalculate order total from existing details
      $totalAmount = (float) CommandeDetail::where('CddID', $commandeHead->CdeID)->sum('CddMontant');

      $commandeHead->update([
        'CdeStatus' => 1,
        'CdeMontant' => $totalAmount,
        'CdeDate' => now(),
      ]);

      return response()->json([
        'message' => 'Commande confirmed successfully',
        'commande' => $commandeHead->fresh()
      ], 200);
    });
  }

  public function cart(Request $request)
  {
    $client = TokenHelper::getUser($request);

    if (!$client) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    // -------------------------
    // Get active commande
    // -------------------------
    $commandeHead = CommandeHead::where('CdeCli', $client->CliNo)
      ->where('CdeCls', $client->CliCls)
      ->where('CdeStatus', 0)
      ->first();

    if (!$commandeHead) {
      return response()->json(['message' => 'No active commande found'], 404);
    }

    // -------------------------
    // Load details
    // -------------------------
    $details = CommandeDetail::where('CddID', $commandeHead->CdeID)->get();

    if ($details->isEmpty()) {
      return response()->json(['message' => 'No details found'], 404);
    }

    // -------------------------
    // LOAD PRODUCTS ONCE 🔥
    // -------------------------
    $productIds = $details->pluck('CddArt')->unique();

    $products = Product::forClient($client)
      ->whereIn('ArtNo', $productIds)
      ->withClientRelations($client)
      ->get()
      ->keyBy('ArtNo');

    // -------------------------
    // FORMAT
    // -------------------------
    $data = $details->map(function ($detail) use ($client, $products) {

      $product = $products[$detail->CddArt] ?? null;

      return [
        'CddID' => $detail->CddID,
        'Cls' => $client->CliCls,
        'CddArtNo' => $detail->CddArt,

        'CddArtNom' => optional($product)->ArtNom,
        'CddArtFam' => optional($product->familles)->FamNom ?? null,
        'CddArtSFam' => optional($product->sousfamilles)->SouNom ?? null,
        'CddArtUni' => optional($product->unite)->UniABV ?? null,

        'CddColisage' => $detail->CddColisage,
        'CddColisageNom' => optional($product->cloisage)->ColABV ?? null,

        'CddColis' => (float) $detail->CddColis,
        'CddUntCol' => (float) $detail->CddUntCol,
        'CddQte' => (float) $detail->CddQte,
        'CddPrix' => (float) $detail->CddPrix,
        'CddMontant' => (float) $detail->CddMontant,

        'CddImages' => $product
          ? $product->imagesarticles->map(fn($img) => [
            'ImgNom' => $img->ImgNom
              ? url('images/articles/' . $img->ImgNom)
              : url('images/default.png'),
          ])
          : [],
      ];
    });

    return response()->json($data, 200);
  }

  public function cartTrackingDetails(Request $request, $CdeID)
  {
    $client = TokenHelper::getUser($request);

    if (!$client) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    // -------------------------
    // Get commande
    // -------------------------
    $commandeHead = CommandeHead::where('CdeCli', $client->CliNo)
      ->where('CdeCls', $client->CliCls)
      ->where('CdeID', $CdeID)
      ->first();

    if (!$commandeHead) {
      return response()->json(['message' => 'Commande not found'], 404);
    }

    // -------------------------
    // Load details
    // -------------------------
    $details = CommandeDetail::where('CddID', $commandeHead->CdeID)->get();

    if ($details->isEmpty()) {
      return response()->json(['message' => 'No details found'], 404);
    }

    // -------------------------
    // LOAD PRODUCTS ONCE 🔥
    // -------------------------
    $productIds = $details->pluck('CddArt')->unique();

    $products = Product::forClient($client)
      ->whereIn('ArtNo', $productIds)
      ->withClientRelations($client)
      ->get()
      ->keyBy('ArtNo');

    // -------------------------
    // FORMAT
    // -------------------------
    $data = $details->map(function ($detail) use ($client, $products) {

      $product = $products[$detail->CddArt] ?? null;

      return [
        'CddID' => $detail->CddID,
        'Cls' => $client->CliCls,
        'CddArtNo' => $detail->CddArt,

        'CddArtNom' => optional($product)->ArtNom,
        'CddArtFam' => optional($product->familles)->FamNom ?? null,
        'CddArtSFam' => optional($product->sousfamilles)->SouNom ?? null,
        'CddArtUni' => optional($product->unite)->UniABV ?? null,

        'CddColisage' => $detail->CddColisage,
        'CddColisageNom' => optional($product->cloisage)->ColABV ?? null,

        'CddColis' => (float) $detail->CddColis,
        'CddUntCol' => (float) $detail->CddUntCol,
        'CddQte' => (float) $detail->CddQte,
        'CddPrix' => (float) $detail->CddPrix,
        'CddMontant' => (float) $detail->CddMontant,

        'CddImages' => $product
          ? $product->imagesarticles->map(fn($img) => [
            'ImgNom' => $img->ImgNom
              ? url('images/articles/' . $img->ImgNom)
              : url('images/default.png'),
          ])
          : [],
      ];
    });

    return response()->json($data, 200);
  }

  public function deleteDetailsById(Request $request)
  {
    $client = TokenHelper::getUser($request);

    if (!$client) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    $validator = Validator::make($request->all(), [
      'CddID' => 'required|integer',
      'CddArtNo' => 'required|string|max:6',
    ]);

    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    return DB::transaction(function () use ($request, $client) {
      $commandeHead = CommandeHead::where('CdeID', $request->CddID)
        ->lockForUpdate()
        ->first();

      if (!$commandeHead) {
        return response()->json(['message' => 'Commande not found'], 404);
      }

      if ($commandeHead->CdeCli !== $client->CliNo || $commandeHead->CdeCls !== $client->CliCls) {
        return response()->json(['message' => 'Unauthorized to modify this commande'], 403);
      }

      if ((int) $commandeHead->CdeStatus !== 0) {
        return response()->json(['message' => 'Cannot modify confirmed commande'], 403);
      }

      $deleted = CommandeDetail::where('CddID', $commandeHead->CdeID)
        ->where('CddArt', $request->CddArtNo)
        ->delete();

      if ($deleted === 0) {
        return response()->json(['message' => 'Detail not found'], 404);
      }

      $newTotal = (float) CommandeDetail::where('CddID', $commandeHead->CdeID)->sum('CddMontant');

      $commandeHead->update([
        'CdeMontant' => $newTotal
      ]);

      return response()->json([
        'message' => 'Deleted successfully',
        'deleted_rows' => $deleted
      ], 200);
    });
  }


  public function store(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'CddArt' => 'required|string|max:6',
      'CddColisage' => 'required|string|max:2',
      'CddColis' => 'nullable|numeric',
      'CddUntCol' => 'nullable|numeric',
      'CddQte' => 'required|numeric|gt:0',
      'CddPrix' => 'required|numeric',
      'CddMontant' => 'required|numeric',
    ]);

    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    $client = TokenHelper::getUser($request);

    if (!$client) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Authoritative product lookup scoped to client's company
    $product = Product::forClient($client)
      ->withClientRelations($client)
      ->where('ArtNo', $request->CddArt)
      ->first();

    if (!$product) {
      return response()->json(['message' => 'Product not found'], 404);
    }

    $authoritativePrice = $product->priceForClient($client);

    if ($authoritativePrice === null || !is_numeric($authoritativePrice) || $authoritativePrice < 0) {
      return response()->json(['message' => 'Valid tariff not found for product'], 422);
    }

    $quantity = (float) $request->CddQte;
    $authoritativeMontant = round((float) $authoritativePrice * $quantity, 2);

    DB::beginTransaction();

    try {

      // -------------------------
      // SERIALIZE CONCURRENT CART CREATION (DATA-01)
      // Lock stable client record to serialize concurrent cart-creation requests
      // -------------------------
      Client::where('CliNo', $client->CliNo)
        ->where('CliCls', $client->CliCls)
        ->lockForUpdate()
        ->first();

      // -------------------------
      // GET OR CREATE HEAD (PROTECTED RE-CHECK)
      // -------------------------
      $commandeHead = CommandeHead::where('CdeCls', $client->CliCls)
        ->where('CdeCli', $client->CliNo)
        ->where('CdeStatus', 0)
        ->lockForUpdate()
        ->first();

      if (!$commandeHead) {
        $commandeHead = CommandeHead::create([
          'CdeCls' => $client->CliCls,
          'CdeCli' => $client->CliNo,
          'CdeStatus' => 0,
          'CdeDate' => now(),
          'CdeMontant' => 0,
        ]);
      } else {
        $commandeHead->update([
          'CdeDate' => now(),
        ]);
      }

      // -------------------------
      // STORE DETAIL (AUTHORITATIVE VALUES)
      // -------------------------
      $this->storeDetail($request->all(), $commandeHead->CdeID, (float) $authoritativePrice, $authoritativeMontant);

      // -------------------------
      // UPDATE TOTAL (ONE QUERY)
      // -------------------------
      $totalAmount = CommandeDetail::where('CddID', $commandeHead->CdeID)
        ->sum('CddMontant');

      $commandeHead->update([
        'CdeMontant' => $totalAmount
      ]);

      DB::commit();

      return response()->json([
        'message' => 'Commande saved successfully',
        'commande' => $commandeHead
      ], 201);
    } catch (\Exception $e) {
      DB::rollBack();

      return response()->json([
        'error' => 'Failed to save commande',
        'details' => $e->getMessage()
      ], 500);
    }
  }

  public function storeDetail($detail, $commandeHeadId, $authoritativePrice = null, $authoritativeMontant = null)
  {
    $unitPrice = $authoritativePrice !== null ? $authoritativePrice : ($detail['CddPrix'] ?? null);
    $montant = $authoritativeMontant !== null ? $authoritativeMontant : ($detail['CddMontant'] ?? null);

    DB::table('vte_commandesd')->updateOrInsert(
      [
        'CddID' => $commandeHeadId,
        'CddArt' => $detail['CddArt'],
      ],
      [
        'CddColisage' => $detail['CddColisage'] ?? null,
        'CddColis' => $detail['CddColis'] ?? null,
        'CddUntCol' => $detail['CddUntCol'] ?? null,
        'CddQte' => $detail['CddQte'] ?? null,
        'CddPrix' => $unitPrice,
        'CddMontant' => $montant,
      ]
    );
  }
  // public function storeDetail($detail, $commandeHeadId)
  // {
  //     try {

  //         // -------------------------
  //         // UPSERT (ONE QUERY STYLE)
  //         // -------------------------
  //         CommandeDetail::updateOrCreate(
  //             [
  //                 'CddID'  => $commandeHeadId,
  //                 'CddArt' => $detail['CddArt'],
  //             ],
  //             [
  //                 'CddColisage' => $detail['CddColisage'],
  //                 'CddColis'    => $detail['CddColis'] ?? null,
  //                 'CddUntCol'   => $detail['CddUntCol'] ?? null,
  //                 'CddQte'      => $detail['CddQte'],
  //                 'CddPrix'     => $detail['CddPrix'],
  //                 'CddMontant'  => $detail['CddMontant'],
  //             ]
  //         );
  //     } catch (\Exception $e) {

  //         // ❗ DO NOT return response here (important fix)
  //         // \Log::error('storeDetail error', [
  //         //     'message' => $e->getMessage(),
  //         //     'data' => $detail,
  //         // ]);

  //         throw $e; // let transaction handle it
  //     }
  // }
}
