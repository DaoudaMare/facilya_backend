<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\RelayController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('relay')->middleware('auth:sanctum')->group(function () {
    Route::post('heartbeat', [RelayController::class, 'heartbeat']);
    Route::post('deposits', [RelayController::class, 'deposits']);
    Route::get('transactions', [RelayController::class, 'transactions']);
    Route::get('pending-payments', [RelayController::class, 'pendingPayments']);
    Route::post('transactions/{uuid}/confirm', [RelayController::class, 'confirmPayment']);
    Route::post('transactions/{uuid}/cancel', [RelayController::class, 'cancelPayment']);
    Route::post('transactions/{uuid}/retry', [RelayController::class, 'retryPayment']);
    Route::get('jobs/next', [RelayController::class, 'nextJob']);
    Route::post('jobs/{uuid}/result', [RelayController::class, 'completeJob']);
});

Route::prefix('v1')->group(function () {
    Route::post('auth/otp/request', [AuthController::class, 'requestOtp'])
        ->middleware('throttle:otp');
    Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:20,1');

    Route::get('networks', [CatalogController::class, 'networks']);
    Route::get('promotions', [CatalogController::class, 'promotions']);
    Route::get('travel/cities', [CatalogController::class, 'cities']);
    Route::get('travel/corridors', [CatalogController::class, 'corridors']);
    Route::get('travel/trips', [CatalogController::class, 'trips']);
    Route::get('quotes/transfers', [TransactionController::class, 'quoteTransfer']);
    Route::get('quotes/tickets', [TransactionController::class, 'quoteTicket']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/pin', [AuthController::class, 'setPin']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('me', [AuthController::class, 'updateMe']);
        Route::get('me/stats', [TransactionController::class, 'stats']);

        Route::get('transactions', [TransactionController::class, 'index']);
        Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
        Route::post('transfers', [TransactionController::class, 'storeTransfer']);
        Route::post('tickets', [TransactionController::class, 'storeTicket']);
    });
});
