<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 60)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('confidentiality', 20)->default('internal')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->date('document_date')->nullable();
            $table->date('review_date')->nullable()->index();
            $table->date('expiry_date')->nullable()->index();
            $table->unsignedInteger('current_version')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('original_name');
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->text('change_summary')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'version']);
        });

        // Explicit per-user access grants for restricted documents.
        Schema::create('document_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'user_id']);
        });

        Schema::create('document_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('correspondences', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 60)->unique();
            $table->string('direction', 10)->index();
            $table->string('external_reference')->nullable();
            $table->string('subject');
            $table->string('sender');
            $table->string('recipient');
            $table->date('letter_date')->nullable();
            $table->date('received_or_sent_date')->nullable()->index();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsible_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('priority', 10)->default('normal');
            $table->boolean('is_confidential')->default(false);
            $table->date('response_due_date')->nullable()->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('summary')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('correspondences')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('correspondence_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_actions');
        Schema::dropIfExists('correspondences');
        Schema::dropIfExists('document_access_logs');
        Schema::dropIfExists('document_user');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
