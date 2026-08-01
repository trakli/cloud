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

