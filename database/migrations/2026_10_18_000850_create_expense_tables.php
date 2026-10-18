<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Expense status lifecycle (each step is recorded, never executed):
 *   recorded -> approved -> paid -> reconciled
 *   recorded -> rejected
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('budget_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 500);
            $table->string('payee', 200)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('USD');
            $table->date('expense_date')->index();
            $table->string('status', 20)->default('recorded')->index();
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('review_note')->nullable();
            $table->text('notes')->nullable();
            $table->userstamps();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['budget_id', 'expense_category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
