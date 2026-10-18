<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('name', 200)->index();
            $table->text('description')->nullable();
            $table->text('objectives')->nullable();
            $table->string('status', 20)->default('planned')->index(); // planned, in_progress, on_hold, completed, cancelled
            $table->string('priority', 20)->default('medium');
            $table->date('start_date')->nullable();
            $table->date('expected_end_date')->nullable()->index();
            $table->date('actual_end_date')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->index();
            $table->userstamps();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_stakeholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // stakeholder, contractor, consultant, sponsor, partner
            $table->string('name', 150);
            $table->string('organization', 200)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role_description')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('deliverables')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->string('status', 20)->default('pending'); // pending, in_progress, completed, missed
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('project_risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('risk'); // risk, issue
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('likelihood', 10)->default('medium'); // low, medium, high
            $table->string('impact', 10)->default('medium');     // low, medium, high
            $table->string('status', 20)->default('open')->index(); // open, mitigating, closed
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('corrective_action')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained('project_milestones')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('deliverables')->nullable();
            $table->string('priority', 20)->default('medium')->index(); // low, medium, high, critical
            $table->string('status', 30)->default('todo')->index(); // todo, in_progress, awaiting_approval, completed, cancelled
            $table->unsignedTinyInteger('progress')->default(0);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('requires_approval')->default(false);
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete(); // designated approver (optional)
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('completion_evidence')->nullable();
            $table->text('review_note')->nullable(); // last approval / rejection note
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->userstamps();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('project_risks');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('project_stakeholders');
        Schema::dropIfExists('projects');
    }
};
