<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Trakli\Cloud\Http\Controllers\CloudController;

/*
|--------------------------------------------------------------------------
| Cloud Plugin API Routes
|--------------------------------------------------------------------------
*/
// Public routes
Route::get('/plans', [CloudController::class, 'getPlans'])->name('plans.index');

Route::get('/benefits', [CloudController::class, 'getBenefits'])->name('benefits.index');

Route::middleware('auth:sanctum')->post('/checkout', [CloudController::class, 'checkout'])->name('checkout');


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/billing/status', [CloudController::class, 'billingStatus']);
    Route::post('/billing/checkout', [CloudController::class, 'checkout']);
    Route::post('/billing/portal', [CloudController::class, 'billingPortal']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/workspace-pricing', [\Trakli\Cloud\Http\Controllers\WorkspaceBillingController::class, 'quote']);
    Route::prefix('/workspaces/{workspace:slug}/billing')->group(function () {
        Route::get('/', [\Trakli\Cloud\Http\Controllers\WorkspaceBillingController::class, 'status']);
        Route::post('/checkout', [\Trakli\Cloud\Http\Controllers\WorkspaceBillingController::class, 'checkout']);
        Route::post('/cancel-checkout', [\Trakli\Cloud\Http\Controllers\WorkspaceBillingController::class, 'cancelCheckout']);
        Route::post('/portal', [\Trakli\Cloud\Http\Controllers\WorkspaceBillingController::class, 'portal']);
    });
});
