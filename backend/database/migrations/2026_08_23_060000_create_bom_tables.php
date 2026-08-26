<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_headers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('status', 30)->default('draft');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bom_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_header_id')->constrained('bom_headers')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['bom_header_id', 'version_number']);
        });

        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_version_id')->constrained('bom_versions')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('wastage_percentage', 8, 4)->default(0);
            $table->unsignedInteger('line_number')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['bom_version_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('bom_versions');
        Schema::dropIfExists('bom_headers');
    }
};
