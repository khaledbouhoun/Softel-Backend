<?php

namespace App\Http\Controllers;

use App\Helpers\TokenHelper;
use App\Models\CommandeDetail;
use App\Models\CommandeHead;
use App\Models\Imagesbanner;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{

  public function index(Request $request)
  {
    $client = $request->attributes->get('auth_user') ?? TokenHelper::getUser($request);

    if (!$client) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    $limit = min((int) $request->query('limit', 15), 50);
    $offset = max((int) $request->query('offset', 0), 0);
    $famno = $request->query('famNo', '00');

    // -------------------------
    // QUERY (USING SCOPES)
    // -------------------------
    $query = Product::forClient($client);

    if ($famno !== '00') {
      $query->where('ArtFam', $famno);
    }

    $products = $query
      ->withClientRelations($client) // ⚡ CLEAN + CENTRALIZED
      ->skip($offset)
      ->take($limit)
      ->get();

    if ($products->isEmpty()) {
      return response()->json(['message' => 'No products found'], 404);
    }

    // -------------------------
    // CART (optimized already)
    // -------------------------
    $commandeHead = CommandeHead::where('CdeCls', $client->CliCls)
      ->where('CdeCli', $client->CliNo)
      ->where('CdeStatus', 0)
      ->first();

    $details = collect();

    if ($commandeHead) {
      $details = CommandeDetail::where('CddID', $commandeHead->CdeID)
        ->get()
        ->keyBy('CddArt');
    }

    // -------------------------
    // FORMAT RESPONSE (NO QUERIES INSIDE LOOP)
    // -------------------------
    $formatted = $products->map(function ($product) use ($client, $details) {

      $ArtQte = $details[$product->ArtNo]->CddQte ?? 0;

      return [
        'ArtNo' => $product->ArtNo,
        'ArtNom' => $product->ArtNom,

        // ✅ FIXED: direct relations (NO parameters)
        'ArtFam' => optional($product->familles)->FamNom,
        'ArtSFam' => optional($product->sousfamilles)->SouNom,

        'ArtColisage' => optional($product->cloisage)->ColNo,
        'ArtColisageNom' => optional($product->cloisage)->ColABV,

        'ArtUni' => optional($product->unite)->UniABV,

        'ArtUntCol' => (float) $product->ArtUntCol,

        // ⚡ optimized pricing (no extra queries)
        'ArtPrix' => $product->priceForClient($client),

        'ArtQte' => (float) $ArtQte,

        // ⚡ already eager loaded
        'ArtImages' => $product->imagesarticles->map(function ($img) {
          return [
            'ImgNom' => $img->ImgNom
              ? url('images/articles/' . $img->ImgNom)
              : url('images/default.png'),
          ];
        }),
      ];
    });

    return response()->json([
      'data' => $formatted,
      'cls' => $client->CliCls
    ], 200);
  }

  public function cartCount(Request $request)
  {
    $client = $request->attributes->get('auth_user') ?? TokenHelper::getUser($request);

    if (!$client) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    $commandeHead = CommandeHead::where('CdeCls', $client->CliCls)
      ->where('CdeCli', $client->CliNo)
      ->where('CdeStatus', 0)
      ->first();

    $count = 0;

    if ($commandeHead) {
      $count = CommandeDetail::where('CddID', $commandeHead->CdeID)->count();
    }

    return response()->json([
      'cartCount' => (int) $count
    ], 200);
  }


  public function imagesbanner(Request $request)
  {
    $client = $request->attributes->get('auth_user') ?? TokenHelper::getUser($request);

    if (!$client) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    $images = Imagesbanner::with('famille.souFamilles') // Load through famille!
      ->where('ImgCls', $client->CliCls)
      ->get();

    if ($images->isEmpty()) {
      return response()->json(['message' => 'No images found'], 404);
    }

    $formatted = $images->map(function ($image) {
      // Find the correct SouFamille from the eager-loaded collection
      $souFamille = ($image->ImgSFam !== null && $image->ImgSFam !== '')
        ? $image->famille?->souFamilles?->firstWhere('SouNo', $image->ImgSFam)
        : null;

      return [
        'ImgFam' => $image->ImgFam,
        'ImgSFam' => $image->ImgSFam,

        // ✅ Get the correct SouFamille name
        'ImgSFamNom' => $souFamille?->SouNom ?? 'N/A',

        'ImgNom' => $image->ImgNom
          ? url('images/banners/' . $image->ImgNom)
          : url('images/default.png'),
      ];
    });

    return response()->json($formatted, 200);
  }

  public function search(Request $request, $search)
  {
    $client = $request->attributes->get('auth_user') ?? TokenHelper::getUser($request);

    if (!$client) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    $limit = min((int) $request->query('limit', 15), 50);
    $offset = max((int) $request->query('offset', 0), 0);

    // -------------------------
    // Search query
    // -------------------------
    $products = Product::where('ArtCls', $client->CliCls)
      ->where(function ($q) use ($search) {
        $q->where('ArtNom', 'like', "{$search}%")   // starts with (fast)
          ->orWhere('ArtNom', 'like', "% {$search}%"); // word match
      })
      ->with([
        'familles',
        'sousfamilles' => fn($q) => $q->where('SouCls', $client->CliCls),
        'cloisage',
        'unite',
        'imagesarticles' => fn($q) => $q->where('ImgCls', $client->CliCls),
        'tarifs' => fn($q) => $q->where('TfdCls', $client->CliCls),
      ])
      ->skip($offset)
      ->take($limit)
      ->get();

    if ($products->isEmpty()) {
      return response()->json(['message' => 'No products found'], 404);
    }

    // -------------------------
    // Load cart details once
    // -------------------------
    $commandeHead = CommandeHead::where('CdeCli', $client->CliNo)
      ->where('CdeStatus', 0)
      ->first();

    $details = collect();

    if ($commandeHead) {
      $details = CommandeDetail::where('CddID', $commandeHead->CdeID)
        ->get()
        ->keyBy('CddArt');
    }

    // -------------------------
    // Format
    // -------------------------
    $formatted = $products->map(function ($product) use ($client, $details) {

      $ArtQte = $details[$product->ArtNo]->CddQte ?? 0;

      return [
        'ArtNo' => $product->ArtNo,
        'ArtNom' => $product->ArtNom,

        'ArtFam' => optional($product->familles)->FamNom,
        'ArtSFam' => optional($product->sousfamilles)->SouNom,

        'ArtColisage' => optional($product->cloisage)->ColNo,
        'ArtColisageNom' => optional($product->cloisage)->ColABV,

        'ArtUni' => optional($product->unite)->UniABV,

        'ArtUntCol' => (float) $product->ArtUntCol,
        'ArtPrix' => $product->priceForClient($client),

        'ArtQte' => (float) $ArtQte,

        'ArtImages' => $product->imagesarticles->map(fn($img) => [
          'ImgNom' => $img->ImgNom
            ? url('images/articles/' . $img->ImgNom)
            : url('images/default.png'),
        ]),
      ];
    });

    return response()->json($formatted, 200);
  }

  public function parfamilles(Request $request)
  {
    $client = $request->attributes->get('auth_user') ?? TokenHelper::getUser($request);

    if (!$client) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    $limit = min((int) $request->query('limit', 15), 50);
    $offset = max((int) $request->query('offset', 0), 0);

    $ArtFam = $request->query('ArtFam');
    $ArtSFam = $request->query('ArtSFam');

    // -------------------------
    // Build query
    // -------------------------
    $query = Product::where('ArtCls', $client->CliCls);

    if ($ArtFam) {
      $query->where('ArtFam', $ArtFam);
    }

    if ($ArtSFam) {
      $query->where('ArtSFam', $ArtSFam);
    }

    // -------------------------
    // Eager loading (FIXED)
    // -------------------------
    $products = $query->with([
      'familles',
      'sousfamilles',
      'cloisage',
      'unite',
      'imagesarticles' => function ($q) use ($client) {
        $q->where('ImgCls', $client->CliCls);
      },
      'tarifs' => fn($q) => $q->where('TfdCls', $client->CliCls),
    ])
      ->skip($offset)
      ->take($limit)
      ->get();

    if ($products->isEmpty()) {
      return response()->json(['message' => 'No products found'], 404);
    }

    // -------------------------
    // Load commande head
    // -------------------------
    $commandeHead = CommandeHead::where('CdeCli', $client->CliNo)
      ->where('CdeStatus', 0)
      ->first();

    // -------------------------
    // Load details ONCE (FIXED)
    // -------------------------
    $details = collect();

    if ($commandeHead) {
      $details = CommandeDetail::where('CddID', $commandeHead->CdeID)
        ->get()
        ->keyBy('CddArt');
    }

    // -------------------------
    // Format response (NO DB calls inside)
    // -------------------------
    $formatted = $products->map(function ($product) use ($client, $details) {

      $ArtQte = isset($details[$product->ArtNo])
        ? (float) $details[$product->ArtNo]->CddQte
        : 0;

      return [
        'ArtNo' => $product->ArtNo,
        'ArtNom' => $product->ArtNom,

        'ArtFam' => optional($product->familles)->FamNom,
        'ArtSFam' => optional($product->sousfamilles)->SouNom,

        'ArtColisage' => optional($product->cloisage)->ColNo,
        'ArtColisageNom' => optional($product->cloisage)->ColABV,

        'ArtUni' => optional($product->unite)->UniABV,

        'ArtUntCol' => (float) $product->ArtUntCol,
        'ArtPrix' => $product->priceForClient($client),

        'ArtQte' => $ArtQte,

        'ArtImages' => $product->imagesarticles->map(function ($img) {
          return [
            'ImgNom' => $img->ImgNom
              ? url('images/articles/' . $img->ImgNom)
              : url('images/default.png'),
          ];
        }),
      ];
    });

    return response()->json($formatted, 200);
  }
}
