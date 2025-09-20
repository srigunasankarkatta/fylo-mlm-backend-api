<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\UserController;

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

/*
|--------------------------------------------------------------------------
| Admin Authentication Routes
|--------------------------------------------------------------------------
|
| Routes for admin authentication using JWT
|
*/

Route::prefix('admin')->group(function () {
    // Public routes (no authentication required)
    Route::post('login', [AuthController::class, 'login']);

    // Protected routes (authentication required)
    Route::middleware(['auth:api'])->group(function () {
        // Authentication routes
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('verify', [AuthController::class, 'verify']);

        // User management routes
        Route::apiResource('users', UserController::class);
        Route::post('users/{id}/restore', [UserController::class, 'restore']);
        Route::delete('users/{id}/force-delete', [UserController::class, 'forceDelete']);
        Route::get('users/{id}/tree', [UserController::class, 'tree']);
        Route::get('users/{id}/financial-summary', [UserController::class, 'financialSummary']);
        Route::get('users/{id}/package-history', [UserController::class, 'packageHistory']);
    });
});

/*
|--------------------------------------------------------------------------
| User Authentication Routes (Future)
|--------------------------------------------------------------------------
|
| Routes for user authentication will be added here
|
*/

// Route::prefix('user')->group(function () {
//     Route::post('login', [UserAuthController::class, 'login']);
//     Route::post('register', [UserAuthController::class, 'register']);
//     Route::post('logout', [UserAuthController::class, 'logout'])->middleware('auth:api');
//     Route::get('me', [UserAuthController::class, 'me'])->middleware('auth:api');
// });
