<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Institutional assets. Data-centre equipment (servers, racks, network,
 * storage) can be registered later by adding asset categories with
 * class "data_centre" and storing technical attributes in `specifications`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('name_so', 150)->nullable();
            $table->string('asset_class', 30)->default('office'); // office, ict, furniture, vehicle, data_centre, other
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('building', 150)->nullable();
            $table->string('floor', 30)->nullable();
            $table->string('room', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->userstamps();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 40)->unique();
            $table->string('name', 200)->index();
            $table->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->string('manufacturer', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial_number', 120)->nullable()->unique();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 15, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('warranty_expiry')->nullable()->index();
            $table->string('condition', 20)->default('good'); // new, good, fair, poor, damaged
            // in_stock, in_use, under_maintenance, retired, disposed, lost
            $table->string('status', 30)->default('in_stock')->index();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->json('specifications')->nullable();
            $table->date('retired_on')->nullable();
            $table->string('retirement_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->userstamps();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20); // assigned, transferred, returned, retired
            $table->foreignId('from_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('to_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->date('movement_date');
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // preventive, corrective, inspection
            $table->text('description');
            $table->string('provider', 200)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('scheduled_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled, in_progress, completed, cancelled
            $table->text('notes')->nullable();
            $table->userstamps();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenances');
        Schema::dropIfExists('asset_movements');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('asset_categories');
    }
};
