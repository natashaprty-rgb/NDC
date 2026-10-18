<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('name_so', 150)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('retention_years')->nullable(); // null = keep indefinitely
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('title', 250)->index();
            $table->foreignId('category_id')->nullable()->constrained('document_categories')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('registration_date');
            // public, internal, confidential, restricted
            $table->string('confidentiality', 20)->default('internal')->index();
            // draft, pending_review, approved, rejected, archived
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_note')->nullable();
            $table->date('review_date')->nullable()->index();
            $table->date('expiry_date')->nullable()->index();
            $table->date('retain_until')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('current_version')->default(0);
            $table->userstamps();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('original_name');
            $table->string('disk', 20)->default('local');
            $table->string('path')->unique();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->string('change_note', 500)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'version_number']);
        });

        // Explicit access grants (used for confidential / restricted documents)
        Schema::create('document_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('level', 10)->default('view'); // view, edit
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'user_id', 'department_id'], 'document_access_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_access');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_categories');
    }
};
