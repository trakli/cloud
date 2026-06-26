<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Trakli\Cloud\Http\Controllers\CloudController;

/*
|--------------------------------------------------------------------------
| Cloud Plugin API Routes
|--------------------------------------------------------------------------
*/
// Public routes
Route::get('/plans', [CloudController::class, 'getPlans'])->name('plans.index');

Route::get('/benefits', [CloudController::class, 'getBenefits'])->name('benefits.index');

Route::post('/stripe/webhook', [WebhookController::class, 'handleWebhook']);

Route::middleware('auth:sanctum')->post('/checkout', [CloudController::class, 'checkout'])->name('checkout');

