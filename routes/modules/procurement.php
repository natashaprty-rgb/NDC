<?php

use App\Http\Controllers\Procurement\GoodsReceiptController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\Procurement\PurchaseRequestController;
use App\Http\Controllers\Procurement\QuotationController;
use App\Http\Controllers\Procurement\SupplierContractController;
use App\Http\Controllers\Procurement\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:suppliers.view')->group(function () {
    Route::resource('suppliers', SupplierController::class);
    Route::get('/suppliers/{supplier}/contracts/create', [SupplierContractController::class, 'create'])->name('contracts.create');
    Route::post('/suppliers/{supplier}/contracts', [SupplierContractController::class, 'store'])->name('contracts.store');
    Route::resource('contracts', SupplierContractController::class)->except(['create', 'store'])
        ->parameters(['contracts' => 'contract']);
});

Route::middleware('can:procurement.view')->group(function () {
    Route::resource('purchase-requests', PurchaseRequestController::class);
    Route::post('/purchase-requests/{purchase_request}/submit', [PurchaseRequestController::class, 'submit'])->name('purchase-requests.submit');
    Route::post('/purchase-requests/{purchase_request}/approve', [PurchaseRequestController::class, 'approve'])->name('purchase-requests.approve');
    Route::post('/purchase-requests/{purchase_request}/reject', [PurchaseRequestController::class, 'reject'])->name('purchase-requests.reject');
    Route::post('/purchase-requests/{purchase_request}/cancel', [PurchaseRequestController::class, 'cancel'])->name('purchase-requests.cancel');

    Route::post('/purchase-requests/{purchase_request}/quotations', [QuotationController::class, 'store'])
        ->middleware('throttle:uploads')->name('purchase-requests.quotations.store');
    Route::post('/quotations/{quotation}/select', [QuotationController::class, 'select'])->name('quotations.select');

    Route::get('/purchase-requests/{purchase_request}/orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
    Route::post('/purchase-requests/{purchase_request}/orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    Route::post('/purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::post('/purchase-orders/{purchase_order}/receipts', [GoodsReceiptController::class, 'store'])
        ->middleware('throttle:uploads')->name('purchase-orders.receipts.store');
});
