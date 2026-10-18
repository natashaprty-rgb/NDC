<?php

use App\Http\Controllers\Finance\BudgetAllocationController;
use App\Http\Controllers\Finance\BudgetController;
use App\Http\Controllers\Finance\ExpenseController;
use Illuminate\Support\Facades\Route;

/*
 * Budget & expense monitoring. Records only: the system never executes payments.
 */
Route::middleware('can:budgets.view')->group(function () {
    Route::resource('budgets', BudgetController::class);
    Route::post('/budgets/{budget}/submit', [BudgetController::class, 'submit'])->name('budgets.submit');
    Route::post('/budgets/{budget}/approve', [BudgetController::class, 'approve'])->name('budgets.approve');
    Route::post('/budgets/{budget}/reject', [BudgetController::class, 'reject'])->name('budgets.reject');
    Route::post('/budgets/{budget}/close', [BudgetController::class, 'close'])->name('budgets.close');

    Route::scopeBindings()->group(function () {
        Route::post('/budgets/{budget}/allocations', [BudgetAllocationController::class, 'store'])->name('budgets.allocations.store');
        Route::put('/budgets/{budget}/allocations/{allocation}', [BudgetAllocationController::class, 'update'])->name('budgets.allocations.update');
        Route::delete('/budgets/{budget}/allocations/{allocation}', [BudgetAllocationController::class, 'destroy'])->name('budgets.allocations.destroy');
    });
});

Route::middleware('can:expenses.view')->group(function () {
    Route::resource('expenses', ExpenseController::class);
    Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
    Route::post('/expenses/{expense}/paid', [ExpenseController::class, 'markPaid'])->name('expenses.paid');
    Route::post('/expenses/{expense}/reconcile', [ExpenseController::class, 'reconcile'])->name('expenses.reconcile');
});
