<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel in-app (database channel) notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Delivery status per channel (database / mail)
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notification_type', 150);
            $table->string('channel', 20);
            $table->string('status', 20)->index(); // sent, failed
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Prevents the scheduler from sending the same reminder twice
        Schema::create('sent_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('reminder_type', 40);
            $table->string('remindable_type', 120);
            $table->unsignedBigInteger('remindable_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('sent_on');
            $table->timestamps();
            $table->unique(['reminder_type', 'remindable_type', 'remindable_id', 'user_id', 'sent_on'], 'sent_reminders_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_reminders');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notifications');
    }
};
