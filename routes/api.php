<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\IncomeConfigController;
use App\Http\Controllers\Admin\ClubIncomeController;

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

        // Package management routes
        Route::apiResource('packages', PackageController::class);
        Route::post('packages/{id}/restore', [PackageController::class, 'restore']);
        Route::delete('packages/{id}/force-delete', [PackageController::class, 'forceDelete']);
        Route::get('packages/{id}/stats', [PackageController::class, 'stats']);
        Route::patch('packages/{id}/toggle-status', [PackageController::class, 'toggleStatus']);

        // Income configuration management routes
        Route::apiResource('income-configs', IncomeConfigController::class);
        Route::post('income-configs/{id}/restore', [IncomeConfigController::class, 'restore']);
        Route::delete('income-configs/{id}/force-delete', [IncomeConfigController::class, 'forceDelete']);
        Route::get('income-configs/{id}/stats', [IncomeConfigController::class, 'stats']);
        Route::patch('income-configs/{id}/toggle-status', [IncomeConfigController::class, 'toggleStatus']);
        Route::post('income-configs/{id}/create-version', [IncomeConfigController::class, 'createVersion']);
        Route::get('income-configs/effective', [IncomeConfigController::class, 'effective']);
        Route::get('income-configs/types', [IncomeConfigController::class, 'types']);

        // Club income management routes
        Route::apiResource('club-income', ClubIncomeController::class);
        Route::post('club-income/{id}/restore', [ClubIncomeController::class, 'restore']);
        Route::delete('club-income/{id}/force-delete', [ClubIncomeController::class, 'forceDelete']);
        Route::get('club-income/{id}/stats', [ClubIncomeController::class, 'stats']);
        Route::patch('club-income/{id}/toggle-status', [ClubIncomeController::class, 'toggleStatus']);
        Route::post('club-income/{id}/payout', [ClubIncomeController::class, 'payout']);
        Route::post('club-income/bulk-payout', [ClubIncomeController::class, 'bulkPayout']);
        Route::get('club-income/dashboard', [ClubIncomeController::class, 'dashboard']);
        Route::get('club-income/ready-for-payout', [ClubIncomeController::class, 'readyForPayout']);
        Route::patch('club-income/{id}/mark-completed', [ClubIncomeController::class, 'markCompleted']);
        Route::get('club-income/matrix/{sponsorId}', [ClubIncomeController::class, 'matrixVisualization']);
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
