<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Organisation structure, staff, application settings, reference number
 * sequences, generic (polymorphic) attachments and backup records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('name_so', 150)->nullable(); // optional Somali display name
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->userstamps();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('staff_number', 30)->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name', 150)->index();
            $table->string('job_title', 150)->nullable();
            $table->string('official_email')->nullable();
            $table->string('official_phone', 40)->nullable();
            $table->string('office_location', 150)->nullable();
            // Employment details: visible only with staff.view_sensitive
            $table->string('employment_status', 20)->default('active')->index(); // active, on_leave, suspended, separated
            $table->string('employment_type', 20)->default('permanent'); // permanent, contract, secondment, intern, consultant
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->userstamps();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, int, bool, json
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Concurrency-safe reference number generation (row locked with SELECT ... FOR UPDATE)
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40);
            $table->string('period', 10); // e.g. 2026, or "all"
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['key', 'period']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type', 120);
            $table->unsignedBigInteger('attachable_id');
            $table->string('category', 40)->default('general'); // evidence, receipt, quotation, contract, scan, agenda, minutes...
            $table->string('original_name');
            $table->string('disk', 20)->default('local');
            $table->string('path')->unique();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['attachable_type', 'attachable_id']);
        });

        Schema::create('backup_records', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // database, files, full
            $table->string('method', 20)->default('manual'); // manual, command
            $table->string('location')->nullable(); // where the backup is stored (no secrets)
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->string('status', 20)->index(); // successful, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('restore_tested_at')->nullable();
            $table->string('restore_test_result', 20)->nullable(); // passed, failed
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_records');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('departments');
    }
};
