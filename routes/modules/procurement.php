<?php

use App\Http\Controllers\Procurement\GoodsReceiptController;
use App\Http\Controllers\Procurement\ProcurementReportController;
use App\Http\Controllers\Procurement\ProcurementRequestController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\Procurement\QuotationController;
use App\Http\Controllers\Procurement\SupplierController;
use App\Http\Controllers\Procurement\SupplierEvaluationController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:procurement.view')->group(function () {
    // Reports are registered before the resources so their paths are not
    // captured by the {procurementRequest} / {supplier} parameters.
    Route::get('procurement/register', [ProcurementReportController::class, 'register'])->name('procurement.register');
    Route::get('suppliers/directory', [ProcurementReportController::class, 'suppliers'])->name('suppliers.report');

    Route::resource('procurement', ProcurementRequestController::class)->parameters(['procurement' => 'procurementRequest']);
    Route::post('procurement/{procurementRequest}/submit', [ProcurementRequestController::class, 'submit'])->name('procurement.submit');
    Route::post('procurement/{procurementRequest}/cancel', [ProcurementRequestController::class, 'cancel'])->name('procurement.cancel');

    Route::scopeBindings()->group(function () {
        Route::post('procurement/{procurementRequest}/quotations', [QuotationController::class, 'store'])->name('procurement.quotations.store');
        Route::post('procurement/{procurementRequest}/quotations/{quotation}/select', [QuotationController::class, 'select'])->name('procurement.quotations.select');
        Route::delete('procurement/{procurementRequest}/quotations/{quotation}', [QuotationController::class, 'destroy'])->name('procurement.quotations.destroy');
    });

    Route::get('procurement/{procurementRequest}/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
    Route::post('procurement/{procurementRequest}/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    Route::get('purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
    Route::put('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
    Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::post('purchase-orders/{purchaseOrder}/receipts', [GoodsReceiptController::class, 'store'])->name('purchase-orders.receipts.store');

    Route::resource('suppliers', SupplierController::class);
    Route::post('suppliers/{supplier}/evaluations', [SupplierEvaluationController::class, 'store'])->name('suppliers.evaluations.store');
    Route::delete('suppliers/{supplier}/evaluations/{evaluation}', [SupplierEvaluationController::class, 'destroy'])
        ->scopeBindings()->name('suppliers.evaluations.destroy');
});
