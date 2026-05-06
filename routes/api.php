<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Video Translation API Routes
Route::prefix('videos')->group(function () {
    Route::post('/upload', [App\Http\Controllers\Api\VideoController::class, 'upload']);
    Route::get('/{id}/status', [App\Http\Controllers\Api\VideoController::class, 'status']);
    Route::get('/{id}/download', [App\Http\Controllers\Api\VideoController::class, 'download']);
    Route::get('/', [App\Http\Controllers\Api\VideoController::class, 'index']);
    Route::get('/{id}', [App\Http\Controllers\Api\VideoController::class, 'show']);
});
