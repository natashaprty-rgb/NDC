<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Budget monitoring. All money columns are DECIMAL(15,2). This is a
 * monitoring tool, not an accounting ledger: no payments are executed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funding_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('type', 20)->default('government'); // government, donor, grant, loan, other
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('name_so', 150)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('title', 200);
            $table->unsignedSmallInteger('fiscal_year')->index();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained()->nullOnDelete();
            $table->char('currency', 3)->default('USD');
            $table->string('status', 20)->default('draft')->index(); // draft, submitted, approved, closed
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->userstamps();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->string('description', 300)->nullable();
            $table->decimal('amount', 15, 2);
            $table->userstamps();
            $table->timestamps();
            $table->unique(['budget_id', 'expense_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_allocations');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('funding_sources');
    }
};
