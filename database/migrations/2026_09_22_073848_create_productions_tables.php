<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Productions Header Table
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->string('production_no')->unique(); // e.g. PRD-0001
            $table->date('production_date');
            $table->foreignId('fg_item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('cbm', 10, 2); // Batch volume produced in CBM
            $table->string('plant_name')->default('Plant-01 Central');
            $table->string('batch_no')->nullable();
            $table->text('remarks')->nullable();

            // Audit & Tracking Columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Production Recipe Breakdown Table (Specific formulation used for this batch)
        Schema::create('production_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained('productions')->cascadeOnDelete();
            $table->foreignId('rm_item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('recipe_qty_per_cbm', 12, 4); // Formulation dosage per 1 CBM
            $table->decimal('actual_qty_per_cbm', 12, 4); // Fine-tuned / actual dosage per 1 CBM
            $table->string('unit', 20)->default('kgs');
            $table->decimal('total_qty', 12, 4); // actual_qty_per_cbm * cbm

            // Audit & Tracking Columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Production Material Consumptions Table (Total deduction ledger saved separately)
        Schema::create('production_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained('productions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('consumed_qty', 12, 4); // Quantity consumed in recipe unit (e.g. kgs)
            $table->string('unit', 20)->default('kgs');
            $table->decimal('consumed_primary_qty', 12, 4)->nullable(); // Converted quantity in item primary unit (e.g. ton)
            $table->string('primary_unit', 20)->nullable();
            $table->date('consumed_date');

            // Audit & Tracking Columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_consumptions');
        Schema::dropIfExists('production_recipe_items');
        Schema::dropIfExists('productions');
    }
};
