<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientNewController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\FamillesController;
use App\Http\Controllers\ProductController;

use Laravel\Sanctum\PersonalAccessToken;
use App\Helpers\TokenHelper;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/company', [CompanyController::class, 'index']);

Route::post('/login', [ClientController::class, 'login']);
Route::post('/signup', [ClientNewController::class, 'signup']);

Route::get('/wilayas', [ClientNewController::class, 'wilayas']);
Route::get('/communes/{wilaya_id}', [ClientNewController::class, 'communes']);


/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (SANCTUM)
|--------------------------------------------------------------------------
*/

Route::middleware('custom.auth')->group(function () {

    Route::get('/me', [ClientController::class, 'me']);
    Route::delete('/logout', [ClientController::class, 'logout']);

    /*
    | Products
    */
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/cartCount', [ProductController::class, 'cartCount']);
        Route::get('/search/{search}', [ProductController::class, 'search']);
        Route::get('/imagesbanner', [ProductController::class, 'imagesbanner']);
        Route::get('/parfamilles', [ProductController::class, 'parfamilles']);
    });

    /*
    | Familles
    */
    Route::prefix('familles')->group(function () {
        Route::get('/', [FamillesController::class, 'index']);
        Route::get('/{FamNo}', [FamillesController::class, 'soufamilles']);
    });

    /*
    | Commandes
    */
    Route::prefix('commandes')->group(function () {
        Route::get('/', [CommandeController::class, 'index']);
        Route::get('/cart', [CommandeController::class, 'cart']);
        Route::get('/cart/{CdeID}', [CommandeController::class, 'cartTrackingDetails']);
        Route::get('/confirmcommande', [CommandeController::class, 'confirmCommande']);
        Route::post('/store', [CommandeController::class, 'store']);
        Route::delete('/deletedetailsbyid', [CommandeController::class, 'deleteDetailsById']);
        Route::get('/{id}', [CommandeController::class, 'details']);
    });
});

/*
|--------------------------------------------------------------------------
| HEALTH CHECK / STATUS
|--------------------------------------------------------------------------
*/

Route::get('/test', fn() => response()->json(['message' => 'API Works']));
