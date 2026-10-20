<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('staff_number', 40)->unique();
            $table->string('full_name');
            $table->string('job_title')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('employment_status', 20)->default('active')->index();
            $table->string('employment_type', 20)->default('permanent');
            $table->date('hire_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('gender', 20)->nullable();
            // Sensitive fields are encrypted at rest (cast "encrypted" on the model).
            $table->text('date_of_birth')->nullable();
            $table->text('national_id')->nullable();
            $table->text('personal_phone')->nullable();
            $table->text('address')->nullable();
            $table->text('emergency_contact')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staff_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('title');
            $table->string('provider')->nullable();
            $table->string('type', 40)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('certificate_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_trainings');
        Schema::dropIfExists('staff');
    }
};
