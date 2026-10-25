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
        Route::match(['get', 'post'], '/confirmcommande', [CommandeController::class, 'confirmCommande']);
        Route::post('/store', [CommandeController::class, 'store']);
        Route::delete('/deletedetailsbyid', [CommandeController::class, 'deleteDetailsById']);
        Route::get('/{id}', [CommandeController::class, 'details']);
    });
});

/*
|--------------------------------------------------------------------------
| SANCTUM DEBUG (SAFE + CLEAN)
|--------------------------------------------------------------------------
*/

Route::get('/debug/auth', function (Request $request) {

    $headerToken = $request->header('X-Auth-Token');
    $bearerToken = $request->bearerToken();
    $token = $headerToken ?? $bearerToken;

    $accessToken = $token ? PersonalAccessToken::findToken($token) : null;

    return response()->json([
        'received_tokens' => [
            'x_auth_token' => $headerToken,
            'bearer_token' => $bearerToken,
            'final_used' => $token,
        ],

        'sanctum' => [
            'token_found' => (bool) $accessToken,
            'token_id' => $accessToken?->id,
            'abilities' => $accessToken?->abilities,
        ],

        'auth' => [
            'request_user' => $request->user(),
            'sanctum_check' => auth('sanctum')->check(),
        ],

        'headers' => $request->getHeaders(),
    ]);
});

/*
|--------------------------------------------------------------------------
| CACHE CLEAR (DEV ONLY)
|--------------------------------------------------------------------------
*/

Route::get('/clear-cache', function () {
    Artisan::call('optimize:clear');
    return response()->json(['message' => 'Cache cleared']);
});


/*
|--------------------------------------------------------------------------
| SIMPLE TEST ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/test', fn() => response()->json(['message' => 'API Works']));

Route::get('/test-sanctum', function () {
    return response()->json(['message' => 'Sanctum Works']);
});

/*
|--------------------------------------------------------------------------
| LOW LEVEL DEBUG (TOKEN ONLY)
|--------------------------------------------------------------------------
*/

Route::get('/debug/token', function (Request $request) {
    return response()->json([
        'x_auth_token' => $request->header('X-Auth-Token'),
        'bearer_token' => $request->bearerToken(),
    ]);
});

Route::get('/server-info', function () {
    return response()->json([
        'server' => $_SERVER,
    ]);
});

Route::get('/test-auth-header', function (\Illuminate\Http\Request $request) {
    return [
        'auth' => $request->header('Authorization'),
        'bearer' => $request->bearerToken(),
        'server' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    ];
});
