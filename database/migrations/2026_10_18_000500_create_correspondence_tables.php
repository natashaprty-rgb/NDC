<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondences', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('direction', 10)->index(); // incoming, outgoing
            $table->string('external_reference', 100)->nullable(); // the other party's reference
            $table->string('subject', 300)->index();
            $table->string('sender', 200);
            $table->string('recipient', 200);
            $table->date('letter_date')->nullable();
            $table->date('received_or_sent_date')->index();
            $table->string('delivery_method', 20)->nullable(); // hand, post, email, courier
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('response_deadline')->nullable()->index();
            $table->string('priority', 20)->default('normal'); // low, normal, high, urgent
            $table->string('confidentiality', 20)->default('internal'); // internal, confidential
            // registered, assigned, in_progress, responded, closed
            $table->string('status', 20)->default('registered')->index();
            $table->text('summary')->nullable();
            $table->foreignId('in_reply_to_id')->nullable()->constrained('correspondences')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->userstamps();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('correspondence_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20); // forwarded, follow_up, status_change, note
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_actions');
        Schema::dropIfExists('correspondences');
    }
};
