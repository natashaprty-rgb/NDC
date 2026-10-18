<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('title', 250)->index();
            $table->string('meeting_type', 30)->default('internal'); // internal, board, project, external, workshop
            $table->text('description')->nullable();
            $table->string('location', 250)->nullable();
            $table->string('online_link', 500)->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('status', 20)->default('scheduled')->index(); // scheduled, completed, cancelled, postponed
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->text('agenda')->nullable();
            $table->longText('minutes')->nullable();
            $table->foreignId('minutes_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('minutes_recorded_at')->nullable();
            $table->userstamps();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('external_name', 150)->nullable();
            $table->string('external_organization', 200)->nullable();
            $table->string('external_email')->nullable();
            $table->string('attendance', 20)->default('invited'); // invited, attended, absent, excused
            $table->timestamps();
            $table->unique(['meeting_id', 'user_id']);
        });

        Schema::create('meeting_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // decision, action
            $table->text('description');
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable()->index();
            $table->string('status', 20)->default('open'); // open, done, cancelled
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete(); // action converted to a task
            $table->userstamps();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_items');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
    }
};
