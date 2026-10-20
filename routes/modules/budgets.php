<?php

use App\Http\Controllers\Budgets\BudgetController;
use App\Http\Controllers\Budgets\BudgetReportController;
use App\Http\Controllers\Budgets\ExpenseController;
use Illuminate\Support\Facades\Route;

/*
| Budget and Expense Tracking (SRS 3.10). Monitoring only: no accounting,
| payroll, banking or payment processing. Record-level checks are done by
| BudgetPolicy and ExpensePolicy.
*/
Route::middleware('permission:budgets.view')->group(function () {
    Route::get('budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::get('budgets/report', [BudgetReportController::class, 'budgets'])->name('budgets.report');
    Route::get('budgets/create', [BudgetController::class, 'create'])->middleware('permission:budgets.create')->name('budgets.create');
    Route::post('budgets', [BudgetController::class, 'store'])->middleware('permission:budgets.create')->name('budgets.store');
    Route::get('budgets/{budget}', [BudgetController::class, 'show'])->whereNumber('budget')->name('budgets.show');
    Route::get('budgets/{budget}/edit', [BudgetController::class, 'edit'])->whereNumber('budget')->name('budgets.edit');
    Route::put('budgets/{budget}', [BudgetController::class, 'update'])->whereNumber('budget')->name('budgets.update');
    Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->whereNumber('budget')->name('budgets.destroy');
    Route::post('budgets/{budget}/submit', [BudgetController::class, 'submit'])->whereNumber('budget')->name('budgets.submit');
    Route::post('budgets/{budget}/close', [BudgetController::class, 'close'])->whereNumber('budget')->name('budgets.close');

    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('expenses/report', [BudgetReportController::class, 'expenses'])->name('expenses.report');
    Route::get('expenses/create', [ExpenseController::class, 'create'])->middleware('permission:expenses.create')->name('expenses.create');
    Route::post('expenses', [ExpenseController::class, 'store'])->middleware('permission:expenses.create')->name('expenses.store');
    Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->whereNumber('expense')->name('expenses.show');
    Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->whereNumber('expense')->name('expenses.edit');
    Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->whereNumber('expense')->name('expenses.update');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->whereNumber('expense')->name('expenses.destroy');
});
