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
        $client = TokenHelper::getUser($request);
        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $offset = $request->input('offset', 0); // default 0
        $limit = $request->input('limit', 15);  // default 15
        $famno = $request->input('famNo', '00');  // default 15

        if ($famno !== '00') {
            $products = Product::where('ArtCls', $client->CliCls)
                ->where('ArtFam', $famno)
                ->skip($offset)
                ->take($limit)
                ->get();
        } else {

            $products = Product::where('ArtCls', $client->CliCls)
                ->skip($offset)
                ->take($limit)
                ->get();
        }

        if ($products->isEmpty()) {
            return response()->json(['message' => 'No products found'], 404);
        } else {
            $commandeHead = CommandeHead::where('CdeCls', $client->CliCls)
                ->where('CdeCli', $client->CliNo)
                ->where('CdeStatus', 0)
                ->first();




            $formatted = $products->map(
                function ($product) use ($client, $commandeHead) { {
                        $ArtQte = 0;
                        if ($commandeHead) {
                            if (CommandeDetail::where('CddArt', $product->ArtNo)->where('CddID', $commandeHead->CdeID)->exists()) {
                                $ArtQte = (float) CommandeDetail::where('CddArt', $product->ArtNo)->where('CddID', $commandeHead->CdeID)->first()->CddQte;
                            } else {
                                $ArtQte = 0;
                            }
                        } else {
                            $ArtQte = 0;
                        }

                        return [
                            'ArtNo' => $product->ArtNo,
                            'ArtNom' => $product->ArtNom,
                            'ArtFam' => optional($product->familles($client->CliCls)->first())->FamNom,
                            'ArtSFam' => optional($product->sousfamilles($product->ArtFam, $client->CliCls)->first())->SouNom,
                            'ArtColisage' => optional($product->cloisage($client->CliCls)->first())->ColNo,
                            'ArtColisageNom' => optional($product->cloisage($client->CliCls)->first())->ColABV,
                            'ArtUni' => optional($product->unite($client->CliCls)->first())->UniABV,
                            'ArtUntCol' => (float) $product->ArtUntCol,
                            'ArtPrix' => $product->priceForClient($client),
                            'ArtQte' => $ArtQte,
                            'ArtImages' => $product->imagesarticles($client->CliCls)->get()
                        ];
                    }
                }
            );


            return response()->json(['data' => $formatted, 'cls' => $client->CliCls], 201);
        }
    }

    public function cartCount(Request $request)
    {
        $client = TokenHelper::getUser($request);
        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $commandeHead = CommandeHead::where('CdeCls', $client->CliCls)
            ->where('CdeCli', $client->CliNo)
            ->where('CdeStatus', 0)
            ->first();

        if ($commandeHead) {
            $count = CommandeDetail::where('CddID', $commandeHead->CdeID)->count();
        } else {
            $count = 0;
        }


        return response()->json(['cartCount' => (int) $count], 200);
    }


    public function imagesbanner(Request $request)
    {
        $client = TokenHelper::getUser($request);
        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }




        $images = Imagesbanner::where('ImgCls', $client->CliCls)
            ->get();

        if ($images->isEmpty()) {
            return response()->json(['message' => 'No images found'], 404);
        }
        $images = $images->map(function ($image) use ($client) {

            return [
                'ImgFam' => $image->ImgFam,
                'ImgSFam' => $image->ImgSFam,
                'ImgSFamNom' => optional($image->sousfamilles($image->ImgFam, $client->CliCls)->first())->SouNom,
                'ImgNom' => $image->ImgNom,

            ];
        });

        return response()->json($images, 200);
    }

    public function search(Request $request, $search)
    {
        $client = TokenHelper::getUser($request);
        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }



        $products = Product::with(['company', 'familles', 'sousfamilles', 'cloisage', 'unite'])
            ->where('ArtCls', $client->CliCls)
            ->where(function ($query) use ($search) {
                $query->where('ArtNom', 'like', "%{$search}%");
            })
            ->get();

        if ($products->isEmpty()) {
            return response()->json(['message' => 'No products found'], 404);
        } else {
            $commandeHead = CommandeHead::where('CdeCli', $client->CliNo)
                ->where('CdeStatus', 0)
                ->first();



            $formatted = $products->map(
                function ($product) use ($client, $commandeHead) { {
                        $ArtQte = 0;
                        if ($commandeHead) {
                            if (CommandeDetail::where('CddArt', $product->ArtNo)->where('CddID', $commandeHead->CdeID)->exists()) {
                                $ArtQte = (float) CommandeDetail::where('CddArt', $product->ArtNo)->where('CddID', $commandeHead->CdeID)->first()->CddQte;
                            } else {
                                $ArtQte = 0;
                            }
                        } else {
                            $ArtQte = 0;
                        }
                        return [
                            'ArtNo' => $product->ArtNo,
                            'ArtNom' => $product->ArtNom,
                            'ArtFam' => optional($product->familles($client->CliCls)->first())->FamNom,
                            'ArtSFam' => optional($product->sousfamilles($product->ArtFam, $client->CliCls)->first())->SouNom,
                            'ArtColisage' => optional($product->cloisage($client->CliCls)->first())->ColNo,
                            'ArtColisageNom' => optional($product->cloisage($client->CliCls)->first())->ColABV,
                            'ArtUni' => optional($product->unite($client->CliCls)->first())->UniABV,
                            'ArtUntCol' => (float) $product->ArtUntCol,
                            'ArtPrix' => $product->priceForClient($client),
                            'ArtQte' => $ArtQte,
                        ];
                    }
                }
            );


            return response()->json($formatted, 200);
        }
    }


    public function parfamilles(Request $request)
    {
        $client = TokenHelper::getUser($request);
        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }






        $offset = $request->input('offset', 0); // default 0
        $limit = $request->input('limit', 15);  // default 15

        $products = Product::with(['company', 'familles', 'sousfamilles', 'cloisage', 'unite'])
            ->where('ArtCls', $client->CliCls)
            ->where('ArtFam', $request->input('ArtFam'))
            ->where('ArtSFam', $request->input('ArtSFam'))
            ->skip($offset)
            ->take($limit)
            ->get();

        if ($products->isEmpty()) {
            return response()->json(['message' => 'No products found'], 404);
        } else {
            $commandeHead = CommandeHead::where('CdeCli', $client->CliNo)
                ->where('CdeStatus', 0)
                ->first();



            $formatted = $products->map(
                function ($product) use ($client, $commandeHead) { {
                        $ArtQte = 0;
                        if ($commandeHead) {
                            if (CommandeDetail::where('CddArt', $product->ArtNo)->where('CddID', $commandeHead->CdeID)->exists()) {
                                $ArtQte = (float) CommandeDetail::where('CddArt', $product->ArtNo)->where('CddID', $commandeHead->CdeID)->first()->CddQte;
                            } else {
                                $ArtQte = 0;
                            }
                        } else {
                            $ArtQte = 0;
                        }
                        return [
                            'ArtNo' => $product->ArtNo,
                            'ArtNom' => $product->ArtNom,
                            'ArtFam' => optional($product->familles($client->CliCls)->first())->FamNom,
                            'ArtSFam' => optional($product->sousfamilles($product->ArtFam, $client->CliCls)->first())->SouNom,
                            'ArtColisage' => optional($product->cloisage($client->CliCls)->first())->ColNo,
                            'ArtColisageNom' => optional($product->cloisage($client->CliCls)->first())->ColABV,
                            'ArtUni' => optional($product->unite($client->CliCls)->first())->UniABV,
                            'ArtUntCol' => (float) $product->ArtUntCol,
                            'ArtPrix' => $product->priceForClient($client),
                            'ArtQte' => $ArtQte,
                        ];
                    }
                }
            );


            return response()->json($formatted, 201);
        }
    }
}
