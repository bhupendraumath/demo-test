<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get(
    '/user',
    function (Request $request) {
        return $request->user();
    }
);
Route::group(
    ['namespace' => 'Api\V1', 'prefix' => 'v1'],
    function () {
        Route::get(
            '/clear-cache',
            function () {
                Artisan::call('cache:clear');
                Artisan::call('view:clear');
                Artisan::call('route:clear');
                Artisan::call('clear-compiled');
                Artisan::call('config:cache');
                return "Cache is cleared";
            }
        );

        Route::post('forgot-password', [CustomAuthController::class, 'forgotPassword']); 
    }
);
