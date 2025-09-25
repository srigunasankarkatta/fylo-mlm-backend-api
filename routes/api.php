<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\IncomeConfigController;
use App\Http\Controllers\Admin\ClubIncomeController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\InvestmentPlanController;

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

        // Investment plans management routes
        Route::get('investment-plans/active', [InvestmentPlanController::class, 'active']);
        Route::get('investment-plans/for-amount', [InvestmentPlanController::class, 'forAmount']);
        Route::post('investment-plans/bulk-update', [InvestmentPlanController::class, 'bulkUpdate']);
        Route::get('investment-plans/export', [InvestmentPlanController::class, 'export']);
        Route::apiResource('investment-plans', InvestmentPlanController::class);
        Route::post('investment-plans/{id}/restore', [InvestmentPlanController::class, 'restore']);
        Route::delete('investment-plans/{id}/force-delete', [InvestmentPlanController::class, 'forceDelete']);
        Route::get('investment-plans/{id}/stats', [InvestmentPlanController::class, 'stats']);
        Route::patch('investment-plans/{id}/toggle-status', [InvestmentPlanController::class, 'toggleStatus']);
        Route::post('investment-plans/{id}/create-version', [InvestmentPlanController::class, 'createVersion']);

        // System settings management routes
        Route::apiResource('system-settings', SystemSettingController::class);
        Route::get('system-settings/key/{key}', [SystemSettingController::class, 'showByKey']);
        Route::post('system-settings/{id}/restore', [SystemSettingController::class, 'restore']);
        Route::delete('system-settings/{id}/force-delete', [SystemSettingController::class, 'forceDelete']);
        Route::get('system-settings/stats', [SystemSettingController::class, 'stats']);
        Route::post('system-settings/{id}/clear-cache', [SystemSettingController::class, 'clearCache']);
        Route::post('system-settings/clear-all-cache', [SystemSettingController::class, 'clearAllCache']);
        Route::post('system-settings/bulk-update', [SystemSettingController::class, 'bulkUpdate']);
        Route::get('system-settings/export', [SystemSettingController::class, 'export']);
        Route::post('system-settings/import', [SystemSettingController::class, 'import']);
    });
});

/*
|--------------------------------------------------------------------------
| User Authentication Routes
|--------------------------------------------------------------------------
|
| Routes for user authentication using JWT
|
*/

use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\Api\PackageController as ApiPackageController;
use App\Http\Controllers\Api\UserPackageController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\InvestmentPlanController as ApiInvestmentPlanController;
use App\Http\Controllers\Api\UserInvestmentController;

// Public routes (no authentication required)
Route::post('register', [UserAuthController::class, 'register']);
Route::post('login', [UserAuthController::class, 'login']);

// Public - packages list/view
Route::get('packages', [ApiPackageController::class, 'index']);
Route::get('packages/{id}', [ApiPackageController::class, 'show']);

// Public - investment plans list/view
Route::get('investment-plans', [ApiInvestmentPlanController::class, 'index']);
Route::get('investment-plans/{id}', [ApiInvestmentPlanController::class, 'show']);

// Protected routes (JWT authentication required)
Route::middleware(['jwt.auth', 'role:user'])->group(function () {
    // Authentication routes
    Route::post('logout', [UserAuthController::class, 'logout']);
    Route::get('me', [UserAuthController::class, 'me']);
    Route::post('refresh', [UserAuthController::class, 'refresh']);

    // User package actions
    Route::post('user/packages', [UserPackageController::class, 'store']); // initiate purchase
    Route::post('user/packages/confirm', [UserPackageController::class, 'confirm']); // payment webhook/callback
    Route::get('user/packages', [UserPackageController::class, 'index']); // list user packages
    Route::get('user/packages/{id}', [UserPackageController::class, 'show']); // view specific package

    // User income actions
    Route::get('user/income/summary', [IncomeController::class, 'summary']); // income summary

    // User investment actions
    Route::get('user/investments', [UserInvestmentController::class, 'index']); // list user investments
    Route::post('user/investments', [UserInvestmentController::class, 'store']); // purchase investment
    Route::get('user/investments/summary', [UserInvestmentController::class, 'summary']); // investment summary
    Route::get('user/investments/transactions', [UserInvestmentController::class, 'transactions']); // investment transactions with earnings
    Route::get('user/investments/earnings', [UserInvestmentController::class, 'earnings']); // earnings summary by period
    Route::get('user/investments/{id}', [UserInvestmentController::class, 'show']); // view specific investment
    Route::get('user/income/records', [IncomeController::class, 'records']); // income records
    Route::get('user/income/by-type', [IncomeController::class, 'byType']); // income by type
    Route::get('user/wallets', [IncomeController::class, 'wallets']); // wallet details
    Route::get('user/club/matrix', [IncomeController::class, 'clubMatrix']); // club matrix
    Route::get('user/ledger/transactions', [IncomeController::class, 'ledgerTransactions']); // ledger transactions

    // Dashboard APIs
    Route::get('user/dashboard/summary', [IncomeController::class, 'dashboardSummary']); // dashboard overview
    Route::get('user/dashboard/quick-stats', [IncomeController::class, 'quickStats']); // quick stats
    Route::get('user/dashboard/recent-activity', [IncomeController::class, 'recentActivity']); // recent activity
    Route::get('user/dashboard/widgets', [IncomeController::class, 'dashboardWidgets']); // dashboard widgets

    // Profile APIs
    Route::get('user/profile', [IncomeController::class, 'profile']); // user profile
    Route::put('user/profile', [IncomeController::class, 'updateProfile']); // update profile
    Route::post('user/change-password', [IncomeController::class, 'changePassword']); // change password

    // Network APIs
    Route::get('user/network/tree', [IncomeController::class, 'networkTree']); // network tree
    Route::get('user/network/stats', [IncomeController::class, 'networkStats']); // network statistics
    Route::get('user/network/members', [IncomeController::class, 'networkMembers']); // team members

    // Earnings APIs (alternative names)
    Route::get('user/earnings/summary', [IncomeController::class, 'summary']); // earnings summary
    Route::get('user/earnings/history', [IncomeController::class, 'records']); // earnings history
    Route::get('user/earnings/by-type', [IncomeController::class, 'byType']); // earnings by type

    // Analytics APIs
    Route::get('user/analytics/performance', [IncomeController::class, 'analyticsPerformance']); // performance analytics
});
