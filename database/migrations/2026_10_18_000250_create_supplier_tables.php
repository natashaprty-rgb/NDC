<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 200)->index();
            $table->string('registration_number', 80)->nullable();
            $table->string('tax_number', 80)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('services', 500)->nullable(); // goods / services supplied
            $table->string('status', 20)->default('active')->index(); // active, inactive, blacklisted
            $table->text('notes')->nullable();
            $table->userstamps();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supplier_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('title', 200);
            $table->text('scope')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable()->index();
            $table->decimal('contract_value', 15, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('status', 20)->default('active')->index(); // draft, active, expired, terminated
            $table->text('notes')->nullable();
            $table->userstamps();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_contracts');
        Schema::dropIfExists('suppliers');
    }
};
