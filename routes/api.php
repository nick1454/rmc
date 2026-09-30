<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DispatchReportController;
use App\Http\Controllers\Api\FinancialReportController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\OutwardPaymentController;
use App\Http\Controllers\Api\PartyController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\PurchasePaymentController;
use App\Http\Controllers\Api\RecipeItemController;
use App\Http\Controllers\Api\StockInwardController;
use App\Http\Controllers\Api\StockOutwardController;
use App\Http\Controllers\Api\StockReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public Auth Endpoints
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected API Endpoints (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/navigation', [NavigationController::class, 'index']);
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

        // Category Master API
        Route::apiResource('categories', CategoryController::class);

        // Item Master API
        Route::get('/items/meta/types', [ItemController::class, 'meta']);
        Route::apiResource('items', ItemController::class);

        // Customer / Vendor (Party) Master API
        Route::get('/parties/meta', [PartyController::class, 'meta']);
        Route::apiResource('parties', PartyController::class);

        // Master Recipe Items API
        Route::get('/items/{item}/recipe', [RecipeItemController::class, 'index']);
        Route::post('/items/{item}/recipe', [RecipeItemController::class, 'sync']);
        Route::delete('/items/{item}/recipe/{recipeItem}', [RecipeItemController::class, 'destroy']);

        // Stock Inward API
        Route::get('/inwards/meta', [StockInwardController::class, 'meta']);
        Route::apiResource('inwards', StockInwardController::class);

        // Stock Outward API
        Route::get('/outwards/meta', [StockOutwardController::class, 'meta']);
        Route::apiResource('outwards', StockOutwardController::class);

        // Purchase Payments API
        Route::get('/purchase-payments/meta', [PurchasePaymentController::class, 'meta']);
        Route::get('/purchase-payments/pending-invoices/{partyId}', [PurchasePaymentController::class, 'pendingInvoices']);
        Route::post('/purchase-payments/{id}/toggle-lock', [PurchasePaymentController::class, 'toggleLock']);
        Route::apiResource('purchase-payments', PurchasePaymentController::class);

        // Outward Payments API (Customer Receipts)
        Route::get('/outward-payments/meta', [OutwardPaymentController::class, 'meta']);
        Route::get('/outward-payments/all-pending-outwards', [OutwardPaymentController::class, 'allPendingOutwards']);
        Route::get('/outward-payments/pending-invoices/{partyId}', [OutwardPaymentController::class, 'pendingInvoices']);
        Route::post('/outward-payments/{id}/toggle-lock', [OutwardPaymentController::class, 'toggleLock']);
        Route::apiResource('outward-payments', OutwardPaymentController::class);

        // Production API
        Route::get('/productions/meta', [ProductionController::class, 'meta']);
        Route::post('/productions/validate-stock', [ProductionController::class, 'validateStock']);
        Route::apiResource('productions', ProductionController::class);

        // Stock Report API
        Route::get('/reports/stock/meta', [StockReportController::class, 'meta']);
        Route::get('/reports/stock/summary', [StockReportController::class, 'summary']);
        Route::get('/reports/stock/ledger/{itemId}', [StockReportController::class, 'ledger']);

        // Dispatch & Customer Billing Report API
        Route::get('/reports/dispatch-payment/meta', [DispatchReportController::class, 'meta']);
        Route::get('/reports/dispatch-payment', [DispatchReportController::class, 'index']);

        // Financial Reports API
        Route::get('/reports/financial/meta', [FinancialReportController::class, 'meta']);
        Route::get('/reports/financial/customer-outstanding', [FinancialReportController::class, 'customerOutstanding']);
        Route::get('/reports/financial/supplier-payables', [FinancialReportController::class, 'supplierPayables']);
        Route::get('/reports/financial/cash-bank-book', [FinancialReportController::class, 'cashBankBook']);
        Route::get('/reports/financial/party-ledger', [FinancialReportController::class, 'partyLedger']);
    });
});

// Direct routes compatibility
Route::post('/auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/navigation', [NavigationController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::apiResource('categories', CategoryController::class);
    Route::get('/items/meta/types', [ItemController::class, 'meta']);
    Route::apiResource('items', ItemController::class);

    Route::get('/parties/meta', [PartyController::class, 'meta']);
    Route::apiResource('parties', PartyController::class);

    Route::get('/items/{item}/recipe', [RecipeItemController::class, 'index']);
    Route::post('/items/{item}/recipe', [RecipeItemController::class, 'sync']);
    Route::delete('/items/{item}/recipe/{recipeItem}', [RecipeItemController::class, 'destroy']);

    Route::get('/inwards/meta', [StockInwardController::class, 'meta']);
    Route::apiResource('inwards', StockInwardController::class);

    Route::get('/outwards/meta', [StockOutwardController::class, 'meta']);
    Route::apiResource('outwards', StockOutwardController::class);

    Route::get('/purchase-payments/meta', [PurchasePaymentController::class, 'meta']);
    Route::get('/purchase-payments/pending-invoices/{partyId}', [PurchasePaymentController::class, 'pendingInvoices']);
    Route::post('/purchase-payments/{id}/toggle-lock', [PurchasePaymentController::class, 'toggleLock']);
    Route::apiResource('purchase-payments', PurchasePaymentController::class);

    Route::get('/outward-payments/meta', [OutwardPaymentController::class, 'meta']);
    Route::get('/outward-payments/all-pending-outwards', [OutwardPaymentController::class, 'allPendingOutwards']);
    Route::get('/outward-payments/pending-invoices/{partyId}', [OutwardPaymentController::class, 'pendingInvoices']);
    Route::post('/outward-payments/{id}/toggle-lock', [OutwardPaymentController::class, 'toggleLock']);
    Route::apiResource('outward-payments', OutwardPaymentController::class);

    Route::get('/productions/meta', [ProductionController::class, 'meta']);
    Route::post('/productions/validate-stock', [ProductionController::class, 'validateStock']);
    Route::apiResource('productions', ProductionController::class);

    Route::get('/reports/stock/meta', [StockReportController::class, 'meta']);
    Route::get('/reports/stock/summary', [StockReportController::class, 'summary']);
    Route::get('/reports/stock/ledger/{itemId}', [StockReportController::class, 'ledger']);

    Route::get('/reports/dispatch-payment/meta', [DispatchReportController::class, 'meta']);
    Route::get('/reports/dispatch-payment', [DispatchReportController::class, 'index']);

    Route::get('/reports/financial/meta', [FinancialReportController::class, 'meta']);
    Route::get('/reports/financial/customer-outstanding', [FinancialReportController::class, 'customerOutstanding']);
    Route::get('/reports/financial/supplier-payables', [FinancialReportController::class, 'supplierPayables']);
    Route::get('/reports/financial/cash-bank-book', [FinancialReportController::class, 'cashBankBook']);
    Route::get('/reports/financial/party-ledger', [FinancialReportController::class, 'partyLedger']);
});
