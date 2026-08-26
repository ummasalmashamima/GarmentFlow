<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('period_type', 30);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('forecast_quantity', 15, 4);
            $table->string('method', 50);
            $table->string('status', 30)->default('draft');
            $table->date('forecast_date')->nullable();
            $table->decimal('confidence_score', 8, 4)->nullable();
            $table->decimal('accuracy_score', 8, 4)->nullable();
            $table->unsignedInteger('lookback_periods')->nullable();
            $table->json('calculation_snapshot')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supply_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('period_type', 30);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('confirmed_order_quantity', 15, 4)->nullable()->default(0);
            $table->decimal('forecast_quantity', 15, 4)->nullable()->default(0);
            $table->decimal('required_quantity', 15, 4)->nullable()->default(0);
            $table->decimal('available_quantity', 15, 4)->nullable()->default(0);
            $table->decimal('planned_production_quantity', 15, 4)->nullable()->default(0);
            $table->string('status', 30)->default('calculated');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('mrp_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_number', 50)->unique();
            $table->string('status', 30)->default('completed');
            $table->date('planning_date');
            $table->decimal('total_gross_quantity', 15, 4)->default(0);
            $table->decimal('total_net_quantity', 15, 4)->default(0);
            $table->boolean('inventory_data_available')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('material_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mrp_run_id')->constrained('mrp_runs')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('gross_quantity', 15, 4)->default(0);
            $table->decimal('available_quantity', 15, 4)->default(0);
            $table->decimal('allocated_quantity', 15, 4)->default(0);
            $table->decimal('net_quantity', 15, 4)->default(0);
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('material_requirement_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_requirement_id')->constrained('material_requirements')->cascadeOnDelete();
            $table->foreignId('supply_plan_id')->nullable()->constrained('supply_plans')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('bom_version_id')->nullable()->constrained('bom_versions')->nullOnDelete();
            $table->foreignId('bom_item_id')->nullable()->constrained('bom_items')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('planned_product_quantity', 15, 4)->default(0);
            $table->decimal('bom_quantity', 15, 4)->default(0);
            $table->decimal('wastage_percentage', 8, 4)->default(0);
            $table->decimal('gross_quantity', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_requirement_sources');
        Schema::dropIfExists('material_requirements');
        Schema::dropIfExists('mrp_runs');
        Schema::dropIfExists('supply_plans');
        Schema::dropIfExists('demand_forecasts');
    }
};
