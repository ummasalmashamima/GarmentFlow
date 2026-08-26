<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_number', 50)->unique();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('supply_plan_id')->nullable()->constrained('supply_plans')->nullOnDelete();
            $table->foreignId('buyer_order_id')->nullable()->constrained('buyer_orders')->nullOnDelete();
            $table->decimal('planned_quantity', 15, 4);
            $table->date('planned_start_date');
            $table->date('planned_end_date');
            $table->string('priority', 30)->default('medium');
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('production_plan_id')->constrained('production_plans')->restrictOnDelete();
            $table->foreignId('buyer_order_id')->nullable()->constrained('buyer_orders')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('bom_version_id')->constrained('bom_versions')->restrictOnDelete();
            $table->decimal('planned_quantity', 15, 4);
            $table->decimal('completed_quantity', 15, 4)->default(0);
            $table->decimal('rejected_quantity', 15, 4)->default(0);
            $table->date('start_date')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->foreignId('issue_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('issue_warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('production_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('bom_item_id')->nullable()->constrained('bom_items')->nullOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('bom_quantity', 15, 4);
            $table->decimal('wastage_percentage', 8, 4)->default(0);
            $table->decimal('required_quantity', 15, 4);
            $table->decimal('consumed_quantity', 15, 4)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('production_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->decimal('planned_quantity', 15, 4);
            $table->decimal('completed_quantity', 15, 4);
            $table->decimal('rejected_quantity', 15, 4)->default(0);
            $table->decimal('remaining_quantity', 15, 4)->default(0);
            $table->decimal('progress_percentage', 8, 4)->default(0);
            $table->date('production_date');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('material_consumptions', function (Blueprint $table) {
            $table->id();
            $table->string('consumption_number', 50)->unique();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('production_order_item_id')->constrained('production_order_items')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('inventory_transaction_id')->nullable()->constrained('inventory_transactions')->nullOnDelete();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->date('consumption_date');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('finished_goods', function (Blueprint $table) {
            $table->id();
            $table->string('finished_goods_number', 50)->unique();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('inventory_transaction_id')->nullable()->constrained('inventory_transactions')->nullOnDelete();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->date('finished_date');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finished_goods');
        Schema::dropIfExists('material_consumptions');
        Schema::dropIfExists('production_progress');
        Schema::dropIfExists('production_order_items');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('production_plans');
    }
};
