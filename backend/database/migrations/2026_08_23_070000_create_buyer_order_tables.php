<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('order_number', 50)->unique();
            $table->date('order_date');
            $table->date('delivery_date');
            $table->string('status', 30)->default('draft');
            $table->decimal('total_quantity', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('buyer_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_order_id')->constrained('buyer_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('item_total', 15, 4);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('order_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_order_id')->constrained('buyer_orders')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_order_id')->constrained('buyer_orders')->cascadeOnDelete();
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('order_planning_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_order_id')->constrained('buyer_orders')->cascadeOnDelete();
            $table->string('status', 30)->default('ready');
            $table->decimal('total_quantity', 15, 4)->default(0);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_planning_inputs');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_approvals');
        Schema::dropIfExists('buyer_order_items');
        Schema::dropIfExists('buyer_orders');
    }
};
